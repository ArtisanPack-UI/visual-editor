<?php

/**
 * MenuLocationResolver service.
 *
 * Resolves theme-exposed menu-location slugs to a concrete
 * {@see VisualEditorNavigation} record. The V1 commitment is
 * configuration-driven menu locations (per §8 of the V1 plan doc):
 * host apps declare the available slugs in
 * `config/artisanpack/visual-editor.php` under `navigation.locations`,
 * and each location optionally names the nav record (`primary_id`)
 * the theme should render for that slot.
 *
 * When a location has no assignment, points at a missing record, or
 * points at a record with an empty block tree, the resolver falls
 * back to the first published nav ordered by `menu_order`. If no
 * published nav exists at all, `forLocation` returns `null` so the
 * `core/navigation` block can render an empty state instead of
 * 500-ing the page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class MenuLocationResolver
{
	public function __construct( protected ConfigRepository $config )
	{
	}

	/**
	 * Returns the configured menu-location entries.
	 *
	 * Each entry is shaped as
	 * `{ slug: string, label: string, primary_id: ?int }` keyed by slug.
	 * Entries with a non-string slug/label are filtered out so the site
	 * editor UI always gets a sane list to render.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{slug: string, label: string, primary_id: ?int}>
	 */
	public function locations(): array
	{
		$raw = $this->config->get( 'artisanpack.visual-editor.navigation.locations', [] );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$locations = [];

		foreach ( $raw as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug  = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? $entry['slug'] : (string) $key;
			$label = isset( $entry['label'] ) && is_string( $entry['label'] ) ? $entry['label'] : $slug;

			if ( '' === $slug ) {
				continue;
			}

			$primaryId = null;
			if ( isset( $entry['primary_id'] ) && is_numeric( $entry['primary_id'] ) ) {
				$primaryId = (int) $entry['primary_id'];
			}

			$locations[ $slug ] = [
				'slug'       => $slug,
				'label'      => $label,
				'primary_id' => $primaryId,
			];
		}

		return $locations;
	}

	/**
	 * Resolves a menu-location slug to the navigation record the theme
	 * should render for that slot.
	 *
	 * Resolution order:
	 *   1. The first published nav with `location = $slug` ordered by
	 *      `menu_order`. This is what the D4 site editor writes to;
	 *      always preferred over config so an admin's UI assignment
	 *      wins against a stale config entry.
	 *   2. The configured `primary_id` (when present and the record
	 *      exists and has a non-empty block tree). Lets a host app
	 *      pin a location without exposing it to the editor.
	 *   3. The first published nav ordered by `menu_order`.
	 *   4. `null` when no published navs exist.
	 *
	 * An unknown slug still falls through to the published-nav fallback,
	 * because a freshly-installed host app may have navs seeded before
	 * it has declared any locations in config.
	 *
	 * @since 1.0.0
	 */
	public function forLocation( string $slug ): ?VisualEditorNavigation
	{
		if ( '' !== $slug ) {
			$assigned = VisualEditorNavigation::query()->forLocation( $slug )->first();

			if ( null !== $assigned && ! $assigned->isEmpty() ) {
				return $assigned;
			}
		}

		$locations = $this->locations();

		$primary = null;
		if ( isset( $locations[ $slug ]['primary_id'] ) ) {
			$primary = $locations[ $slug ]['primary_id'];
		}

		if ( null !== $primary ) {
			$record = VisualEditorNavigation::query()->find( $primary );

			if ( null !== $record && ! $record->isEmpty() ) {
				return $record;
			}
		}

		return $this->fallback();
	}

	/**
	 * Returns the navigation record currently effective for each
	 * configured location, keyed by location slug.
	 *
	 * Each entry is the result of `forLocation($slug)` — see that
	 * method for the resolution chain (DB assignment → configured
	 * `primary_id` → first-published fallback → `null`). A null entry
	 * therefore means "no DB assignment, no config primary_id, AND no
	 * published nav exists at all" — not the narrower "no direct
	 * assignment" condition the locations-panel UI uses to render its
	 * fallback hint. The panel reads `is_fallback` from
	 * {@see \ArtisanPackUI\VisualEditor\Http\Controllers\MenuLocationsController::index()}
	 * for that distinction.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, ?VisualEditorNavigation>  Keyed by location slug.
	 */
	public function effectiveAssignments(): array
	{
		$assignments = [];

		foreach ( array_keys( $this->locations() ) as $slug ) {
			$assignments[ $slug ] = $this->forLocation( $slug );
		}

		return $assignments;
	}

	/**
	 * Returns the first published nav ordered by `menu_order`, or null
	 * when the database has no published navs.
	 *
	 * Exposed separately so callers that already know the location is
	 * unconfigured (for example, a front-end renderer handed a slug that
	 * doesn't exist in config) can reach the fallback without paying for
	 * the location lookup.
	 *
	 * @since 1.0.0
	 */
	public function fallback(): ?VisualEditorNavigation
	{
		return VisualEditorNavigation::query()->published()->first();
	}
}
