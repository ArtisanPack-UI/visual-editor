<?php

/**
 * Walk a block tree and stamp `attributes.ref` on every
 * `core/navigation` block that ships with `__unstableLocation` set
 * but no `ref` of its own (Keystone #48).
 *
 * Themes typically author navigation blocks against a theme-declared
 * location (`primary`, `footer`, …) rather than a brittle hard-coded
 * menu id — the seed convention is `{"__unstableLocation": "primary"}`.
 * Gutenberg's modern `core/navigation` block, though, no longer
 * resolves `__unstableLocation` to a menu id at runtime; the
 * attribute is vestigial and the block bails to "no menu selected"
 * unless `ref` is populated.
 *
 * This resolver bridges the gap. On every template / template-part
 * read the adapter walks the block tree once and stamps the
 * resolved `ref` so the editor's `useEntityBlockEditor` fetches the
 * matching `wp_navigation` entity and lights up the picker.
 *
 * The mapping (location → menu id) is loaded lazily per instance
 * and cached so a tree with multiple nav blocks resolves in one
 * query rather than N.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor;

use Illuminate\Support\Str;

class NavigationBlockRefResolver
{
	protected const NAV_BLOCK_NAME = 'core/navigation';

	protected const ASSIGNMENT_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\MenuLocationAssignment';

	protected const MENU_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\Menu';

	/**
	 * Per-instance cache. Keys are `{theme}|{location}` so a render
	 * across multiple themes (vanishingly rare in V1) doesn't collide.
	 * Values are the resolved menu id, or `null` when the location is
	 * unassigned.
	 *
	 * @var array<string, int|null>
	 */
	protected array $cache = [];

	/**
	 * Per-instance cache of menu-item projections, keyed by menu id so
	 * a tree with multiple nav blocks pointed at the same menu doesn't
	 * re-fetch / re-project for each one.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	protected array $blocksByMenuId = [];

	/**
	 * Walk a block tree and return a new tree with resolved refs.
	 * Untouched blocks pass through by reference (PHP copy-on-write
	 * gives us tree sharing where nothing changed), so the projection
	 * is cheap for trees that don't contain nav blocks.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, mixed>  $blocks
	 * @param  string             $theme  Active theme slug; pinned per
	 *                                    template / template-part read.
	 *
	 * @return array<int, mixed>
	 */
	public function resolve( array $blocks, string $theme ): array
	{
		return array_map(
			fn ( $block ) => is_array( $block ) ? $this->resolveBlock( $block, $theme ) : $block,
			$blocks,
		);
	}

	/**
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveBlock( array $block, string $theme ): array
	{
		$attributes  = is_array( $block['attributes'] ?? null ) ? $block['attributes'] : [];
		$innerBlocks = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];

		$resolvedAttributes = $attributes;
		$resolvedInner      = [] === $innerBlocks ? $innerBlocks : $this->resolve( $innerBlocks, $theme );

		if ( self::NAV_BLOCK_NAME === ( $block['name'] ?? null ) ) {
			$resolvedAttributes = $this->stampNavRef( $attributes, $theme );

			// Stamp the menu's items as the nav block's innerBlocks
			// when we resolved a `ref` (or the author had one) AND
			// the block tree is currently empty. Gutenberg's nav
			// block reads its children from the block tree (via the
			// block-editor data store), not from any entity record —
			// so without populated innerBlocks the canvas renders an
			// empty nav block + the inspector shows "This Navigation
			// Menu is empty." even when the menu has rows in
			// `menu_items` (Keystone #48).
			$refId = is_numeric( $resolvedAttributes['ref'] ?? null )
				? (int) $resolvedAttributes['ref']
				: null;

			if ( null !== $refId && [] === $resolvedInner ) {
				$resolvedInner = $this->menuItemsAsBlocks( $refId );
			}
		}

		if ( $resolvedAttributes === $attributes && $resolvedInner === $innerBlocks ) {
			return $block;
		}

		return [
			...$block,
			'attributes'  => $resolvedAttributes,
			'innerBlocks' => $resolvedInner,
		];
	}

	/**
	 * Project the menu's `menu_items` rows into the nav-block tree
	 * shape (`core/navigation-link` / `core/navigation-submenu`) the
	 * editor expects. Reuses {@see MenuItemBlockBridge::itemsToBlocks}
	 * so the projection stays in lockstep with the front-end render
	 * path.
	 *
	 * Cached per-menu-id so a tree with multiple nav blocks pointed
	 * at the same menu only fetches once. Returns an empty array
	 * when cms-framework isn't installed or the menu isn't found.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function menuItemsAsBlocks( int $menuId ): array
	{
		if ( array_key_exists( $menuId, $this->blocksByMenuId ) ) {
			return $this->blocksByMenuId[ $menuId ];
		}

		if ( ! class_exists( self::MENU_FQCN ) ) {
			$this->blocksByMenuId[ $menuId ] = [];

			return [];
		}

		$model = self::MENU_FQCN;
		$menu  = $model::query()->with( 'items' )->find( $menuId );

		if ( null === $menu ) {
			$this->blocksByMenuId[ $menuId ] = [];

			return [];
		}

		$blocks = $this->stampMetadata(
			( new MenuItemBlockBridge() )->itemsToBlocks( $menu->items ),
		);

		$this->blocksByMenuId[ $menuId ] = $blocks;

		return $blocks;
	}

	/**
	 * Stamp `clientId` + `isValid` on every block in the projected
	 * tree. The other blocks in a template-part response carry these
	 * because cms-framework's seed applier stamps them at write time;
	 * our runtime projection has to mirror that or Gutenberg's
	 * block-editor data store rejects the malformed nodes during
	 * receive and the entire tree fails to mount silently (no
	 * console error, just an empty canvas). Keystone #48.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stampMetadata( array $blocks ): array
	{
		return array_map(
			fn ( array $block ): array => [
				...$block,
				'clientId'    => (string) ( $block['clientId'] ?? Str::uuid() ),
				'isValid'     => true,
				'innerBlocks' => $this->stampMetadata(
					is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [],
				),
			],
			$blocks,
		);
	}

	/**
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampNavRef( array $attributes, string $theme ): array
	{
		// Honor an existing `ref` — the author already chose a menu
		// explicitly, so resolution shouldn't override it. Still
		// strip `__unstableLocation` if it's sitting beside the
		// `ref`: Gutenberg's current `core/navigation` block prefers
		// the legacy location attribute over `ref` when both are
		// present and falls back to its own (broken in our
		// environment) location-lookup pipeline. Leaving it in place
		// kept blocks authored with both attributes on the wrong
		// resolution path and never hydrated from the explicit menu
		// the author chose.
		if ( isset( $attributes['ref'] ) && is_numeric( $attributes['ref'] ) ) {
			$resolved = $attributes;
			unset( $resolved['__unstableLocation'] );

			return $resolved;
		}

		$location = $attributes['__unstableLocation'] ?? null;

		if ( ! is_string( $location ) || '' === $location ) {
			return $attributes;
		}

		$menuId = $this->lookupMenuIdForLocation( $theme, $location );

		if ( null === $menuId ) {
			return $attributes;
		}

		// Strip `__unstableLocation` after stamping `ref`. Gutenberg's
		// current `core/navigation` block prefers `__unstableLocation`
		// when both attributes are set and falls back to its own
		// (broken in our environment) location-lookup pipeline; leaving
		// the attribute in place sent the picker down that path and
		// kept `useEntityBlockEditor` being called with `id: null`
		// despite the `ref` sitting right next to it. Removing
		// `__unstableLocation` forces the block to read `ref`
		// directly — the path our shim hydrates from.
		$resolved = [
			...$attributes,
			'ref' => $menuId,
		];

		unset( $resolved['__unstableLocation'] );

		return $resolved;
	}

	/**
	 * Resolve `(theme, location)` to a menu id via cms-framework's
	 * `MenuLocationAssignment`. Returns `null` when the location is
	 * unassigned, when cms-framework isn't installed, or when the
	 * model's container binding hasn't been registered.
	 *
	 * @since 1.1.0
	 */
	protected function lookupMenuIdForLocation( string $theme, string $location ): ?int
	{
		$cacheKey = $theme . '|' . $location;

		if ( array_key_exists( $cacheKey, $this->cache ) ) {
			return $this->cache[ $cacheKey ];
		}

		$id = null;

		if ( class_exists( self::ASSIGNMENT_FQCN ) ) {
			$model      = self::ASSIGNMENT_FQCN;
			$assignment = $model::query()
				->where( 'theme', $theme )
				->where( 'location', $location )
				->first();

			if ( null !== $assignment && null !== ( $assignment->menu_id ?? null ) ) {
				$id = (int) $assignment->menu_id;
			}
		}

		$this->cache[ $cacheKey ] = $id;

		return $id;
	}
}
