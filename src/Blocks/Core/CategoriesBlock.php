<?php

/**
 * Server-rendered `core/categories` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Core;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use stdClass;

/**
 * Renders a taxonomy's terms matching upstream `core/categories` markup.
 * Supports the upstream attributes the editor exposes: `showHierarchy`,
 * `showPostCounts`, `showOnlyTopLevel`, `showEmpty`. `displayAsDropdown`
 * falls back to the list rendering for V1 — the upstream dropdown ships a
 * JS handler we don't run server-side.
 *
 * ## Taxonomies (#960)
 *
 * `taxonomy` picks the terms: `category` (the default) and `post_tag`
 * read cms-framework's `PostCategory` / `PostTag`; any other taxonomy is
 * asked of the host through the {@see self::FILTER_TERMS} filter, and an
 * unanswered one renders the empty shell. Counts only include published
 * posts (cms-framework's `Post::published()` scope).
 *
 * ## Count style, current term and the "All" item (#960)
 *
 * - `countStyle` `inline` (default) keeps upstream's bare ` (N)` after the
 *   link; `badge` moves the count inside the link as
 *   `<span class="wp-block-categories__count">`, next to a
 *   `<span class="wp-block-categories__name">`, so it can be drawn as a
 *   pill and is part of the link's target.
 * - The item whose URL matches the request gets `current-cat` and
 *   `aria-current="page"` on its link (see {@see urlMatchesRequest()}).
 * - `showAll` prepends a `cat-item-all` item labelled `allLabel` ("All")
 *   that links to the unfiltered list (see {@see resolveAllItem()}).
 */
class CategoriesBlock extends DynamicBlock
{
	/**
	 * Filter that supplies the terms of a taxonomy other than `category`
	 * and `post_tag`.
	 *
	 * Signature: `null $terms, string $taxonomy, array $attrs -> array|null`.
	 * Return a list of `['id' => int, 'name' => string, 'url' => string,
	 * 'count' => int, 'parentId' => int|null]` (`count` and `parentId`
	 * optional), in display order, or `null` when the taxonomy is unknown.
	 * `count` should be the term's published-record count.
	 *
	 * @since 1.15.2
	 */
	public const FILTER_TERMS = 'ap.visualEditor.terms';

	/**
	 * Filter that supplies the "All" item's link (`showAll`).
	 *
	 * Signature: `string|null $url, string $taxonomy, array $attrs ->
	 * string|array|null`. The default is the posts index URL for
	 * `category` / `post_tag` and `null` otherwise. Return a URL, or
	 * `['url' => string, 'count' => int]` to also give the total number of
	 * published records — a sum of the term counts double-counts records
	 * that carry several terms, so a host taxonomy's "All" item shows no
	 * count unless the host supplies one. `null` (or an empty URL) drops
	 * the item.
	 *
	 * @since 1.15.2
	 */
	public const FILTER_ALL_URL = 'ap.visualEditor.terms.allUrl';

	/**
	 * Query-string keys that page a list rather than filter it, ignored
	 * when matching an item's URL against the request. cms-framework's
	 * query loops page on `page` or `query-{id}-page`.
	 */
	private const PAGE_QUERY_KEY_PATTERN = '/^(page|paged|query-.+-page)$/';

	private const TAXONOMY_SLUG_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Key of the term rendered as current during the active
	 * {@see render()} call; reset at the start of every render.
	 */
	protected ?string $currentTermKey = null;

	public function name(): string
	{
		return 'artisanpack/categories';
	}

