<?php

/**
 * Menu locations controller.
 *
 * Read-only surface for the configured menu-location slugs, paired with
 * the navigation record currently assigned to each. The site editor's
 * Navigation section calls `index` once when the section mounts to
 * render its locations panel; subsequent assignment writes go through
 * the regular `PUT /navigation/{id}` surface (the `location` field on
 * the navigation record is the source of truth).
 *
 * Locations themselves are config-only for V1 per plan §8 — there is
 * no create / update / destroy here on purpose. 1.1+ may revisit.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Services\MenuLocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class MenuLocationsController extends Controller
{
	public function __construct( protected MenuLocationResolver $resolver ) {}

	/**
	 * Returns the configured menu locations, the menu currently
	 * resolved for each, and whether that menu was resolved through a
	 * direct DB assignment (the editor's UI-driven path) or via the
	 * config-driven fallback chain.
	 *
	 * Response shape:
	 *
	 *   ```
	 *   {
	 *     "data": [
	 *       {
	 *         "slug": "primary",
	 *         "label": "Primary Menu",
	 *         "menu": { "id": 12, "title": "…", "slug": "main" } | null,
	 *         "is_fallback": true | false
	 *       }
	 *     ]
	 *   }
	 *   ```
	 *
	 * `is_fallback: true` tells the panel to render the "no direct
	 * assignment — falling back to X" hint instead of treating the
	 * resolved menu as authoritative.
	 *
	 * @since 1.0.0
	 */
	public function index(): JsonResponse
	{
		Gate::authorize( 'viewAny', VisualEditorNavigation::class );

		$locations = $this->resolver->locations();
		$rows      = [];

		foreach ( $locations as $entry ) {
			$slug = $entry['slug'];

			// Direct assignment — a published nav whose `location`
			// column matches the slug. The same query the resolver
			// runs first, but we also need to know whether it
			// matched so the UI can flag fallback assignments.
			$assigned = VisualEditorNavigation::query()
				->forLocation( $slug )
				->first();

			$resolved = null !== $assigned && ! $assigned->isEmpty()
				? $assigned
				: $this->resolver->forLocation( $slug );

			$rows[] = [
				'slug'        => $slug,
				'label'       => $entry['label'],
				'menu'        => null === $resolved ? null : [
					'id'    => $resolved->getKey(),
					'slug'  => (string) $resolved->slug,
					'title' => (string) ( $resolved->title ?? '' ),
				],
				'is_fallback' => null !== $resolved && ( null === $assigned || $assigned->isEmpty() ),
			];
		}

		return response()->json( [ 'data' => $rows ] );
	}
}
