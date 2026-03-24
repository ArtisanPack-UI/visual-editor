<?php

/**
 * Listing Table Blade Component.
 *
 * A reusable table component for rendering listing pages in table view.
 * Supports sortable columns, row selection, and per-row action slots.
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
 * Table component for site editor listing pages.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ListingTable extends Component
{
	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $columns   Column definitions with key, label, sortable flag.
	 * @param array<int, mixed>                $rows      The data rows to display.
	 * @param string                           $sortField The currently sorted column key.
	 * @param string                           $sortDirection The current sort direction (asc/desc).
	 * @param array<int, int|string>           $selected  IDs of currently selected rows.
	 * @param string                           $type      The listing type (template, part, pattern).
	 */
	public function __construct(
		public array $columns = [],
		public array $rows = [],
		public string $sortField = 'name',
		public string $sortDirection = 'asc',
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
		return view( 'visual-editor::components.listing-table' );
	}
}