	public function validateAttrs( array $attrs ): array
	{
		$taxonomy = isset( $attrs['taxonomy'] ) && is_string( $attrs['taxonomy'] ) ? strtolower( trim( $attrs['taxonomy'] ) ) : '';

		return [
			'taxonomy'          => '' === $taxonomy ? 'category' : $taxonomy,
			'showHierarchy'     => (bool) ( $attrs['showHierarchy'] ?? false ),
			'showPostCounts'    => (bool) ( $attrs['showPostCounts'] ?? false ),
			'showOnlyTopLevel'  => (bool) ( $attrs['showOnlyTopLevel'] ?? false ),
			'showEmpty'         => (bool) ( $attrs['showEmpty'] ?? false ),
			'displayAsDropdown' => (bool) ( $attrs['displayAsDropdown'] ?? false ),
			'countStyle'        => 'badge' === ( $attrs['countStyle'] ?? 'inline' ) ? 'badge' : 'inline',
			'showAll'           => (bool) ( $attrs['showAll'] ?? false ),
			'allLabel'          => isset( $attrs['allLabel'] ) && is_string( $attrs['allLabel'] ) ? trim( $attrs['allLabel'] ) : '',
			'className'         => isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$this->currentTermKey = null;

		$categories = $this->fetchTerms( $attrs );

		if ( null === $categories || $categories->isEmpty() ) {
			return $this->emptyShell( $attrs, __( 'No categories' ) );
		}

		$request              = $this->currentRequestUrl();
		$this->currentTermKey = $this->findCurrentTermKey( $categories, $request );

		$items = $attrs['showHierarchy']
			? $this->renderHierarchy( $categories, null, $attrs )
			: $this->renderFlat( $categories, $attrs );

		if ( $attrs['showAll'] ) {
			$items = $this->renderAllItem( $categories, $attrs, $request ) . $items;
		}

		$classes = $this->wrapperClasses( $attrs );

		return sprintf(
			'<ul class="%s">%s</ul>',
			e( implode( ' ', $classes ) ),
			$items,
		);
	}

	/**
	 * Returns the terms of the block's taxonomy, or `null` when the
	 * taxonomy is unknown.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attrs  Validated block attributes.
	 *
	 * @return Collection<int, object>|null
	 */
	protected function fetchTerms( array $attrs ): ?Collection
	{
		return match ( $attrs['taxonomy'] ) {
			'category' => $this->fetchCategories( $attrs ),
			'post_tag' => $this->fetchTags( $attrs ),
			default    => $this->fetchHostTerms( $attrs ),
		};
	}

	/**
	 * Returns the category records used by {@see render()}. Pulled out so
	 * tests can override the data source without exercising the database.
	 *
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return Collection<int, object>
	 */
	protected function fetchCategories( array $attrs ): Collection
	{
		$query = PostCategory::query()->withCount( [ 'posts' => static fn ( Builder $posts ): Builder => $posts->published() ] );

		if ( $attrs['showOnlyTopLevel'] ) {
			$query->whereNull( 'parent_id' );
		}

		$query->orderBy( 'name' );

		return $this->withoutEmptyTerms( $query->get(), $attrs );
	}

	/**
	 * Returns the tag records for the `post_tag` taxonomy. Tags are flat,
	 * so `showOnlyTopLevel` and `showHierarchy` keep every tag.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attrs  Validated block attributes.
	 *
	 * @return Collection<int, object>
	 */
	protected function fetchTags( array $attrs ): Collection
	{
		$tags = PostTag::query()
			->withCount( [ 'posts' => static fn ( Builder $posts ): Builder => $posts->published() ] )
			->orderBy( 'name' )
			->get();

		return $this->withoutEmptyTerms( $tags, $attrs );
	}

	/**
	 * Asks the host for a taxonomy's terms through {@see self::FILTER_TERMS}
	 * and normalizes them to the record shape the renderer reads
	 * (`id`, `name`, `permalink`, `posts_count`, `parent_id`). Entries
	 * without a numeric `id`, a `name` or a safe `url` are dropped.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attrs  Validated block attributes.
	 *
	 * @return Collection<int, object>|null `null` when the taxonomy is unknown.
	 */
	protected function fetchHostTerms( array $attrs ): ?Collection
	{
		$taxonomy = (string) $attrs['taxonomy'];

		if ( 1 !== preg_match( self::TAXONOMY_SLUG_PATTERN, $taxonomy ) || ! function_exists( 'applyFilters' ) ) {
			return null;
		}

		$raw = applyFilters( self::FILTER_TERMS, null, $taxonomy, $attrs );

		if ( ! is_array( $raw ) ) {
			return null;
		}

		$terms = new Collection();

		foreach ( $raw as $entry ) {
			$term = $this->normalizeHostTerm( $entry );

			if ( null !== $term ) {
				$terms->push( $term );
			}
		}

		if ( $attrs['showOnlyTopLevel'] ) {
			$terms = $terms->filter( static fn ( object $term ): bool => null === $term->parent_id )->values();
		}

		return $this->withoutEmptyTerms( $terms, $attrs );
	}

	/**
	 * Normalizes one {@see self::FILTER_TERMS} entry, or returns `null`
	 * when it can't be rendered.
	 *
	 * @since 1.15.2
	 */
	protected function normalizeHostTerm( mixed $entry ): ?object
	{
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$id   = $entry['id'] ?? null;
		$name = isset( $entry['name'] ) && is_string( $entry['name'] ) ? trim( $entry['name'] ) : '';
		$url  = $this->sanitizeUrl( $entry['url'] ?? null );

		if ( ! $this->isIntegerLike( $id ) || '' === $name || '' === $url ) {
			return null;
		}

		$parentId = $entry['parentId'] ?? null;
		$count    = $entry['count'] ?? 0;

		$term              = new stdClass();
		$term->id          = (int) $id;
		$term->name        = $name;
		$term->permalink   = $url;
		$term->posts_count = is_numeric( $count ) ? max( 0, (int) $count ) : 0;
		$term->parent_id   = $this->isIntegerLike( $parentId ) ? (int) $parentId : null;

		return $term;
	}

	/**
	 * Drops terms with no published posts unless `showEmpty` is set, keeping
	 * the ancestors of non-empty terms when `showHierarchy` is on.
	 *
	 * Filtering post-hoc keeps the SQL portable: `having` on a `withCount`
	 * alias works in MySQL but not in every SQLite/Postgres configuration,
	 * so the in-memory filter is the safer cross-database choice.
	 *
	 * @since 1.15.2
	 *
	 * @param  Collection<int, object>  $terms
	 * @param  array<string, mixed>     $attrs
	 *
	 * @return Collection<int, object>
	 */
	protected function withoutEmptyTerms( Collection $terms, array $attrs ): Collection
	{
		if ( $attrs['showEmpty'] ) {
			return $terms;
		}

		// With `showHierarchy`, an empty term stays when a descendant has
		// published posts, as in WordPress, or the hierarchy walk (which
		// starts at the top level) would never reach that descendant.
		$byId = $terms->keyBy( static fn ( object $term ): int => (int) $term->id );
		$keep = [];

		foreach ( $terms as $term ) {
			if ( (int) ( $term->posts_count ?? 0 ) <= 0 ) {
				continue;
			}

			$keep[ (int) $term->id ] = true;

			if ( ! $attrs['showHierarchy'] ) {
				continue;
			}

			$parent = $term->parent_id ?? null;

			while ( null !== $parent && isset( $byId[ (int) $parent ] ) && ! isset( $keep[ (int) $parent ] ) ) {
				$keep[ (int) $parent ] = true;
				$parent                = $byId[ (int) $parent ]->parent_id ?? null;
			}
		}

		return $terms->filter( static fn ( object $term ): bool => isset( $keep[ (int) $term->id ] ) )->values();
	}

	/**
	 * @param  Collection<int, object>  $categories
	 * @param  array<string, mixed>     $attrs
	 */
	protected function renderFlat( Collection $categories, array $attrs ): string
	{
		return $categories->map(
			fn ( object $category ): string => $this->renderItem( $category, $attrs ),
		)->implode( '' );
	}

	/**
	 * @param  Collection<int, object>  $categories
	 * @param  array<string, mixed>     $attrs
	 */
	protected function renderHierarchy( Collection $categories, ?int $parentId, array $attrs ): string
	{
		$children = $categories->where( 'parent_id', $parentId );

		if ( $children->isEmpty() ) {
			return '';
		}

		return $children->map( function ( object $category ) use ( $categories, $attrs ): string {
			$nested = $this->renderHierarchy( $categories, (int) $category->id, $attrs );

			$inner = '' === $nested
				? ''
				: sprintf( '<ul class="children">%s</ul>', $nested );

			return $this->renderItem( $category, $attrs, $inner );
		} )->implode( '' );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderItem( object $category, array $attrs, string $extra = '' ): string
	{
		return $this->renderListItem(
			sprintf( 'cat-item cat-item-%d', (int) $category->id ),
			(string) $category->permalink,
			(string) $category->name,
			(int) ( $category->posts_count ?? 0 ),
			null !== $this->currentTermKey && $this->termKey( $category ) === $this->currentTermKey,
			$attrs,
			$extra,
		);
	}

	/**
	 * Builds one `<li>`. In `inline` count style the count follows the
	 * link as ` (N)`, exactly as before #960; in `badge` style the name
	 * and count are spans inside the link, the count carrying a
	 * screen-reader-only noun so it isn't read as a bare number.
	 *
	 * @since 1.15.2
	 *
	 * @param  string                $classes    The item's classes.
	 * @param  string                $url        The item's link.
	 * @param  string                $name       The item's label.
	 * @param  int|null              $count      Published post count, or `null` for none.
	 * @param  bool                  $isCurrent  Whether the item is the page being viewed.
	 * @param  array<string, mixed>  $attrs      Validated block attributes.
	 * @param  string                $extra      Markup after the link (nested list).
	 */
	protected function renderListItem( string $classes, string $url, string $name, ?int $count, bool $isCurrent, array $attrs, string $extra = '' ): string
	{
		$showCount = $attrs['showPostCounts'] && null !== $count;

		if ( 'badge' === $attrs['countStyle'] ) {
			$label = sprintf( '<span class="wp-block-categories__name">%s</span>', e( $name ) );

			if ( $showCount ) {
				$label .= sprintf(
					'<span class="wp-block-categories__count">%d<span class="screen-reader-text">%s</span></span>',
					$count,
					e( ' ' . trans_choice( 'post|posts', (int) $count ) ),
				);
			}

			$after = '';
		} else {
			$label = e( $name );
			$after = $showCount ? sprintf( ' (%d)', $count ) : '';
		}

		return sprintf(
			'<li class="%s"><a href="%s"%s>%s</a>%s%s</li>',
			e( $isCurrent ? $classes . ' current-cat' : $classes ),
			e( $url ),
			$isCurrent ? ' aria-current="page"' : '',
			$label,
			$after,
			$extra,
		);
	}

	/**
	 * Renders the `showAll` item, or `''` when the taxonomy has no "All"
	 * link. It is current when no term is and the request is on its URL
	 * without any of the query parameters the terms filter by.
	 *
	 * @since 1.15.2
	 *
	 * @param  Collection<int, object>                                $categories
	 * @param  array<string, mixed>                                   $attrs
	 * @param  array{path: string, query: array<string, mixed>}|null  $request
	 */
	protected function renderAllItem( Collection $categories, array $attrs, ?array $request ): string
	{
		$all = $this->resolveAllItem( $attrs );

		if ( null === $all ) {
			return '';
		}

		$isCurrent = null === $this->currentTermKey
			&& null !== $request
			&& $this->urlMatchesRequest( $all['url'], $request )
			&& ! $this->requestFiltersByTerm( $categories, $all['url'], $request );

		return $this->renderListItem(
			'cat-item cat-item-all',
			$all['url'],
			'' === $attrs['allLabel'] ? __( 'All' ) : $attrs['allLabel'],
			$all['count'],
			$isCurrent,
			$attrs,
		);
	}

	/**
	 * Whether the request carries a query parameter that the terms' URLs
	 * filter by and the "All" URL doesn't (e.g. `?package-category=` for
	 * a term that isn't listed), in which case "All" isn't current.
	 *
	 * @since 1.15.2
	 *
	 * @param  Collection<int, object>                           $categories
	 * @param  string                                            $allUrl
	 * @param  array{path: string, query: array<string, mixed>}  $request
	 */
	protected function requestFiltersByTerm( Collection $categories, string $allUrl, array $request ): bool
	{
		$allKeys = array_keys( $this->parseUrl( $allUrl )['query'] );

		foreach ( $categories as $category ) {
			foreach ( array_keys( $this->parseUrl( (string) $category->permalink )['query'] ) as $key ) {
				if ( ! in_array( $key, $allKeys, true ) && isset( $request['query'][ $key ] ) && '' !== $request['query'][ $key ] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Resolves the "All" item's link and count through
	 * {@see self::FILTER_ALL_URL}. The default link is the posts index
	 * (config `visual-editor.categories.posts_index_url`) for `category`
	 * and `post_tag`, whose count is every published post; other
	 * taxonomies have no default link and no count unless the host
	 * returns one.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attrs  Validated block attributes.
	 *
	 * @return array{url: string, count: int|null}|null
	 */
	protected function resolveAllItem( array $attrs ): ?array
	{
		$taxonomy  = (string) $attrs['taxonomy'];
		$isBuiltIn = in_array( $taxonomy, [ 'category', 'post_tag' ], true );
		$default   = $isBuiltIn ? $this->postsIndexUrl() : null;

		$resolved = function_exists( 'applyFilters' )
			? applyFilters( self::FILTER_ALL_URL, $default, $taxonomy, $attrs )
			: $default;

		$count = null;

		if ( is_array( $resolved ) ) {
			$count    = isset( $resolved['count'] ) && is_numeric( $resolved['count'] ) ? max( 0, (int) $resolved['count'] ) : null;
			$resolved = $resolved['url'] ?? null;
		}

		$url = $this->sanitizeUrl( $resolved );

		if ( '' === $url ) {
			return null;
		}

		if ( null === $count && $isBuiltIn && $attrs['showPostCounts'] ) {
			$count = $this->countPublishedPosts();
		}

		return [ 'url' => $url, 'count' => $count ];
	}

	/**
	 * The posts index URL the built-in taxonomies' "All" item links to.
	 *
	 * @since 1.15.2
	 */
	protected function postsIndexUrl(): string
	{
		$configured = config( 'visual-editor.categories.posts_index_url', '/blog' );
		$configured = is_string( $configured ) && '' !== trim( $configured ) ? trim( $configured ) : '/blog';

		return str_starts_with( $configured, '/' ) && ! str_starts_with( $configured, '//' ) ? url( $configured ) : $configured;
	}

	/**
	 * Total published posts, the built-in taxonomies' "All" count. Pulled
	 * out so tests can override it without exercising the database.
	 *
	 * @since 1.15.2
	 */
	protected function countPublishedPosts(): int
	{
		return Post::query()->published()->count();
	}

	/**
	 * The current request's path (with the app's base path, matching
	 * `url()`) and query, or `null` outside a request.
	 *
	 * @since 1.15.2
	 *
	 * @return array{path: string, query: array<string, mixed>}|null
	 */
	protected function currentRequestUrl(): ?array
	{
		if ( ! app()->bound( 'request' ) ) {
			return null;
		}

		$request = app( 'request' );

		if ( ! $request instanceof Request ) {
			return null;
		}

		return [
			'path'  => $this->normalizePath( $request->getBaseUrl() . $request->getPathInfo() ),
			'query' => $this->withoutPageKeys( $request->query->all() ),
		];
	}

	/**
	 * Key of the first term whose URL matches the request, or `null`.
	 *
	 * @since 1.15.2
	 *
	 * @param  Collection<int, object>                                $categories
	 * @param  array{path: string, query: array<string, mixed>}|null  $request
	 */
	protected function findCurrentTermKey( Collection $categories, ?array $request ): ?string
	{
		if ( null === $request ) {
			return null;
		}

		foreach ( $categories as $category ) {
			if ( $this->urlMatchesRequest( (string) $category->permalink, $request ) ) {
				return $this->termKey( $category );
			}
		}

		return null;
	}

	/**
	 * Whether a URL points at the request: the paths match, and every
	 * query parameter the URL carries (e.g. `?package-category=forms`)
	 * has the same value in the request. Paging parameters are ignored on
	 * both sides, so page 2 of a term archive still marks the term, and
	 * the request may carry parameters the URL doesn't.
	 *
	 * @since 1.15.2
	 *
	 * @param  string                                            $url      The item's link.
	 * @param  array{path: string, query: array<string, mixed>}  $request  {@see currentRequestUrl()}.
	 */
	protected function urlMatchesRequest( string $url, array $request ): bool
	{
		$parsed = $this->parseUrl( $url );

		if ( $parsed['path'] !== $request['path'] ) {
			return false;
		}

		foreach ( $parsed['query'] as $key => $value ) {
			$actual = $request['query'][ $key ] ?? null;

			if ( ! is_scalar( $value ) || ! is_scalar( $actual ) || (string) $value !== (string) $actual ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Splits a URL into its normalized path and its non-paging query.
	 *
	 * @since 1.15.2
	 *
	 * @return array{path: string, query: array<string, mixed>}
	 */
	protected function parseUrl( string $url ): array
	{
		$path  = parse_url( $url, PHP_URL_PATH );
		$query = [];

		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

		return [
			'path'  => $this->normalizePath( is_string( $path ) ? $path : '' ),
			'query' => $this->withoutPageKeys( $query ),
		];
	}

	/**
	 * Decodes a path and drops its trailing slash (`''` becomes `/`).
	 *
	 * @since 1.15.2
	 */
	protected function normalizePath( string $path ): string
	{
		$path = rtrim( rawurldecode( $path ), '/' );

		return '' === $path ? '/' : $path;
	}

	/**
	 * Drops the paging keys ({@see self::PAGE_QUERY_KEY_PATTERN}).
	 *
	 * @since 1.15.2
	 *
	 * @param  array<array-key, mixed>  $query
	 *
	 * @return array<string, mixed>
	 */
	protected function withoutPageKeys( array $query ): array
	{
		$kept = [];

		foreach ( $query as $key => $value ) {
			if ( 1 !== preg_match( self::PAGE_QUERY_KEY_PATTERN, (string) $key ) ) {
				$kept[ (string) $key ] = $value;
			}
		}

		return $kept;
	}

	/**
	 * Identifies a term within one render; host ids aren't guaranteed
	 * unique across sources, so the URL is part of the key.
	 *
	 * @since 1.15.2
	 */
	protected function termKey( object $category ): string
	{
		return (int) $category->id . '|' . (string) $category->permalink;
	}

	/**
	 * @since 1.15.2
	 */
	protected function isIntegerLike( mixed $value ): bool
	{
		return is_int( $value ) || ( is_string( $value ) && '' !== $value && ctype_digit( $value ) );
	}

	/**
	 * Returns a host-supplied URL only when it is relative or uses an
	 * `http` / `https` scheme, otherwise `''`: `e()` neutralizes markup
	 * but not a `javascript:` link.
	 *
	 * @since 1.15.2
	 */
	protected function sanitizeUrl( mixed $value ): string
	{
		if ( ! is_string( $value ) ) {
			return '';
		}

		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			return '';
		}

		// Browsers drop tabs and newlines inside a URL, so check a copy
		// without control characters or spaces; a URL `parse_url()` can't
		// read fails closed.
		$probe = (string) preg_replace( '/[\x00-\x20\x7F]+/', '', $trimmed );

		if ( false === parse_url( $probe ) ) {
			return '';
		}

		if ( 1 === preg_match( '#^https?:#i', $probe ) ) {
			return $trimmed;
		}

		// Relative only when no `:` comes before the first `/`, `?` or `#`.
		return 1 === preg_match( '#^[^:/?\#]*(?:[/?\#]|$)#', $probe ) ? $trimmed : '';
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-categories', 'wp-block-categories-list' ];

		if ( '' !== $attrs['className'] ) {
			$classes[] = $attrs['className'];
		}

		return $classes;
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function emptyShell( array $attrs, string $message ): string
	{
		return sprintf(
			'<ul class="%s"><li class="cat-item-empty">%s</li></ul>',
			e( implode( ' ', $this->wrapperClasses( $attrs ) ) ),
			e( $message ),
		);
	}
}
