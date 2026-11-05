<?php

/**
 * Entity search controller.
 *
 * Powers the link-control menu picker D4 ships with: when a user adds a
 * menu item and chooses Page / Post / Template / Custom URL, the picker
 * hits this endpoint with `?type=…&q=…` and gets back a flat list of
 * `{ type, id, title, url }` rows it can drop straight into the menu
 * tree as a typed reference.
 *
 * Also powers the block editor's link selector (#1042): `links()` searches
 * every registered resource at once and returns only rows that have a
 * public URL.
 *
 * Sources:
 *
 *   - `page` / `post` / any other slug the {@see ResourceResolver} knows,
 *     whether it comes from `artisanpack.visual-editor.resources` or the
 *     `ap.visualEditor.resources` filter. The model must expose a `title`
 *     or `name` column for the query to bite.
 *   - `template` / `template-part` reach cms-framework's resolvers
 *     (`SiteEditor\Resolution\TemplateResolver` + `TemplatePartResolver`)
 *     when cms-framework is installed. Without cms-framework, the
 *     types are simply unavailable from this endpoint.
 *
 * Resource rows go through the model's `forVisualEditor` scope and its
 * `view` policy ability, so a search can't reveal a draft to someone who
 * can't read it.
 *
 * The endpoint is intentionally READ-only and small: a more elaborate
 * search surface (Scout, faceting) is out of V1 scope. Hard caps live
 * in the per-type `MAX_RESULTS` constant.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Iterator;
use RuntimeException;

class EntitySearchController extends Controller
{
	/**
	 * Hard cap on rows returned per request — keeps the picker
	 * responsive even when a host app has 50,000 pages.
	 */
	protected const MAX_RESULTS = 20;

	/**
	 * How many rows to read per allowed result. The `view` check runs in
	 * PHP, so reading only `MAX_RESULTS` rows would come up short when the
	 * first matches are ones the user can't see.
	 */
	protected const CANDIDATE_MULTIPLIER = 5;

	/**
	 * Search columns already looked up this request, by connection and
	 * table, so the link search doesn't introspect the schema on every
	 * keystroke for every resource.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, string|null>
	 */
	protected array $searchColumns = [];

	/**
	 * @since 1.20.0
	 *
	 * @param  ResourceResolver  $resolver  Resolves resource slugs, including filter-contributed ones.
	 */
	public function __construct( protected ResourceResolver $resolver )
	{
	}

	/**
	 * Performs the search and returns a flat row list.
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): JsonResponse
	{
		$type = trim( (string) $request->query( 'type', '' ) );
		$q    = trim( (string) $request->query( 'q', '' ) );

		if ( '' === $type ) {
			return response()->json( [ 'data' => [] ] );
		}

		if ( in_array( $type, [ 'template', 'template-part' ], true ) ) {
			return response()->json( [ 'data' => $this->searchCmsFrameworkEntities( $type, $q ) ] );
		}

		$slug = $this->resolveResourceSlug( $type );

		if ( null === $slug ) {
			return response()->json( [ 'data' => [] ] );
		}

		return response()->json( [ 'data' => $this->searchResource( $slug, $type, $q ) ] );
	}

	/**
	 * Searches every registered resource for the link selector.
	 *
	 * Rows without a public URL are left out, since they can't be linked
	 * to. An empty query returns the most recently updated content.
	 *
	 * Each resource's rows are read lazily, so the `view` policy and URL
	 * lookup only run on rows until the list is full.
	 *
	 * @since 1.20.0
	 */
	public function links( Request $request ): JsonResponse
	{
		$q      = trim( (string) $request->query( 'q', '' ) );
		$groups = [];

		foreach ( $this->resolver->slugs() as $slug ) {
			$groups[] = $this->candidateRows( $slug, Str::singular( $slug ), $q, '' === $q )
				->filter( static fn ( array $row ): bool => null !== $row['url'] )
				->getIterator();
		}

		$matches = $this->takeFromEachGroup( $groups );

		if ( '' !== $q ) {
			usort( $matches, static fn ( array $a, array $b ): int => strcasecmp( $a['title'], $b['title'] ) );
		}

		return response()->json( [ 'data' => $matches ] );
	}

	/**
	 * Search cms-framework's site-editor entity resolvers for templates
	 * and template parts. Returns an empty list when cms-framework
	 * isn't installed — the Phase H install gate is the user-facing
	 * surface for that condition.
	 *
	 * @since 1.0.0
	 *
	 * @return list<array{type: string, id: string, title: string, url: null}>
	 */
	protected function searchCmsFrameworkEntities( string $type, string $needle ): array
	{
		// No leading backslash: the container does not normalise FQCN
		// keys, so `'\Foo\Bar'` would miss a binding registered under
		// `Foo\Bar::class` and silently build a fresh resolver instead.
		$resolverClass = 'template' === $type
			? 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver'
			: 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver';

		if ( ! class_exists( $resolverClass ) ) {
			return [];
		}

		$resolver = app( $resolverClass );
		$entities = $resolver->all();

		$needle  = mb_strtolower( $needle );
		$matches = [];

		foreach ( $entities as $entity ) {
			$title = (string) ( $entity->title ?? '' );
			$slug  = (string) ( $entity->slug ?? '' );

			if ( '' !== $needle ) {
				$haystack = mb_strtolower( $title . ' ' . $slug );

				if ( ! str_contains( $haystack, $needle ) ) {
					continue;
				}
			}

			$matches[] = [
				'type'  => $type,
				'id'    => $slug,
				'title' => '' !== $title ? $title : $slug,
				'url'   => null,
			];
		}

		// Sort first, slice second — otherwise the returned 20 would
		// depend on the resolver's iteration order rather than being the
		// alphabetically-first matches. Mirrors `searchResourceModel()`'s
		// `orderBy + limit` semantics.
		usort( $matches, static fn ( array $a, array $b ): int => strcasecmp( $a['title'], $b['title'] ) );

		return array_slice( $matches, 0, self::MAX_RESULTS );
	}

	/**
	 * Takes rows from each group in turn, up to `MAX_RESULTS`, so one type
	 * with many matches can't push the others out of the list. A group is
	 * only read as far as the list needs.
	 *
	 * @since 1.20.0
	 *
	 * @param  list<Iterator<int, array{type: string, id: int|string, title: string, url: ?string}>>  $groups
	 *
	 * @return list<array{type: string, id: int|string, title: string, url: ?string}>
	 */
	protected function takeFromEachGroup( array $groups ): array
	{
		$rows  = [];
		$taken = [];

		while ( [] !== $groups ) {
			foreach ( $groups as $index => $group ) {
				// Advance only on the group's next turn, so a list that fills
				// up never checks rows it won't show.
				if ( isset( $taken[ $index ] ) ) {
					$group->next();
				}

				if ( ! $group->valid() ) {
					unset( $groups[ $index ] );

					continue;
				}

				$rows[]          = $group->current();
				$taken[ $index ] = true;

				if ( count( $rows ) >= self::MAX_RESULTS ) {
					return $rows;
				}
			}
		}

		return $rows;
	}

	/**
	 * Resolves a requested type to a registered resource slug. The
	 * resolver's keys are URL slugs (`pages`, `categories`); the picker
	 * accepts the singular form (`page`, `category`) for ergonomics, so
	 * both are checked. Returns null for an unregistered type.
	 *
	 * @since 1.20.0
	 */
	protected function resolveResourceSlug( string $type ): ?string
	{
		foreach ( [ $type, Str::plural( $type ), $type . 's' ] as $candidate ) {
			if ( $this->resolver->has( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Runs the LIKE search against a resource and shapes the rows the user
	 * may view into the picker's response envelope.
	 *
	 * A resource that isn't usable (missing class, no `HasBlockContent`)
	 * returns nothing, so one bad entry can't fail a search across every
	 * type.
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Takes a resource slug, applies the model's editor scope
	 *               and `view` policy, and can order by recency.
	 *
	 * @param  string  $slug        The registered resource slug.
	 * @param  string  $type        The `type` value for the returned rows.
	 * @param  string  $needle      The search term.
	 * @param  bool    $mostRecent  Whether to order by last update instead of title.
	 *
	 * @return list<array{type: string, id: int|string, title: string, url: ?string}>
	 */
	protected function searchResource( string $slug, string $type, string $needle, bool $mostRecent = false ): array
	{
		return $this->candidateRows( $slug, $type, $needle, $mostRecent )
			->take( self::MAX_RESULTS )
			->values()
			->all();
	}

	/**
	 * The rows of a resource that match a search and that the user may
	 * view, shaped for the picker. Rows are read in pages of `MAX_RESULTS`,
	 * with the resource's eager loads, and the `view` policy and the URL
	 * lookup run per row, only as far as the caller reads. So a search across
	 * many resources holds at most one page of models for each.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $slug        The registered resource slug.
	 * @param  string  $type        The `type` value for the returned rows.
	 * @param  string  $needle      The search term.
	 * @param  bool    $mostRecent  Whether to order by last update instead of title.
	 *
	 * @return LazyCollection<int, array{type: string, id: int|string, title: string, url: ?string}>
	 */
	protected function candidateRows( string $slug, string $type, string $needle, bool $mostRecent ): LazyCollection
	{
		try {
			$query = $this->resolver->query( $slug );
		} catch ( InvalidArgumentException|RuntimeException ) {
			return LazyCollection::empty();
		}

		$model        = $query->getModel();
		$searchColumn = $this->resolveSearchColumn( $model );

		if ( null === $searchColumn ) {
			return LazyCollection::empty();
		}

		if ( '' !== $needle ) {
			// `|` is the explicit ESCAPE character — neutral across
			// MySQL / Postgres / SQLite and avoids the
			// backslash-double-escape pitfall the default Laravel
			// LIKE binding has on Postgres + SQLite. Escape `|`, `%`,
			// and `_` in the user's input so the pattern matches the
			// literal characters they typed.
			$escaped = str_replace(
				[ '|', '%', '_' ],
				[ '||', '|%', '|_' ],
				$needle,
			);

			$query->whereRaw(
				$searchColumn . " LIKE ? ESCAPE '|'",
				[ '%' . $escaped . '%' ],
			);
		}

		$this->applyOrder( $query, $model, $searchColumn, $mostRecent );

		// The key breaks ties, so pages of an offset read never overlap.
		$query->orderBy( $model->getQualifiedKeyName() );

		// `lazy()` rather than `cursor()`, so the resource's eager loads
		// still apply to each page.
		return $query
			->lazy( self::MAX_RESULTS )
			->take( self::MAX_RESULTS * self::CANDIDATE_MULTIPLIER )
			->filter( fn ( Model $row ): bool => Gate::allows( 'view', $row ) )
			->map( fn ( Model $row ): array => [
				'type'  => $type,
				'id'    => $row->getKey(),
				'title' => (string) ( $row->{$searchColumn} ?? '' ),
				'url'   => $this->resolveResourceUrl( $row, $slug ),
			] );
	}

	/**
	 * Orders a search by title, or by last update when asked and the model
	 * tracks it.
	 *
	 * @since 1.20.0
	 *
	 * @param  Builder<Model>  $query
	 * @param  Model           $model         The resource's model.
	 * @param  string          $searchColumn  The title column.
	 * @param  bool            $mostRecent    Whether to prefer recently updated rows.
	 */
	protected function applyOrder( Builder $query, Model $model, string $searchColumn, bool $mostRecent ): void
	{
		$updatedAt = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;

		if ( $mostRecent && null !== $updatedAt ) {
			$query->orderByDesc( $updatedAt );

			return;
		}

		$query->orderBy( $searchColumn );
	}

	/**
	 * Returns the first column the model declares that the picker can
	 * search on. `title` is the canonical name; a resource that uses
	 * `name` is supported as a fallback so host apps don't have to
	 * rename columns to wire into the picker.
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Takes the model instance rather than its class, and
	 *               looks each table up once per request.
	 */
	protected function resolveSearchColumn( Model $model ): ?string
	{
		$key = $model->getConnectionName() . '|' . $model->getTable();

		if ( array_key_exists( $key, $this->searchColumns ) ) {
			return $this->searchColumns[ $key ];
		}

		// One listing instead of a `hasColumn()` per candidate; compared
		// without case, as `hasColumn()` does.
		$columns = array_map( 'strtolower', Schema::connection( $model->getConnectionName() )->getColumnListing( $model->getTable() ) );

		foreach ( [ 'title', 'name' ] as $candidate ) {
			if ( in_array( $candidate, $columns, true ) ) {
				return $this->searchColumns[ $key ] = $candidate;
			}
		}

		return $this->searchColumns[ $key ] = null;
	}

	/**
	 * Best-effort URL extraction for a resource model. A `permalink`
	 * accessor wins, then a `url` attribute, then null. Custom URL
	 * menu items are the escape hatch for resources without a public
	 * URL — the picker still resolves the typed reference, the host
	 * app just has to render its own URL at the front end.
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Runs the result through the `ap.visualEditor.search.resourceUrl` filter.
	 */
	protected function resolveResourceUrl( Model $row, string $slug ): ?string
	{
		$url = null;

		foreach ( [ 'permalink', 'url' ] as $attribute ) {
			$value = $row->getAttribute( $attribute );

			if ( is_string( $value ) && '' !== $value ) {
				$url = $value;

				break;
			}
		}

		/**
		 * Filters the public URL of a resource record in entity search.
		 *
		 * Lets a host supply URLs for content types whose models have no
		 * `permalink` or `url`. The link selector leaves out any record
		 * that ends up with no URL.
		 *
		 * @since 1.20.0
		 *
		 * @hook  ap.visualEditor.search.resourceUrl
		 *
		 * @param  string|null  $url   The URL found on the model, or null.
		 * @param  Model        $row   The record.
		 * @param  string       $slug  The resource slug, such as `pages`.
		 *
		 * @return string|null The record's public URL, or null when it has none.
		 */
		$filtered = applyFilters( 'ap.visualEditor.search.resourceUrl', $url, $row, $slug );

		return is_string( $filtered ) && '' !== $filtered ? $filtered : null;
	}
}
