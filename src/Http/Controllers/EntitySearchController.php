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
 * Sources:
 *
 *   - `page` / `post` / any other slug declared in
 *     `artisanpack.visual-editor.resources` map to that entry's
 *     Eloquent model. The model must expose a `title` column for the
 *     query to bite.
 *   - `template` / `template-part` map to the C1/C2 entities so the
 *     picker can drop a template URL into a menu item.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

class EntitySearchController extends Controller
{
	/**
	 * Hard cap on rows returned per request — keeps the picker
	 * responsive even when a host app has 50,000 pages.
	 */
	protected const MAX_RESULTS = 20;

	/**
	 * Built-in search sources mapped to their model + URL strategy.
	 * Resource-config sources (pages, posts) are layered on at request
	 * time so a host app's published `resources` map drives the
	 * picker's available types without controller edits.
	 *
	 * @return array<string, array{model: class-string<Model>, url: callable(Model): ?string}>
	 */
	protected function builtInSources(): array
	{
		return [
			'template'      => [
				'model' => VisualEditorTemplate::class,
				'url'   => fn ( Model $template ): ?string => null,
			],
			'template-part' => [
				'model' => VisualEditorTemplatePart::class,
				'url'   => fn ( Model $part ): ?string => null,
			],
		];
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

		$source = $this->resolveSource( $type );

		if ( null === $source ) {
			return response()->json( [ 'data' => [] ] );
		}

		/** @var class-string<Model> $modelClass */
		$modelClass = $source['model'];

		// `title` is the canonical search column — every entity in the
		// allowlist exposes it, so we only have to dispatch one column
		// name. A model whose schema has neither column gets skipped
		// instead of 500-ing the picker.
		$searchColumn = $this->resolveSearchColumn( $modelClass );

		if ( null === $searchColumn ) {
			return response()->json( [ 'data' => [] ] );
		}

		$query = $modelClass::query();

		if ( '' !== $q ) {
			// `|` is the explicit ESCAPE character — neutral across
			// MySQL / Postgres / SQLite and avoids the
			// backslash-double-escape pitfall the default Laravel
			// LIKE binding has on Postgres + SQLite. Escape `|`, `%`,
			// and `_` in the user's input so the pattern matches the
			// literal characters they typed.
			$escaped = str_replace(
				[ '|', '%', '_' ],
				[ '||', '|%', '|_' ],
				$q
			);

			$query->whereRaw(
				$searchColumn . " LIKE ? ESCAPE '|'",
				[ '%' . $escaped . '%' ]
			);
		}

		$rows = $query
			->orderBy( $searchColumn )
			->limit( self::MAX_RESULTS )
			->get();

		$urlResolver = $source['url'];

		$data = $rows->map( function ( Model $row ) use ( $type, $searchColumn, $urlResolver ): array {
			return [
				'type'  => $type,
				'id'    => $row->getKey(),
				'title' => (string) ( $row->{$searchColumn} ?? '' ),
				'url'   => $urlResolver( $row ),
			];
		} )->all();

		return response()->json( [ 'data' => $data ] );
	}

	/**
	 * Resolves the requested type slug to a model + URL strategy.
	 *
	 * @since 1.0.0
	 *
	 * @return array{model: class-string<Model>, url: callable(Model): ?string}|null
	 */
	protected function resolveSource( string $type ): ?array
	{
		$builtIns = $this->builtInSources();

		if ( isset( $builtIns[ $type ] ) ) {
			return $builtIns[ $type ];
		}

		// Resource-config sources. The map's keys are URL slugs (`pages`,
		// `posts`); the picker accepts the singular form (`page`,
		// `post`) for ergonomics, so both are checked.
		$resources = config( 'artisanpack.visual-editor.resources', [] );

		if ( ! is_array( $resources ) ) {
			return null;
		}

		$candidates = [ $type, $type . 's' ];

		foreach ( $candidates as $candidate ) {
			if ( ! array_key_exists( $candidate, $resources ) ) {
				continue;
			}

			$modelClass = $resources[ $candidate ];

			if ( ! is_string( $modelClass ) || ! class_exists( $modelClass ) ) {
				continue;
			}

			if ( ! is_subclass_of( $modelClass, Model::class ) ) {
				continue;
			}

			return [
				'model' => $modelClass,
				'url'   => fn ( Model $row ): ?string => $this->resolveResourceUrl( $row ),
			];
		}

		return null;
	}

	/**
	 * Returns the first column the model declares that the picker can
	 * search on. `title` is the canonical name; an `a11y` resource that
	 * uses `name` is supported as a fallback so host apps don't have to
	 * rename columns to wire into the picker.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<Model>  $modelClass
	 */
	protected function resolveSearchColumn( string $modelClass ): ?string
	{
		/** @var Model $instance */
		$instance = new $modelClass();

		foreach ( [ 'title', 'name' ] as $candidate ) {
			if ( Schema::connection( $instance->getConnectionName() )->hasColumn( $instance->getTable(), $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Best-effort URL extraction for a resource model. A `route_to_url`
	 * accessor wins, then a `url` attribute, then null. Custom URL menu
	 * items are the escape hatch for resources without a public URL —
	 * the picker still resolves the typed reference, the host app just
	 * has to render its own URL at the front end.
	 *
	 * @since 1.0.0
	 */
	protected function resolveResourceUrl( Model $row ): ?string
	{
		foreach ( [ 'permalink', 'url' ] as $attribute ) {
			$value = $row->getAttribute( $attribute );

			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		return null;
	}
}
