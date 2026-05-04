<?php

/**
 * MenuItem controller — H6 site-editor.
 *
 * Serves the WP REST `wp_navigation_link` surface backed by cms-framework's
 * `MenuItem` model. Items are scoped to a parent `menu_id`; the index
 * endpoint requires `?menu_id=...` so the editor can fetch a single
 * menu's items without paginating across all menus.
 *
 * No H5 resolver pathway here: items don't surface in the H5 filter
 * map directly (they're embedded in `ResolvedMenu::items` as projected
 * arrays). This controller talks straight to cms-framework's model so
 * the editor can do incremental item edits without re-saving the whole
 * menu.
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

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\StoreMenuItemRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdateMenuItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class MenuItemController extends Controller
{
	protected const CMS_MENU_ITEM_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\MenuItem';

	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\MenuResolver';

	/**
	 * GET `/visual-editor/api/menu-items` — list items, scoped by
	 * `?menu_id=`. Without that filter the endpoint returns 422 to
	 * avoid surfacing the whole table at once (consistent with WP's
	 * paginated default; we're skipping pagination for V1).
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return response()->json( [] );
		}

		$menuId = $request->integer( 'menu_id' );

		if ( $menuId < 1 ) {
			return response()->json( [
				'message' => 'A menu_id query parameter is required.',
				'errors'  => [ 'menu_id' => [ 'The menu_id query parameter is required to list items.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$model = self::CMS_MENU_ITEM_FQCN;

		$items = $model::query()
			->where( 'menu_id', $menuId )
			->orderByRaw( 'COALESCE(parent_id, 0)' )
			->orderBy( 'position' )
			->orderBy( 'id' )
			->get();

		return response()->json(
			$items->map( fn ( $item ) => $this->itemToShape( $item ) )->all(),
		);
	}

	/**
	 * GET `/visual-editor/api/menu-items/{id}` — single item.
	 *
	 * @since 1.0.0
	 */
	public function show( int|string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$item = $this->findItem( $id );

		if ( null === $item ) {
			return response()->json( [ 'message' => 'Menu item not found.' ], Response::HTTP_NOT_FOUND );
		}

		return response()->json( $this->itemToShape( $item ) );
	}

	/**
	 * POST `/visual-editor/api/menu-items` — create an item.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreMenuItemRequest $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$validated         = $request->validated();
		$validated['type'] = $validated['type'] ?? 'link';

		$model = self::CMS_MENU_ITEM_FQCN;

		/** @var object $item */
		$item = $model::create( $validated );

		return response()->json( $this->itemToShape( $item ), Response::HTTP_CREATED );
	}

	/**
	 * PUT `/visual-editor/api/menu-items/{id}` — update an item.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateMenuItemRequest $request, int|string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$item = $this->findItem( $id );

		if ( null === $item ) {
			return response()->json( [ 'message' => 'Menu item not found.' ], Response::HTTP_NOT_FOUND );
		}

		$item->update( $request->validated() );

		return response()->json( $this->itemToShape( $item->fresh() ) );
	}

	/**
	 * DELETE `/visual-editor/api/menu-items/{id}` — delete an item.
	 *
	 * @since 1.0.0
	 */
	public function destroy( int|string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$item = $this->findItem( $id );

		if ( null === $item ) {
			return response()->json( [ 'message' => 'Menu item not found.' ], Response::HTTP_NOT_FOUND );
		}

		$item->delete();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * @since 1.0.0
	 */
	protected function findItem( int|string $id ): ?object
	{
		if ( ! is_numeric( $id ) ) {
			return null;
		}

		$model = self::CMS_MENU_ITEM_FQCN;

		return $model::query()->whereKey( (int) $id )->first();
	}

	/**
	 * Project a cms-framework `MenuItem` into the WP REST
	 * `wp_navigation_link` envelope. Mirrors
	 * {@see MenuItemAdapter::toArray()} but reads directly from a model
	 * rather than from a `ResolvedMenu::$items` projection.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function itemToShape( object $item ): array
	{
		$label = (string) ( $item->label ?? '' );

		return [
			'id'          => (int) $item->id,
			'menus'       => (int) ( $item->menu_id ?? 0 ),
			'parent'      => (int) ( $item->parent_id ?? 0 ),
			'position'    => (int) ( $item->position ?? 0 ),
			'type'        => (string) ( $item->type ?? 'link' ),
			'title'       => [
				'rendered' => $label,
				'raw'      => $label,
			],
			'url'         => (string) ( $item->url ?? '' ),
			'target'      => (string) ( $item->target ?? '' ),
			'classes'     => $this->stringList( $item->classes ?? null ),
			'xfn'         => $this->stringList( $item->rel ?? null ),
			'description' => (string) ( $item->description ?? '' ),
			'object'      => null !== $item->object_type && '' !== $item->object_type ? (string) $item->object_type : null,
			'object_id'   => null !== $item->object_id ? (int) $item->object_id : null,
			'kind'        => null !== $item->kind && '' !== $item->kind ? (string) $item->kind : null,
		];
	}

	/**
	 * Normalize a comma-separated string into a list of non-empty strings.
	 * Mirrors {@see MenuItemAdapter::stringList()} for the model-source
	 * column shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function stringList( mixed $value ): array
	{
		if ( is_string( $value ) && '' !== $value ) {
			$parts = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

			return false === $parts ? [] : array_values( $parts );
		}

		return [];
	}

	/**
	 * @since 1.0.0
	 */
	protected function cmsFrameworkAvailable(): bool
	{
		if ( ! class_exists( self::CMS_MENU_ITEM_FQCN ) ) {
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
}
