<?php

/**
 * Pattern Listing Page Livewire Component.
 *
 * Displays a paginated, searchable, filterable listing of patterns
 * in either table or card grid view. Supports sorting, bulk actions,
 * category filtering, and per-row CRUD operations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\SiteEditor;

use ArtisanPackUI\VisualEditor\Contracts\SiteEditorListing;
use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Livewire component for the pattern listing page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class PatternListingPage extends Component implements SiteEditorListing
{
	use WithPagination;

	/**
	 * The search query string.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Url]
	public string $search = '';

	/**
	 * The current sort field.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $sortField = 'name';

	/**
	 * The current sort direction.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $sortDirection = 'asc';

	/**
	 * The category filter.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Url]
	public string $filterCategory = '';

	/**
	 * The current view mode (table or grid).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $viewMode = 'table';

	/**
	 * IDs of selected rows for bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, int>
	 */
	public array $selected = [];

	/**
	 * Number of items per page.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $perPage = 15;

	/**
	 * Authorize access when the component mounts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.permission', 'visual-editor.access-site-editor' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}
	}

	/**
	 * Sort by the given field, toggling direction if already active.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field The column key to sort by.
	 *
	 * @return void
	 */
	public function sort( string $field ): void
	{
		$allowed = [ 'name', 'category', 'updated_at' ];

		if ( ! in_array( $field, $allowed, true ) ) {
			return;
		}

		if ( $this->sortField === $field ) {
			$this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
		} else {
			$this->sortField     = $field;
			$this->sortDirection = 'asc';
		}

		$this->resetPage();
	}

	/**
	 * Toggle the view mode between table and grid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mode The view mode to set.
	 *
	 * @return void
	 */
	public function setViewMode( string $mode ): void
	{
		if ( in_array( $mode, [ 'table', 'grid' ], true ) ) {
			$this->viewMode = $mode;
		}
	}

	/**
	 * Toggle select all rows on the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function toggleSelectAll(): void
	{
		$pageIds = $this->getQuery()
			->paginate( $this->perPage )
			->pluck( 'id' )
			->toArray();

		if ( count( $this->selected ) === count( $pageIds ) ) {
			$this->selected = [];
		} else {
			$this->selected = $pageIds;
		}
	}

	/**
	 * Duplicate a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The pattern ID to duplicate.
	 *
	 * @return void
	 */
	public function duplicate( int $id ): void
	{
		$pattern = Pattern::find( $id );

		if ( null === $pattern ) {
			return;
		}

		Pattern::create( [
			'name'        => $pattern->name . ' (' . __( 'Copy' ) . ')',
			'slug'        => $pattern->slug . '-' . Str::random( 6 ),
			'blocks'      => $pattern->blocks,
			'category'    => $pattern->category,
			'description' => $pattern->description,
			'keywords'    => $pattern->keywords,
			'status'      => $pattern->status ?? 'draft',
			'is_synced'   => $pattern->is_synced ?? false,
			'user_id'     => $pattern->user_id,
		] );

		$this->dispatch( 've-pattern-duplicated' );
	}

	/**
	 * Delete a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The pattern ID to delete.
	 *
	 * @return void
	 */
	public function delete( int $id ): void
	{
		$pattern = Pattern::find( $id );

		if ( null === $pattern ) {
			return;
		}

		$pattern->delete();

		$this->selected = array_values( array_diff( $this->selected, [ $id ] ) );
		$this->dispatch( 've-pattern-deleted' );
	}

	/**
	 * Bulk delete selected patterns.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function bulkDelete(): void
	{
		$count = Pattern::whereIn( 'id', $this->selected )->delete();

		$this->selected = [];
		$this->dispatch( 've-patterns-bulk-deleted', count: $count );
	}

	/**
	 * Get the column definitions for the table view.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getColumns(): array
	{
		$columns = [
			[ 'key' => 'name', 'label' => __( 'visual-editor::ve.pattern_listing_column_name' ), 'sortable' => true ],
			[ 'key' => 'category', 'label' => __( 'visual-editor::ve.pattern_listing_column_category' ), 'sortable' => true ],
			[ 'key' => 'updated_at', 'label' => __( 'visual-editor::ve.pattern_listing_column_updated' ), 'sortable' => true ],
		];

		return veApplyFilters( 've.listing.columns', $columns, 'pattern' );
	}

	/**
	 * Get the row actions for a given item.
	 *
	 * Default actions (duplicate, lock, delete) are built-in to the view.
	 * This method returns additional custom actions added via the
	 * `ve.listing.actions` filter hook.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item The listing row item.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getRowActions( object $item ): array
	{
		return veApplyFilters( 've.listing.actions', [], 'pattern', $item );
	}

	/**
	 * Reset page when search changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedSearch(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset page when category filter changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedFilterCategory(): void
	{
		$this->resetPage();
	}

	/**
	 * Render the pattern listing page.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		$categories = Pattern::query()
			->whereNotNull( 'category' )
			->distinct()
			->pluck( 'category' )
			->sort()
			->values();

		return view( 'visual-editor::livewire.site-editor.pattern-listing', [
			'patterns'   => $this->getQuery()->paginate( $this->perPage ),
			'columns'    => $this->getColumns(),
			'categories' => $categories,
		] );
	}

	/**
	 * Build the query for fetching patterns.
	 *
	 * @since 1.0.0
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	protected function getQuery(): \Illuminate\Database\Eloquent\Builder
	{
		$query = Pattern::query();

		if ( '' !== $this->search ) {
			$query->where( 'name', 'like', '%' . $this->search . '%' );
		}

		if ( '' !== $this->filterCategory ) {
			$query->where( 'category', $this->filterCategory );
		}

		$query->orderBy( $this->sortField, $this->sortDirection );

		return $query;
	}
}
