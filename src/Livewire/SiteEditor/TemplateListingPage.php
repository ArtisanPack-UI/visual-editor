<?php

/**
 * Template Listing Page Livewire Component.
 *
 * Displays a paginated, searchable, filterable listing of templates
 * in either table or card grid view. Supports sorting, bulk actions,
 * and per-row CRUD operations.
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

use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Services\TemplateManager;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Livewire component for the template listing page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class TemplateListingPage extends Component
{
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
	 * The status filter.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Url]
	public string $filterStatus = '';

	/**
	 * The type filter.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Url]
	public string $filterType = '';

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
		$allowed = [ 'name', 'type', 'status', 'updated_at' ];

		if ( ! in_array( $field, $allowed, true ) ) {
			return;
		}

		if ( $this->sortField === $field ) {
			$this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
		} else {
			$this->sortField     = $field;
			$this->sortDirection = 'asc';
		}
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
		$allItems = $this->getAllTemplates();
		$page     = max( 1, (int) request()->input( 'page', 1 ) );
		$pageIds  = $allItems->forPage( $page, $this->perPage )
			->pluck( 'id' )
			->filter()
			->values()
			->toArray();

		if ( count( $this->selected ) === count( $pageIds ) ) {
			$this->selected = [];
		} else {
			$this->selected = $pageIds;
		}
	}

	/**
	 * Duplicate a template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The template ID to duplicate.
	 *
	 * @return void
	 */
	public function duplicate( int $id ): void
	{
		$template = Template::find( $id );

		if ( null === $template ) {
			return;
		}

		$template->duplicate( $template->slug . '-' . Str::random( 6 ) );

		$this->dispatch( 've-template-duplicated' );
	}

	/**
	 * Delete a template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The template ID to delete.
	 *
	 * @return void
	 */
	public function delete( int $id ): void
	{
		$template = Template::find( $id );

		if ( null === $template || $template->is_locked ) {
			return;
		}

		$template->delete();

		$this->selected = array_values( array_diff( $this->selected, [ $id ] ) );
		$this->dispatch( 've-template-deleted' );
	}

	/**
	 * Toggle the lock status of a template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The template ID.
	 *
	 * @return void
	 */
	public function toggleLock( int $id ): void
	{
		$template = Template::find( $id );

		if ( null === $template ) {
			return;
		}

		$template->update( [ 'is_locked' => ! $template->is_locked ] );
	}

	/**
	 * Bulk delete selected templates.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function bulkDelete(): void
	{
		$count = Template::whereIn( 'id', $this->selected )
			->where( 'is_locked', false )
			->delete();

		$this->selected = [];
		$this->dispatch( 've-templates-bulk-deleted', count: $count );
	}

	/**
	 * Bulk change status of selected templates.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status The new status value.
	 *
	 * @return void
	 */
	public function bulkChangeStatus( string $status ): void
	{
		if ( ! in_array( $status, [ 'active', 'draft' ], true ) ) {
			return;
		}

		$count = Template::whereIn( 'id', $this->selected )
			->where( 'is_locked', false )
			->update( [ 'status' => $status ] );

		$this->selected = [];
		$this->dispatch( 've-templates-bulk-status-changed', count: $count );
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
			[ 'key' => 'name', 'label' => __( 'visual-editor::ve.template_listing_column_name' ), 'sortable' => true ],
			[ 'key' => 'type', 'label' => __( 'visual-editor::ve.template_listing_column_type' ), 'sortable' => true ],
			[ 'key' => 'status', 'label' => __( 'visual-editor::ve.template_listing_column_status' ), 'sortable' => true ],
			[ 'key' => 'updated_at', 'label' => __( 'visual-editor::ve.template_listing_column_updated' ), 'sortable' => true ],
		];

		return veApplyFilters( 've.listing.columns', $columns, 'template' );
	}

	/**
	 * Render the template listing page.
	 *
	 * Merges in-memory registered templates with database templates via the
	 * TemplateManager so that programmatically registered templates
	 * appear in the listing alongside user-created ones.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		$items = $this->getAllTemplates();

		$page      = max( 1, (int) request()->input( 'page', 1 ) );
		$total     = $items->count();
		$templates = new LengthAwarePaginator(
			$items->forPage( $page, $this->perPage )->values(),
			$total,
			$this->perPage,
			$page,
			[ 'path' => request()->url(), 'query' => request()->query() ],
		);

		return view( 'visual-editor::livewire.site-editor.template-listing', [
			'templates' => $templates,
			'columns'   => $this->getColumns(),
		] );
	}

	/**
	 * Get all templates from the manager, then filter and sort.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	protected function getAllTemplates(): Collection
	{
		$manager = app( TemplateManager::class );

		$items = collect( $manager->all() )->map( function ( array $item ): object {
			if ( ! isset( $item['id'] ) ) {
				$item['id'] = 'registered-' . ( $item['slug'] ?? '' );
			}

			if ( ! isset( $item['status'] ) ) {
				$item['status'] = 'active';
			}

			if ( ! isset( $item['is_locked'] ) ) {
				$item['is_locked'] = false;
			}

			if ( ! isset( $item['updated_at'] ) ) {
				$item['updated_at'] = null;
			}

			return (object) $item;
		} );

		if ( '' !== $this->search ) {
			$search = mb_strtolower( $this->search );
			$items  = $items->filter( fn ( object $t ): bool => str_contains( mb_strtolower( $t->name ?? '' ), $search ) );
		}

		if ( '' !== $this->filterStatus ) {
			$items = $items->filter( fn ( object $t ): bool => ( $t->status ?? 'active' ) === $this->filterStatus );
		}

		if ( '' !== $this->filterType ) {
			$items = $items->filter( fn ( object $t ): bool => ( $t->type ?? '' ) === $this->filterType );
		}

		$field = $this->sortField;
		$items = $items->sortBy( fn ( object $t ) => $t->$field ?? '', SORT_REGULAR, 'desc' === $this->sortDirection );

		return $items;
	}
}
