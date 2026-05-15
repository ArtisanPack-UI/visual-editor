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
 *   - `template` / `template-part` reach cms-framework's resolvers
 *     (`SiteEditor\Resolution\TemplateResolver` + `TemplatePartResolver`)
 *     when cms-framework is installed. Without cms-framework, the
 *     types are simply unavailable from this endpoint.
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

		$source = $this->resolveResourceSource( $type );

		if ( null === $source ) {
			return response()->json( [ 'data' => [] ] );
		}

		return response()->json( [ 'data' => $this->searchResourceModel( $source['model'], $type, $q ) ] );
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
		$resolverClass = 'template' === $type
			? '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver'
			: '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver';

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

			if ( count( $matches ) >= self::MAX_RESULTS ) {
				break;
			}
		}

		usort( $matches, static fn ( array $a, array $b ): int => strcasecmp( $a['title'], $b['title'] ) );

		return $matches;
	}

	/**
	 * Resolves a type slug to a `resources`-config Eloquent model.
	 * Returns null for any type not declared in the resources map.
	 *
	 * @since 1.0.0
	 *
	 * @return array{model: class-string<Model>}|null
	 */
	protected function resolveResourceSource( string $type ): ?array
	{
		$resources = config( 'artisanpack.visual-editor.resources', [] );

		if ( ! is_array( $resources ) ) {
			return null;
		}

		// The map's keys are URL slugs (`pages`, `posts`); the picker
		// accepts the singular form (`page`, `post`) for ergonomics, so
		// both are checked.
		foreach ( [ $type, $type . 's' ] as $candidate ) {
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

			return [ 'model' => $modelClass ];
		}

		return null;
	}

	/**
	 * Runs the LIKE search against a resources-config model and shapes
	 * the rows into the picker's response envelope.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<Model>  $modelClass
	 * @return list<array{type: string, id: int|string, title: string, url: ?string}>
	 */
	protected function searchResourceModel( string $modelClass, string $type, string $needle ): array
	{
		$searchColumn = $this->resolveSearchColumn( $modelClass );

		if ( null === $searchColumn ) {
			return [];
		}

		$query = $modelClass::query();

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
				$needle
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

		return $rows->map( fn ( Model $row ): array => [
			'type'  => $type,
			'id'    => $row->getKey(),
			'title' => (string) ( $row->{$searchColumn} ?? '' ),
			'url'   => $this->resolveResourceUrl( $row ),
		] )->values()->all();
	}

	/**
	 * Returns the first column the model declares that the picker can
	 * search on. `title` is the canonical name; a resource that uses
	 * `name` is supported as a fallback so host apps don't have to
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
	 * Best-effort URL extraction for a resource model. A `permalink`
	 * accessor wins, then a `url` attribute, then null. Custom URL
	 * menu items are the escape hatch for resources without a public
	 * URL — the picker still resolves the typed reference, the host
	 * app just has to render its own URL at the front end.
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
