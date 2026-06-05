<?php

/**
 * Menu locations controller.
 *
 * Read-only surface for the configured menu-location slugs paired with
 * the {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu}
 * currently assigned to each. Backs the site editor's Navigation section
 * locations panel.
 *
 * Authoritative source for both halves of the response:
 *
 *   - Location slugs + labels come from the active theme's `theme.json`
 *     `menus.locations` block (via cms-framework's `ThemeManager`).
 *   - Assignments come from cms-framework's `menu_location_assignments`
 *     table (`MenuLocationAssignment`), keyed on `(theme, location)`.
 *
 * Phase H deliberately drops the visual-editor's legacy config-driven
 * fallback chain (the old `MenuLocationResolver`'s "if nothing's
 * assigned, fall back to the first published nav" behavior) — see
 * `docs/plans/14` §4.5.1. cms-framework's model is "assigned or
 * unassigned, full stop"; consuming UI renders the empty state for the
 * unassigned case. `is_fallback` is therefore always `false` in the
 * response shape, kept for backwards compatibility with the existing
 * JS contract.
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

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MenuLocationsController extends Controller
{
	/**
	 * Returns the configured menu locations + the menu currently
	 * assigned to each.
	 *
	 * Response shape (preserved from V1 surface):
	 *
	 *   ```
	 *   {
	 *     "data": [
	 *       {
	 *         "slug": "primary",
	 *         "label": "Primary Menu",
	 *         "menu": { "id": 12, "title": "…", "slug": "main" } | null,
	 *         "is_fallback": false
	 *       }
	 *     ]
	 *   }
	 *   ```
	 *
	 * Returns an empty `data` array when cms-framework isn't installed,
	 * since this surface is a Phase H feature gated by the install
	 * check at the SPA mount layer.
	 *
	 * @since 1.0.0
	 */
	public function index(): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return response()->json( [ 'data' => [] ] );
		}

		$themeManagerClass    = '\\ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';
		$assignmentClass      = '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\MenuLocationAssignment';
		$themeManager         = app( $themeManagerClass );
		$activeTheme          = $themeManager->getActiveTheme();
		$activeSlug           = is_array( $activeTheme ) && ! empty( $activeTheme['slug'] ) ? (string) $activeTheme['slug'] : null;
		$declaredLocations    = is_array( $activeTheme['menus']['locations'] ?? null ) ? $activeTheme['menus']['locations'] : [];

		$assignmentsByLocation = [];

		if ( null !== $activeSlug ) {
			$rows = $assignmentClass::query()
				->where( 'theme', $activeSlug )
				->with( 'menu' )
				->get();

			foreach ( $rows as $row ) {
				if ( null === $row->menu ) {
					continue;
				}

				$assignmentsByLocation[ (string) $row->location ] = $row->menu;
			}
		}

		$data = [];

		foreach ( $declaredLocations as $slug => $label ) {
			$slug  = (string) $slug;
			$menu  = $assignmentsByLocation[ $slug ] ?? null;
			$data[] = [
				'slug'        => $slug,
				'label'       => (string) $label,
				'menu'        => null === $menu ? null : [
					'id'    => (int) $menu->getKey(),
					'slug'  => (string) ( $menu->slug ?? '' ),
					'title' => (string) ( $menu->name ?? $menu->title ?? '' ),
				],
				'is_fallback' => false,
			];
		}

		return response()->json( [ 'data' => $data ] );
	}

	/**
	 * Whether cms-framework's site-editor module is loaded in this app.
	 * The endpoint is a no-op without it (Phase H install gate would
	 * have already surfaced "cms-framework required" at the SPA layer,
	 * but the controller stays defensive for direct-API callers).
	 *
	 * @since 1.0.0
	 */
	protected function cmsFrameworkAvailable(): bool
	{
		return class_exists( '\\ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager' )
			&& class_exists( '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\MenuLocationAssignment' );
	}
}
