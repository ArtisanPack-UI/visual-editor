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
use ArtisanPackUI\VisualEditor\SiteEditor\MenuItemBlockBridge;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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
		// Eager-load `items` — `menuToShape()` projects them into
		// `content.blocks` (#440), and without the eager load the
		// list endpoint would N+1 one items query per menu.
		$query = $model::query()->with( 'items' )->orderBy( 'id' );

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

		$attributes = $this->modelAttributesFromRequest( $request->validated(), forCreate: true );

		if ( ! array_key_exists( 'theme', $attributes ) || '' === (string) $attributes['theme'] ) {
			return response()->json( [
				'message' => 'A theme is required to create a menu.',
				'errors'  => [ 'theme' => [ 'The theme field is required when no theme is active.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		if ( ! array_key_exists( 'name', $attributes ) || '' === (string) $attributes['name'] ) {
			return response()->json( [
				'message' => 'A name is required to create a menu.',
				'errors'  => [ 'name' => [ 'The name (or title) field is required.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		try {
			/** @var object $menu */
			$menu = $model::create( $attributes );
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

		$validated = $request->validated();
		$blocks    = array_key_exists( 'content', $validated )
			? $this->resolveContentBlocks( $validated['content'] )
			: null;

		try {
			DB::transaction( function () use ( $menu, $validated, $blocks ): void {
				$menu->update( $this->modelAttributesFromRequest( $validated ) );

				// When the editor sends a navigation tree, replace the
				// menu's items wholesale. Gated on `$blocks !== null`
				// so a partial update (rename, theme change, …) leaves
				// items untouched. Inside the same transaction as the
				// attribute update so a failed item rebuild rolls the
				// whole save back.
				//
				// `$blocks` is resolved up front from one of three
				// shapes (Keystone #48):
				//   - `content` as a string → parsed via rawToBlocks
				//   - `content.blocks` as array → used directly (#440)
				//   - `content.raw` as string → parsed via rawToBlocks
				// `null` here means "key was omitted from the
				// request" — leave items alone. An authored
				// `content: null` / `""` resolves to `[]` (wipe).
				if ( is_array( $blocks ) ) {
					$this->replaceMenuItems( $menu, $blocks );
				}
			} );
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
	 * Normalize an authored `content` value into a `core/navigation-*`
	 * block tree (Keystone #48). Always returns an array; the caller
	 * gates on whether the `content` key was *present* in the request
	 * (so a partial update that omits `content` entirely leaves the
	 * existing items in place — distinct from an authored
	 * `content: null` / `content: ""` which wipes them).
	 *
	 * Three accepted input shapes:
	 *   - `null` or empty string → `[]` (wipe items)
	 *   - String of WP block-comment markup → parsed via
	 *     {@see MenuItemBlockBridge::rawToBlocks}
	 *   - Array `{ raw?, blocks? }` → `blocks` wins when both are
	 *     set (lossless); `raw` is parsed otherwise
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function resolveContentBlocks( mixed $content ): array
	{
		if ( null === $content ) {
			// `content: null` (from `ConvertEmptyStringsToNull` on
			// `content: ""`) means "no items" — wipe them.
			return [];
		}

		if ( is_string( $content ) ) {
			$trimmed = trim( $content );

			return '' === $trimmed ? [] : ( new MenuItemBlockBridge() )->rawToBlocks( $trimmed );
		}

		if ( is_array( $content ) ) {
			if ( is_array( $content['blocks'] ?? null ) ) {
				return $content['blocks'];
			}

			if ( is_string( $content['raw'] ?? null ) ) {
				$trimmed = trim( $content['raw'] );

				return '' === $trimmed ? [] : ( new MenuItemBlockBridge() )->rawToBlocks( $trimmed );
			}

			// Empty `content: {}` object → no items.
			return [];
		}

		return [];
	}

	/**
	 * Replace a menu's items from a `core/navigation-*` block tree.
	 *
	 * The block wire shape carries no stable per-item id, so a precise
	 * insert / update / delete diff against the existing rows isn't
	 * possible — a delete-all + rebuild is the correct equivalent and
	 * satisfies every round-trip the editor needs (add, remove,
	 * reorder, re-nest). The caller runs this inside a transaction.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>  $blocks
	 */
	protected function replaceMenuItems( object $menu, array $blocks ): void
	{
		// Bulk delete — `reorder()` strips the `items()` relation's
		// `ORDER BY` so the DELETE is portable (SQLite rejects
		// `DELETE … ORDER BY` without a LIMIT). Deleting every row for
		// the menu in one query means no orphans regardless of the
		// per-row `deleting` cascade.
		$menu->items()->reorder()->delete();

		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( $blocks );

		$this->insertItemSpecs( $menu, $specs, null );
	}

	/**
	 * Depth-first insert of the nested row specs produced by
	 * {@see MenuItemBlockBridge::blocksToItemSpecs()}. `position` is the
	 * sibling index within each parent; `parent_id` is the just-inserted
	 * parent row's id.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array{attributes: array<string, mixed>, children: array<int, mixed>}>  $specs
	 */
	protected function insertItemSpecs( object $menu, array $specs, ?int $parentId ): void
	{
		$position = 0;

		foreach ( $specs as $spec ) {
			/** @var object $row */
			$row = $menu->items()->create( array_merge( $spec['attributes'], [
				'parent_id' => $parentId,
				'position'  => $position,
			] ) );

			$position++;

			if ( [] !== $spec['children'] ) {
				$this->insertItemSpecs( $menu, $spec['children'], (int) $row->id );
			}
		}
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
			// `content` mirrors the WP REST `wp_navigation` shape. #440
			// projects `menu_items` rows into a `core/navigation-link` /
			// `core/navigation-submenu` block tree under `content.blocks`.
			// Keystone #48: ship `content.raw` as the serialized block-
			// comment form too — Gutenberg's `core/navigation` block
			// reads `content.raw` (not `content.blocks`) when deciding
			// which inner blocks to render, so without this the picker
			// shows an empty list even when the menu has items. The
			// backend re-derives both from `menu_items` on every read;
			// items remain individually addressable via /menu-items.
			'content'        => ( function () use ( $menu ): array {
				$bridge = new MenuItemBlockBridge();
				$blocks = $bridge->itemsToBlocks( $menu->items );

				return [
					'raw'    => $bridge->blocksToRaw( $blocks ),
					'blocks' => $blocks,
				];
			} )(),
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
	 * Translate the WP-shape validated input into cms-framework `Menu`
	 * model attributes. Maps `title` (WP REST shape — what the editor's
	 * create-menu dialog sends) onto `name` (model column). On create,
	 * falls back to cms-framework's active theme when the payload
	 * doesn't carry one. Mirrors the helpers in TemplateController and
	 * TemplatePartController (#438).
	 *
	 * The active-theme fallback is create-only. On a partial `update()`
	 * the payload routinely omits `theme` (e.g. a rename sends only
	 * `title`); injecting the active theme there would silently
	 * re-home the menu to a different theme. An explicit `theme` in the
	 * payload still flows through on both paths — only the *inferred*
	 * fallback is gated.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated
	 * @param  bool  $forCreate  True for store(), false for update().
	 *
	 * @return array<string, mixed>
	 */
	protected function modelAttributesFromRequest( array $validated, bool $forCreate = false ): array
	{
		$attributes = [];

		foreach ( [ 'theme', 'slug', 'name', 'description', 'auto_add_pages' ] as $field ) {
			if ( array_key_exists( $field, $validated ) ) {
				$attributes[ $field ] = $validated[ $field ];
			}
		}

		// `title` (WP REST shape) wins over `name` (model shape) when
		// both are present, since the editor consistently sends `title`
		// and an explicit body `name` would only appear from a REST
		// client choosing to use the model field directly.
		if ( array_key_exists( 'title', $validated ) ) {
			$attributes['name'] = $validated['title'];
		}

		if ( $forCreate
			&& ( ! array_key_exists( 'theme', $attributes ) || '' === (string) ( $attributes['theme'] ?? '' ) )
		) {
			$active = $this->activeThemeSlug();

			if ( null !== $active ) {
				$attributes['theme'] = $active;
			}
		}

		return $attributes;
	}

	/**
	 * Resolve the active theme slug through cms-framework's `ThemeManager`
	 * when available. Mirrors {@see TemplateController::activeThemeSlug()}.
	 *
	 * @since 1.0.0
	 */
	protected function activeThemeSlug(): ?string
	{
		$themeManagerFqcn = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

		if ( ! class_exists( $themeManagerFqcn ) || ! app()->bound( $themeManagerFqcn ) ) {
			return null;
		}

		$theme = app( $themeManagerFqcn )->getActiveTheme();

		if ( ! is_array( $theme ) || empty( $theme['slug'] ) || ! is_string( $theme['slug'] ) ) {
			return null;
		}

		return $theme['slug'];
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
