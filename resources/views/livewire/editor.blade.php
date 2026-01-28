<?php

declare( strict_types=1 );

/**
 * Visual Editor - Main Editor Shell
 *
 * The top-level page component that serves as the main container for
 * all editor functionality including the sidebar, toolbar, canvas,
 * and status bar.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Models\Content;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
	/**
	 * The content being edited.
	 *
	 * @since 1.0.0
	 *
	 * @var Content
	 */
	public Content $content;

	/**
	 * The content sections data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $sections = [];

	/**
	 * Whether the sidebar panel is open.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $sidebarOpen = true;

	/**
	 * The active sidebar tab.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $sidebarTab = 'blocks';

	/**
	 * The ID of the currently active block.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $activeBlockId = null;

	/**
	 * Whether the content has unsaved changes.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $isDirty = false;

	/**
	 * The current save status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $saveStatus = 'saved';

	/**
	 * Mount the component with the given content.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to edit.
	 *
	 * @return void
	 */
	public function mount( Content $content ): void
	{
		$this->content  = $content;
		$this->sections = $content->sections ?? [];
	}

	/**
	 * Save the content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'editor-save' )]
	public function save(): void
	{
		$this->saveStatus = 'saving';

		try {
			$this->content->sections = $this->sections;
			$this->content->save();

			$this->saveStatus = 'saved';
			$this->isDirty    = false;
		} catch ( \Throwable $e ) {
			$this->saveStatus = 'error';
			session()->flash( 'error', __( 'Failed to save content.' ) );
		}
	}

	/**
	 * Toggle the sidebar open/closed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function toggleSidebar(): void
	{
		$this->sidebarOpen = !$this->sidebarOpen;
	}

	/**
	 * Set the active sidebar tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab The tab to activate.
	 *
	 * @return void
	 */
	public function setSidebarTab( string $tab ): void
	{
		$this->sidebarTab = $tab;
	}

	/**
	 * Deselect the active block.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function deselectBlock(): void
	{
		$this->activeBlockId = null;
	}

	/**
	 * Handle the sections being updated.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sections The updated sections data.
	 *
	 * @return void
	 */
	#[On( 'sections-updated' )]
	public function onSectionsUpdated( array $sections ): void
	{
		$this->sections = $sections;
		$this->isDirty  = true;
		$this->saveStatus = 'unsaved';
	}
}; ?>

<div class="ve-editor flex h-screen flex-col overflow-hidden bg-gray-100">
	{{-- Top Toolbar --}}
	<livewire:visual-editor::toolbar
		:save-status="$saveStatus"
		:content-title="$content->title"
		:content-status="$content->status"
	/>

	{{-- Main Editor Area --}}
	<div class="flex flex-1 overflow-hidden">
		{{-- Left Sidebar --}}
		@if ( $sidebarOpen )
			<livewire:visual-editor::sidebar
				:active-tab="$sidebarTab"
			/>
		@endif

		{{-- Canvas Area --}}
		<div class="flex flex-1 flex-col overflow-hidden">
			<livewire:visual-editor::canvas
				:sections="$sections"
				:active-block-id="$activeBlockId"
			/>

			{{-- Status Bar --}}
			<livewire:visual-editor::status-bar
				:save-status="$saveStatus"
				:content-status="$content->status"
			/>
		</div>
	</div>
</div>

@script
<script>
	const handler = ( event ) => {
		const isMac = navigator.platform.toUpperCase().indexOf( 'MAC' ) >= 0;
		const modKey = isMac ? event.metaKey : event.ctrlKey;

		if ( modKey && 's' === event.key ) {
			event.preventDefault();
			$wire.save();
		}

		if ( modKey && !event.shiftKey && 'z' === event.key ) {
			event.preventDefault();
			$wire.dispatch( 'editor-undo' );
		}

		if ( modKey && event.shiftKey && 'z' === event.key ) {
			event.preventDefault();
			$wire.dispatch( 'editor-redo' );
		}

		if ( modKey && '\\' === event.key ) {
			event.preventDefault();
			$wire.toggleSidebar();
		}

		if ( 'Escape' === event.key ) {
			$wire.deselectBlock();
		}
	};

	document.addEventListener( 'keydown', handler );

	$cleanup( () => document.removeEventListener( 'keydown', handler ) );
</script>
@endscript
