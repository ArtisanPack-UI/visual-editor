<?php

/**
 * Menu controller — H6 site-editor.
 *
 * Wraps cms-framework's H4 menus module in the WP REST `wp_navigation`
 * shape. Unlike the Template / Pattern controllers, this one bypasses
 * H5's `MenuResolver` for REST reads — the resolver is keyed by
 * theme-declared **location** and only surfaces *assigned* menus, while
 * WP REST `wp_navigation` expects id-based lookup over the full menu
 * record set. The resolver continues to power `core/navigation` block
 * resolution at render time as a separate code path.
 *
 * Plan 14 §4.5 maps this controller's surface to the editor's
 * `core-data` shim under `kind: 'postType', name: 'wp_navigation',
 * baseURL: '/menus', key: 'id'`. Items are fetched separately through
 * {@see MenuItemController}.
 *
 * Supersedes the plan 11 Phase D `NavigationController` that read /
 * wrote visual-editor's own `VisualEditorNavigation` model and
 * embedded items in the menu envelope.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor;

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\StoreMenuRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdateMenuRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class MenuController extends Controller
{
	protected const CMS_MENU_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\Menu';

	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\MenuResolver';

	/**
	 * GET `/visual-editor/api/menus` — list all menus, optionally filtered
	 * by `?theme=...`. Returns a flat list of WP-shape `wp_navigation`
	 * records.
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return response()->json( [] );
		}

		$model = self::CMS_MENU_FQCN;
		$query = $model::query()->orderBy( 'id' );

		$theme = trim( (string) $request->query( 'theme', '' ) );
		if ( '' !== $theme ) {
			$query->where( 'theme', $theme );
		}

		$menus = $query->get();

		return response()->json(
			$menus->map( fn ( $menu ) => $this->menuToShape( $menu ) )->all(),
		);
	}

	/**
	 * GET `/visual-editor/api/menus/{id}` — fetch a single menu by id.
	 *
	 * @since 1.0.0
	 */
	public function show( int|string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$menu = $this->findMenu( $id );

		if ( null === $menu ) {
			return response()->json( [ 'message' => 'Menu not found.' ], Response::HTTP_NOT_FOUND );
		}

		return response()->json( $this->menuToShape( $menu ) );
	}

	/**
	 * POST `/visual-editor/api/menus` — create a menu record.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreMenuRequest $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$model = self::CMS_MENU_FQCN;

		try {
			/** @var object $menu */
			$menu = $model::create( $request->validated() );
		} catch ( QueryException $e ) {
			if ( $this->isUniqueViolation( $e ) ) {
				return response()->json( [
					'message' => 'A menu with this slug already exists for the theme.',
					'errors'  => [ 'slug' => [ 'Slug must be unique within the theme.' ] ],
				], Response::HTTP_CONFLICT );
			}

			throw $e;
		}

		return response()->json( $this->menuToShape( $menu ), Response::HTTP_CREATED );
	}

	/**
	 * PUT `/visual-editor/api/menus/{id}` — update a menu record.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateMenuRequest $request, int|string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$menu = $this->findMenu( $id );

		if ( null === $menu ) {
			return response()->json( [ 'message' => 'Menu not found.' ], Response::HTTP_NOT_FOUND );
		}

		try {
			$menu->update( $request->validated() );
		} catch ( QueryException $e ) {
			if ( $this->isUniqueViolation( $e ) ) {
				return response()->json( [
					'message' => 'A menu with this slug already exists for the theme.',
					'errors'  => [ 'slug' => [ 'Slug must be unique within the theme.' ] ],
				], Response::HTTP_CONFLICT );
			}

			throw $e;
		}

		return response()->json( $this->menuToShape( $menu->fresh() ) );
	}

	/**
	 * DELETE `/visual-editor/api/menus/{id}` — delete a menu record.
	 *
	 * Cascade deletes the menu's items and any location assignments via
	 * cms-framework's foreign-key constraints.
	 *
	 * @since 1.0.0
	 */
	public function destroy( int|string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$menu = $this->findMenu( $id );

		if ( null === $menu ) {
			return response()->json( [ 'message' => 'Menu not found.' ], Response::HTTP_NOT_FOUND );
		}

		$menu->delete();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * Look up a menu by primary key. Numeric ids hit the model directly;
	 * non-numeric ids never match because cms-framework's `menus` PK is
	 * numeric — falling through to null returns 404.
	 *
	 * @since 1.0.0
	 */
	protected function findMenu( int|string $id ): ?object
	{
		if ( ! is_numeric( $id ) ) {
			return null;
		}

		$model = self::CMS_MENU_FQCN;

		return $model::query()->whereKey( (int) $id )->first();
	}

	/**
	 * Project a cms-framework `Menu` model into the WP REST
	 * `wp_navigation` envelope. Items are NOT inlined — clients fetch
	 * them through `/menu-items?menu_id={id}`. Per plan 14 §4.5 this
	 * separation matches WP's `wp_navigation` + `wp_navigation_link`
	 * split.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     id: int,
	 *     slug: string,
	 *     theme: string,
	 *     name: string,
	 *     description: string,
	 *     type: string,
	 *     status: string,
	 *     title: array{rendered: string, raw: string},
	 *     auto_add_pages: bool
	 * }
	 */
	protected function menuToShape( object $menu ): array
	{
		$name = (string) ( $menu->name ?? '' );

		return [
			'id'             => (int) $menu->id,
			'slug'           => (string) $menu->slug,
			'theme'          => (string) $menu->theme,
			'name'           => $name,
			'description'    => (string) ( $menu->description ?? '' ),
			'type'           => 'wp_navigation',
			'status'         => 'publish',
			'title'          => [
				'rendered' => $name,
				'raw'      => $name,
			],
			'auto_add_pages' => (bool) ( $menu->auto_add_pages ?? false ),
		];
	}

	/**
	 * @since 1.0.0
	 */
	protected function cmsFrameworkAvailable(): bool
	{
		if ( ! class_exists( self::CMS_MENU_FQCN ) ) {
			return false;
		}

		return app()->bound( self::CMS_RESOLVER_BINDING );
	}

	/**
	 * @since 1.0.0
	 */
	protected function cmsFrameworkUnavailable(): JsonResponse
	{
		return response()->json(
			[ 'message' => 'The site editor requires artisanpack-ui/cms-framework.' ],
			Response::HTTP_NOT_FOUND,
		);
	}

	/**
	 * @since 1.0.0
	 *
	 * @see TemplateController::isUniqueViolation() for the rationale.
	 */
	protected function isUniqueViolation( QueryException $e ): bool
	{
		if ( '23505' === (string) $e->getCode() ) {
			return true;
		}

		$message = strtolower( $e->getMessage() );

		return str_contains( $message, 'unique' )
			|| str_contains( $message, 'duplicate entry' );
	}
}
