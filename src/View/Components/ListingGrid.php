<?php

/**
 * Listing Grid Blade Component.
 *
 * A reusable card grid component for rendering listing pages in grid view.
 * Displays items as visual cards with name, status badge, and hover actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Grid component for site editor listing pages.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ListingGrid extends Component
{
	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, mixed>      $rows     The data rows to display as cards.
	 * @param array<int, int|string> $selected IDs of currently selected rows.
	 * @param string                 $type     The listing type (template, part, pattern).
	 */
	public function __construct(
		public array $rows = [],
		public array $selected = [],
		public string $type = 'template',
	) {
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.listing-grid' );
	}
}
