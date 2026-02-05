<?php

declare( strict_types=1 );

/**
 * Visual Editor - Main Editor Shell
 *
 * The top-level page component that serves as the main container for
 * all editor functionality including the sidebar, toolbar, canvas,
 * and status bar. Manages save, publish, autosave, and scheduling
 * workflows.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Services\ContentService;
use Illuminate\Support\Carbon;
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
	 * Whether the save-as-pattern modal is open.
	 *
	 * @since 1.1.0
	 *
	 * @var bool
	 */
	public bool $showSavePatternModal = false;

	/**
	 * The pattern name for saving.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public string $patternName = '';

	/**
	 * The pattern description for saving.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public string $patternDescription = '';

	/**
	 * The content blocks data.
	 *
	 * @since 1.1.0
	 *
	 * @var array
	 */
	public array $blocks = [];

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
	 * The last saved time as a human-readable string.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $lastSaved = '';

	/**
	 * Whether the settings drawer is visible.
	 *
	 * @since 1.3.0
	 *
	 * @var bool
	 */
	public bool $showSettingsDrawer = false;

	/**
	 * The active tab within the settings drawer.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	public string $settingsDrawerTab = 'styles';

	/**
	 * The active responsive breakpoint for styles.
	 *
	 * @since 1.8.0
	 *
	 * @var string
	 */
	public string $activeBreakpoint = 'base';

	/**
	 * The active interaction state for styles.
	 *
	 * @since 1.8.0
	 *
	 * @var string
	 */
	public string $activeState = 'default';

	/**
	 * The text color for the active block's current breakpoint/state.
	 *
	 * @since 1.8.0
	 *
	 * @var string
	 */
	public string $styleTextColor = '';

	/**
	 * The background color for the active block's current breakpoint/state.
	 *
	 * @since 1.8.0
	 *
	 * @var string
	 */
	public string $styleBackgroundColor = '';

	/**
	 * The border color for the active block's current breakpoint/state.
	 *
	 * @since 1.8.0
	 *
	 * @var string
	 */
	public string $styleBorderColor = '';

	/**
	 * The content title for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentTitle = '';

	/**
	 * The content slug for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentSlug = '';

	/**
	 * The content excerpt for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentExcerpt = '';

	/**
	 * The content meta title for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentMetaTitle = '';

	/**
	 * The content meta description for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentMetaDescription = '';

	/**
	 * Whether the pre-publish panel is visible.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $showPrePublishPanel = false;

	/**
	 * The pre-publish check results.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $prePublishChecks = [];

	/**
	 * The scheduled publish date.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $scheduleDate = '';

	/**
	 * The scheduled publish time.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $scheduleTime = '';

	/**
	 * The undo history stack.
	 *
	 * Stores previous block states as snapshots for undo operations.
	 *
	 * @since 1.9.0
	 *
	 * @var array<int, array>
	 */
	public array $undoStack = [];

	/**
	 * The redo history stack.
	 *
	 * Stores forward block states as snapshots for redo operations.
	 *
	 * @since 1.9.0
	 *
	 * @var array<int, array>
	 */
	public array $redoStack = [];

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
		$this->content                = $content;
		$this->blocks                 = $content->blocks ?? [];
		$this->contentTitle           = $content->title ?? '';
		$this->contentSlug            = $content->slug ?? '';
		$this->contentExcerpt         = $content->excerpt ?? '';
		$this->contentMetaTitle       = $content->meta_title ?? '';
		$this->contentMetaDescription = $content->meta_description ?? '';
	}

	/**
	 * Save the content as a draft.
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
			$service = app( ContentService::class );

			$service->saveDraft( $this->content, [
				'title'            => $this->contentTitle,
				'slug'             => $this->contentSlug,
				'excerpt'          => $this->contentExcerpt,
				'meta_title'       => $this->contentMetaTitle,
				'meta_description' => $this->contentMetaDescription,
				'blocks'           => $this->blocks,
			], auth()->id() );

			$this->content->refresh();

			$this->saveStatus = 'saved';
			$this->isDirty    = false;
			$this->lastSaved  = now()->format( 'g:i A' );
		} catch ( Throwable $e ) {
			report( $e );
			$this->saveStatus = 'error';
			session()->flash( 'error', __( 'Failed to save content.' ) );
		}
	}

	/**
	 * Opens the pre-publish checklist panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'editor-publish' )]
	public function publish(): void
	{
		$service                   = app( ContentService::class );
		$this->prePublishChecks    = $service->runPrePublishChecks( $this->content );
		$this->showPrePublishPanel = true;
		$this->showSettingsDrawer  = false;
	}

	/**
	 * Confirms publishing after the pre-publish checklist.
	 *
	 * If schedule date and time are set, schedules instead of
	 * publishing immediately.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function confirmPublish(): void
	{
		try {
			$service = app( ContentService::class );

			// Save current content first.
			$service->saveDraft( $this->content, [
				'title'            => $this->contentTitle,
				'slug'             => $this->contentSlug,
				'excerpt'          => $this->contentExcerpt,
				'meta_title'       => $this->contentMetaTitle,
				'meta_description' => $this->contentMetaDescription,
				'blocks'           => $this->blocks,
			], auth()->id() );

			if ( '' !== $this->scheduleDate && '' !== $this->scheduleTime ) {
				try {
					$publishAt = Carbon::parse( $this->scheduleDate . ' ' . $this->scheduleTime );
				} catch ( Exception $e ) {
					session()->flash( 'error', __( 'Invalid schedule date or time.' ) );
					return;
				}
				$service->schedule( $this->content, $publishAt, auth()->id() );
			} else {
				$service->publish( $this->content, auth()->id() );
			}

			$this->content->refresh();
			$this->showPrePublishPanel = false;
			$this->isDirty             = false;
			$this->saveStatus          = 'saved';
			$this->lastSaved           = now()->format( 'g:i A' );
			$this->scheduleDate        = '';
			$this->scheduleTime        = '';
		} catch ( Throwable $e ) {
			$this->saveStatus = 'error';
			report( $e );
			session()->flash( 'error', __( 'Failed to publish content.' ) );
		}
	}

	/**
	 * Unpublishes content by reverting to draft status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'editor-unpublish' )]
	public function unpublish(): void
	{
		try {
			$service = app( ContentService::class );
			$service->unpublish( $this->content, auth()->id() );
			$this->content->refresh();
		} catch ( Throwable $e ) {
			session()->flash( 'error', __( 'Failed to unpublish content.' ) );
		}
	}

	/**
	 * Autosaves the current editor state if dirty.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function autosave(): void
	{
		if ( !$this->isDirty ) {
			return;
		}

		try {
			$service = app( ContentService::class );
			$service->autosave( $this->content, [
				'blocks' => $this->blocks,
			], auth()->id() );
		} catch ( Throwable $e ) {
			// Autosave failures are silent to not disrupt editing.
		}
	}

	/**
	 * Submits content for review.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function submitForReview(): void
	{
		try {
			$service = app( ContentService::class );

			$service->saveDraft( $this->content, [
				'title'            => $this->contentTitle,
				'slug'             => $this->contentSlug,
				'excerpt'          => $this->contentExcerpt,
				'meta_title'       => $this->contentMetaTitle,
				'meta_description' => $this->contentMetaDescription,
				'blocks'           => $this->blocks,
			], auth()->id() );

			$service->submitForReview( $this->content, auth()->id() );
			$this->content->refresh();
			$this->isDirty    = false;
			$this->saveStatus = 'saved';
			$this->lastSaved  = now()->format( 'g:i A' );
		} catch ( Throwable $e ) {
			session()->flash( 'error', __( 'Failed to submit for review.' ) );
		}
	}

	/**
	 * Closes the pre-publish checklist panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function closePrePublishPanel(): void
	{
		$this->showPrePublishPanel = false;
		$this->scheduleDate        = '';
		$this->scheduleTime        = '';
	}

	/**
	 * Push the given blocks snapshot onto the undo stack.
	 *
	 * Clears the redo stack and caps the undo stack at the
	 * configured max_history_states limit.
	 *
	 * @since 1.9.0
	 *
	 * @param array $blocks The blocks state to record.
	 *
	 * @return void
	 */
	public function pushHistory( array $blocks ): void
	{
		$this->undoStack[] = $blocks;
		$this->redoStack   = [];

		$max = (int) config( 'artisanpack.visual-editor.editor.max_history_states', 50 );

		if ( count( $this->undoStack ) > $max ) {
			$this->undoStack = array_slice( $this->undoStack, -$max );
		}

		$this->notifyToolbarUndoRedo();
	}

	/**
	 * Undo the last block change.
	 *
	 * Pops the most recent snapshot from the undo stack, pushes
	 * the current state onto the redo stack, and syncs the canvas.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	#[On( 'editor-undo' )]
	public function undo(): void
	{
		if ( empty( $this->undoStack ) ) {
			return;
		}

		$this->redoStack[] = $this->blocks;
		$this->blocks      = array_pop( $this->undoStack );
		$this->isDirty     = true;
		$this->saveStatus  = 'unsaved';

		$this->dispatch( 'canvas-sync-blocks', blocks: $this->blocks );
		$this->notifyToolbarUndoRedo();
	}

	/**
	 * Redo the last undone block change.
	 *
	 * Pops the most recent snapshot from the redo stack, pushes
	 * the current state onto the undo stack, and syncs the canvas.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	#[On( 'editor-redo' )]
	public function redo(): void
	{
		if ( empty( $this->redoStack ) ) {
			return;
		}

		$this->undoStack[] = $this->blocks;
		$this->blocks      = array_pop( $this->redoStack );
		$this->isDirty     = true;
		$this->saveStatus  = 'unsaved';

		$this->dispatch( 'canvas-sync-blocks', blocks: $this->blocks );
		$this->notifyToolbarUndoRedo();
	}

	/**
	 * Notify the toolbar of the current undo/redo availability.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	public function notifyToolbarUndoRedo(): void
	{
		$this->dispatch( 'undo-redo-state-changed', canUndo: !empty( $this->undoStack ), canRedo: !empty( $this->redoStack ) );
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
		$this->activeBlockId     = null;
		$this->settingsDrawerTab = 'page';
	}

	/**
	 * Handle the blocks being updated.
	 *
	 * @since 1.1.0
	 *
	 * @param array $blocks The updated blocks data.
	 *
	 * @return void
	 */
	#[On( 'editor-sync-state' )]
	public function onEditorSyncState( array $blocks ): void
	{
		$this->pushHistory( $this->blocks );
		$this->blocks     = $blocks;
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle blocks reordered from the layers tab.
	 *
	 * Updates the editor state and syncs the new order to the canvas.
	 *
	 * @since 1.4.0
	 *
	 * @param array $blocks The reordered blocks data.
	 *
	 * @return void
	 */
	#[On( 'layers-reordered' )]
	public function onLayersReordered( array $blocks ): void
	{
		$this->pushHistory( $this->blocks );
		$this->blocks     = $blocks;
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
		$this->dispatch( 'canvas-sync-blocks', blocks: $this->blocks );
	}

	/**
	 * Toggle the settings drawer open/closed.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function toggleSettingsDrawer(): void
	{
		$this->showSettingsDrawer = !$this->showSettingsDrawer;

		if ( $this->showSettingsDrawer ) {
			$this->showPrePublishPanel = false;
		}
	}

	/**
	 * Set the active settings drawer tab.
	 *
	 * @since 1.3.0
	 *
	 * @param string $tab The tab to activate ('block' or 'page').
	 *
	 * @return void
	 */
	public function setSettingsDrawerTab( string $tab ): void
	{
		$this->settingsDrawerTab = $tab;
	}

	/**
	 * Handle block selection from the canvas.
	 *
	 * Opens the settings drawer and switches to the block tab.
	 *
	 * @since 1.3.0
	 *
	 * @param string $blockId The selected block ID.
	 *
	 * @return void
	 */
	#[On( 'block-selected' )]
	public function onBlockSelected( string $blockId ): void
	{
		$this->activeBlockId      = $blockId;
		$this->showSettingsDrawer = true;
		$this->settingsDrawerTab  = 'styles';

		if ( $this->showPrePublishPanel ) {
			$this->showPrePublishPanel = false;
		}

		$this->syncColorProperties();
	}

	/**
	 * Handle the sidebar toggle event from the toolbar.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	#[On( 'editor-toggle-sidebar' )]
	public function onToggleSidebar(): void
	{
		$this->toggleSidebar();
	}

	/**
	 * Handle the settings toggle event from the toolbar.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	#[On( 'editor-toggle-settings' )]
	public function onToggleSettings(): void
	{
		$this->toggleSettingsDrawer();
	}

	/**
	 * Get the active block's registry configuration.
	 *
	 * @since 1.4.0
	 *
	 * @return array|null
	 */
	public function getActiveBlockConfig(): ?array
	{
		if ( null === $this->activeBlockId ) {
			return null;
		}

		$block = $this->findBlockRecursive( $this->activeBlockId, $this->blocks );

		if ( null === $block ) {
			return null;
		}

		return veBlocks()->get( $block['type'] ?? '' );
	}

	/**
	 * Update a setting on the active block.
	 *
	 * @since 1.4.0
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The setting value.
	 *
	 * @return void
	 */
	public function updateBlockSetting( string $key, mixed $value ): void
	{
		if ( null === $this->activeBlockId ) {
			return;
		}

		$path = $this->findBlockPath( $this->activeBlockId, $this->blocks );

		if ( null === $path ) {
			return;
		}

		$this->pushHistory( $this->blocks );

		$blocks = $this->blocks;
		data_set( $blocks, $path . '.settings.' . $key, $value );

		$this->blocks     = $blocks;
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';

		// Notify canvas component to sync the updated blocks
		$this->dispatch( 'canvas-sync-blocks', blocks: $this->blocks );
	}

	/**
	 * Apply a block variation to the active block.
	 *
	 * Updates the block's settings to match the selected variation's defaults.
	 *
	 * @since 2.0.0
	 *
	 * @param string $blockType     The block type identifier.
	 * @param string $variationName The variation name to apply.
	 *
	 * @return void
	 */
	public function applyBlockVariation( string $blockType, string $variationName ): void
	{
		if ( null === $this->activeBlockId ) {
			return;
		}

		$variation = veBlocks()->getVariation( $blockType, $variationName );

		if ( null === $variation ) {
			return;
		}

		$path = $this->findBlockPath( $this->activeBlockId, $this->blocks );

		if ( null === $path ) {
			return;
		}

		$this->pushHistory( $this->blocks );

		$blocks = $this->blocks;

		// Store the selected variation
		data_set( $blocks, $path . '.settings._variation', $variationName );

		// Apply variation attributes to block settings
		if ( isset( $variation['attributes']['settings'] ) && is_array( $variation['attributes']['settings'] ) ) {
			foreach ( $variation['attributes']['settings'] as $key => $value ) {
				data_set( $blocks, $path . '.settings.' . $key, $value );
			}
		}

		$this->blocks     = $blocks;
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';

		// Sync the updated block to the canvas
		$this->dispatch( 'canvas-sync-blocks', blocks: $this->blocks );
	}

	/**
	 * Get a style value for the active block at a specific breakpoint, state, section, and property.
	 *
	 * @since 1.8.0
	 *
	 * @param string $breakpoint The breakpoint key (base, md, lg).
	 * @param string $state      The state key (default, hover, focus, active).
	 * @param string $section    The section key (sizing, typography, etc.).
	 * @param string $property   The property key (padding_top, font_size, etc.).
	 *
	 * @return string
	 */
	public function getStyleValue( string $breakpoint, string $state, string $section, string $property ): string
	{
		if ( null === $this->activeBlockId ) {
			return '';
		}

		$block = $this->findBlockRecursive( $this->activeBlockId, $this->blocks );

		if ( null === $block ) {
			return '';
		}

		return (string) data_get( $block['settings'], "styles.{$breakpoint}.{$state}.{$section}.{$property}", '' );
	}

	/**
	 * Handle active breakpoint updates by syncing color properties.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function updatedActiveBreakpoint(): void
	{
		$this->syncColorProperties();
	}

	/**
	 * Handle active state updates by syncing color properties.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function updatedActiveState(): void
	{
		$this->syncColorProperties();
	}

	/**
	 * Handle text color updates from the colorpicker.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function updatedStyleTextColor(): void
	{
		$this->updateBlockSetting( "styles.{$this->activeBreakpoint}.{$this->activeState}.colors.text_color", $this->styleTextColor );
	}

	/**
	 * Handle background color updates from the colorpicker.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function updatedStyleBackgroundColor(): void
	{
		$this->updateBlockSetting( "styles.{$this->activeBreakpoint}.{$this->activeState}.colors.background_color", $this->styleBackgroundColor );
	}

	/**
	 * Handle border color updates from the colorpicker.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function updatedStyleBorderColor(): void
	{
		$this->updateBlockSetting( "styles.{$this->activeBreakpoint}.{$this->activeState}.borders.border_color", $this->styleBorderColor );
	}

	/**
	 * Handle page setting property updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @param string $property The property name that was updated.
	 *
	 * @return void
	 */
	public function updatedContentTitle(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content slug updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentSlug(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content excerpt updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentExcerpt(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content meta title updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentMetaTitle(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content meta description updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentMetaDescription(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Open the save-as-pattern modal.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	#[On( 'open-save-pattern-modal' )]
	public function openSavePatternModal(): void
	{
		$this->patternName          = '';
		$this->patternDescription   = '';
		$this->showSavePatternModal = true;
	}

	/**
	 * Save the current blocks as a pattern.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function confirmSavePattern(): void
	{
		$this->dispatch( 'save-blocks-as-section', name: $this->patternName, description: $this->patternDescription );

		$this->showSavePatternModal = false;
		$this->patternName          = '';
		$this->patternDescription   = '';
	}

	/**
	 * Sync color properties from the active block's settings.
	 *
	 * Reads the text, background, and border color values from the
	 * active block at the current breakpoint and state, and sets
	 * them on the dedicated Livewire properties so they can be used
	 * with wire:model on colorpicker components.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	protected function syncColorProperties(): void
	{
		$this->styleTextColor       = $this->getStyleValue( $this->activeBreakpoint, $this->activeState, 'colors', 'text_color' );
		$this->styleBackgroundColor = $this->getStyleValue( $this->activeBreakpoint, $this->activeState, 'colors', 'background_color' );
		$this->styleBorderColor     = $this->getStyleValue( $this->activeBreakpoint, $this->activeState, 'borders', 'border_color' );
	}

	/**
	 * Recursively find a block by ID anywhere in the block tree.
	 *
	 * @since 2.0.0
	 *
	 * @param string $blockId The block ID to find.
	 * @param array  $blocks  The blocks array to search.
	 *
	 * @return array|null The block data or null.
	 */
	private function findBlockRecursive( string $blockId, array $blocks ): ?array
	{
		foreach ( $blocks as $block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				return $block;
			}

			$found = $this->searchInnerBlocks( $blockId, $block );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Search within a single block's inner containers for a block ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $blockId The block ID to find.
	 * @param array  $block   The parent block to search within.
	 *
	 * @return array|null The found block or null.
	 */
	private function searchInnerBlocks( string $blockId, array $block ): ?array
	{
		$innerBlocks = $block['content']['inner_blocks'] ?? [];

		if ( !empty( $innerBlocks ) ) {
			$found = $this->findBlockRecursive( $blockId, $innerBlocks );

			if ( null !== $found ) {
				return $found;
			}
		}

		$columns = $block['content']['columns'] ?? [];

		foreach ( $columns as $column ) {
			$colBlocks = $column['blocks'] ?? [];

			if ( !empty( $colBlocks ) ) {
				$found = $this->findBlockRecursive( $blockId, $colBlocks );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		$items = $block['content']['items'] ?? [];

		foreach ( $items as $item ) {
			$itemBlocks = $item['inner_blocks'] ?? [];

			if ( !empty( $itemBlocks ) ) {
				$found = $this->findBlockRecursive( $blockId, $itemBlocks );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Find the dot-notation path to a block in the tree.
	 *
	 * @since 2.0.0
	 *
	 * @param string $blockId The block ID to find.
	 * @param array  $blocks  The blocks array to search.
	 * @param string $prefix  The current path prefix.
	 *
	 * @return string|null The dot-notation path or null.
	 */
	private function findBlockPath( string $blockId, array $blocks, string $prefix = '' ): ?string
	{
		foreach ( $blocks as $index => $block ) {
			$currentPath = '' === $prefix ? (string) $index : $prefix . '.' . $index;

			if ( ( $block['id'] ?? '' ) === $blockId ) {
				return $currentPath;
			}

			$innerBlocks = $block['content']['inner_blocks'] ?? [];

			if ( !empty( $innerBlocks ) ) {
				$found = $this->findBlockPath( $blockId, $innerBlocks, $currentPath . '.content.inner_blocks' );

				if ( null !== $found ) {
					return $found;
				}
			}

			$columns = $block['content']['columns'] ?? [];

			foreach ( $columns as $colIndex => $column ) {
				$colBlocks = $column['blocks'] ?? [];

				if ( !empty( $colBlocks ) ) {
					$found = $this->findBlockPath( $blockId, $colBlocks, $currentPath . '.content.columns.' . $colIndex . '.blocks' );

					if ( null !== $found ) {
						return $found;
					}
				}
			}

			$items = $block['content']['items'] ?? [];

			foreach ( $items as $itemIndex => $item ) {
				$itemBlocks = $item['inner_blocks'] ?? [];

				if ( !empty( $itemBlocks ) ) {
					$found = $this->findBlockPath( $blockId, $itemBlocks, $currentPath . '.content.items.' . $itemIndex . '.inner_blocks' );

					if ( null !== $found ) {
						return $found;
					}
				}
			}
		}

		return null;
	}
}; ?>

<div class="ve-editor flex h-screen flex-col overflow-hidden bg-gray-100"
	 data-theme="light"
	 data-autosave-interval="{{ config( 'artisanpack.visual-editor.editor.autosave_interval', 60 ) }}">
	{{-- Top Toolbar --}}
	<livewire:visual-editor::toolbar
		:save-status="$saveStatus"
		:content-title="$content->title"
		:content-status="$content->status"
		:sidebar-open="$sidebarOpen"
		:settings-open="$showSettingsDrawer"
	/>

	{{-- Main Editor Area --}}
	<div class="flex flex-1 overflow-hidden">
		{{-- Left Sidebar --}}
		@if ( $sidebarOpen )
			<livewire:visual-editor::sidebar
				:active-tab="$sidebarTab"
				:blocks="$blocks"
				:active-block-id="$activeBlockId"
			/>
		@endif

		{{-- Canvas Area --}}
		<div class="flex flex-1 flex-col overflow-hidden">
			<livewire:visual-editor::canvas
				wire:key="editor-canvas"
				:blocks="$blocks"
				:active-block-id="$activeBlockId"
			/>

			{{-- Status Bar --}}
			<livewire:visual-editor::status-bar
				:save-status="$saveStatus"
				:content-status="$content->status"
				:last-saved="$lastSaved"
			/>
		</div>

		{{-- Save as Pattern Modal --}}
		@if ( $showSavePatternModal )
			<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="$set( 'showSavePatternModal', false )">
				<div class="w-96 rounded-lg bg-white p-6 shadow-xl">
					<h3 class="mb-4 text-lg font-semibold text-gray-900">{{ __( 'Save as Pattern' ) }}</h3>
					<div class="mb-3">
						<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( 'Name' ) }}</label>
						<input
							wire:model="patternName"
							type="text"
							class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
							placeholder="{{ __( 'Pattern name...' ) }}"
						/>
					</div>
					<div class="mb-4">
						<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( 'Description' ) }}</label>
						<textarea
							wire:model="patternDescription"
							class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
							rows="2"
							placeholder="{{ __( 'Optional description...' ) }}"
						></textarea>
					</div>
					<div class="flex justify-end gap-2">
						<x-artisanpack-button
							wire:click="$set( 'showSavePatternModal', false )"
							:label="__( 'Cancel' )"
							variant="outline"
							size="sm"
						/>
						<x-artisanpack-button
							wire:click="confirmSavePattern"
							:label="__( 'Save Pattern' )"
							color="primary"
							size="sm"
						/>
					</div>
				</div>
			</div>
		@endif

		{{-- Right Settings Panel (inline) --}}
		@if ( $showSettingsDrawer )
			<div
				class="ve-settings-panel flex w-72 shrink-0 flex-col border-l border-gray-200 bg-white"
			>
				{{-- Panel Header --}}
				<div class="border-b border-gray-200 px-3 py-2.5">
					<h3 class="text-sm font-semibold text-gray-900">
						{{ __( 'Edit Block' ) }}
					</h3>
				</div>

				{{-- Settings Panel Tabs --}}
				<div class="flex border-b border-gray-200">
					@php
						$settingsTabs = [
							'styles'   => __( 'Styles' ),
							'settings' => __( 'Settings' ),
							'page'     => __( 'Page' ),
						];
					@endphp
					@foreach ( $settingsTabs as $key => $label )
						<button
							wire:key="settings-tab-{{ $key }}"
							wire:click="setSettingsDrawerTab( '{{ $key }}' )"
							class="flex-1 border-b-2 px-3 py-2 text-center text-xs font-medium transition-colors
								{{ $settingsDrawerTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
						>
							{{ $label }}
						</button>
					@endforeach
				</div>

				{{-- Tab Content --}}
				<div class="flex-1 overflow-y-auto">
					@if ( 'styles' === $settingsDrawerTab )
						@if ( null !== $activeBlockId )
							@php
								$activeBlockConfig = $this->getActiveBlockConfig();
								$activeBlock       = $this->findBlockRecursive( $activeBlockId, $this->blocks );
								$currentSettings   = $activeBlock['settings'] ?? [];
							@endphp

							@include( 'visual-editor::livewire.partials.settings-styles-tab', [
								'activeBlockConfig' => $activeBlockConfig,
								'currentSettings'   => $currentSettings,
								'activeBreakpoint'  => $activeBreakpoint,
								'activeState'       => $activeState,
							] )
						@else
							<div class="p-3">
								<p class="text-sm text-gray-500">
									{{ __( 'Select a block on the canvas to view its styles.' ) }}
								</p>
							</div>
						@endif
					@elseif ( 'settings' === $settingsDrawerTab )
						<div class="p-3">
							@if ( null !== $activeBlockId )
								@php
									$activeBlockConfig = $this->getActiveBlockConfig();
									$settingsSchema    = $activeBlockConfig['settings_schema'] ?? [];
									$activeBlock       = $this->findBlockRecursive( $activeBlockId, $this->blocks );
									$currentSettings   = $activeBlock['settings'] ?? [];
									$blockType         = $activeBlock['type'] ?? $activeBlock['name'] ?? '';
									$hasVariations     = veBlocks()->hasVariations( $blockType );
									$variations        = $hasVariations ? veBlocks()->getVariations( $blockType ) : [];
									$currentVariation  = $currentSettings['_variation'] ?? '';
								@endphp

								{{-- Block Variation Selector --}}
								@if ( $hasVariations )
									<div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3">
										@php
											$variationOptions = collect( $variations )->map( function ( $variation, $key ) use ( $currentVariation ) {
												$title = $variation['title'] ?? ucfirst( $key );
												$description = $variation['description'] ?? '';
												$displayName = $description ? "{$title} - {$description}" : $title;

												return [
													'id'       => $key,
													'name'     => $displayName,
													'selected' => $key === $currentVariation,
												];
											} )->values()->all();
										@endphp
										<x-artisanpack-select
											:label="__( 'Block Variation' )"
											:options="$variationOptions"
											option-value="id"
											option-label="name"
											wire:change="applyBlockVariation( '{{ $blockType }}', $event.target.value )"
										/>
										<p class="mt-1.5 text-xs text-blue-700">
											{{ __( 'Changing the variation will update block settings to match the selected layout.' ) }}
										</p>
									</div>
								@endif

								@if ( empty( $settingsSchema ) )
									<p class="text-sm text-gray-500">
										{{ __( 'This block has no configurable settings.' ) }}
									</p>
								@else
									<div class="space-y-4">
										@foreach ( $settingsSchema as $settingKey => $schema )
											@php
												$fieldType    = $schema['type'] ?? 'text';
												$fieldLabel   = $schema['label'] ?? ucfirst( str_replace( '_', ' ', $settingKey ) );
												$fieldOptions = $schema['options'] ?? [];
												$fieldDefault = $schema['default'] ?? '';
												$fieldValue   = $currentSettings[ $settingKey ] ?? $fieldDefault;
											@endphp
											<div>

											@if ( 'select' === $fieldType )
												@php
													$selectOptions = collect( $fieldOptions )->map( fn ( $opt ) => [
														'id'       => $opt,
														'name'     => ucfirst( $opt ),
														'selected' => $opt === $fieldValue,
													] )->all();
												@endphp
												<x-artisanpack-select
													:label="__( $fieldLabel )"
													:options="$selectOptions"
													option-value="id"
													option-label="name"
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
												/>
											@elseif ( 'alignment' === $fieldType )
												@php
													$alignmentOptions = [
														[ 'id' => 'left', 'name' => __( 'Left' ) ],
														[ 'id' => 'center', 'name' => __( 'Center' ) ],
														[ 'id' => 'right', 'name' => __( 'Right' ) ],
													];
												@endphp
												<x-artisanpack-select
													:label="__( $fieldLabel )"
													:options="$alignmentOptions"
													option-value="id"
													option-label="name"
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
													:selected="$fieldValue"
												/>
											@elseif ( 'toggle' === $fieldType )
												<x-artisanpack-toggle
													:label="__( $fieldLabel )"
													:checked="(bool) $fieldValue"
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.checked )"
												/>
											@elseif ( 'color_picker' === $fieldType )
												<x-artisanpack-input
													type="color"
													:label="__( $fieldLabel )"
													:value="$fieldValue"
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
												/>
											@elseif ( 'url' === $fieldType )
												<x-artisanpack-input
													type="url"
													:label="__( $fieldLabel )"
													:value="$fieldValue"
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
												/>
											@elseif ( 'textarea' === $fieldType )
												<div>
													<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( $fieldLabel ) }}</label>
													<textarea
														wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
														class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
														rows="3"
													>{{ $fieldValue }}</textarea>
												</div>
											@else
												<x-artisanpack-input
													:label="__( $fieldLabel )"
													:value="$fieldValue"
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
												/>
											@endif
											</div>
										@endforeach
									</div>
								@endif
							@else
								<p class="text-sm text-gray-500">
									{{ __( 'Select a block on the canvas to view its settings.' ) }}
								</p>
							@endif
						</div>
					@elseif ( 'page' === $settingsDrawerTab )
						<div class="space-y-4 p-3">
							<x-artisanpack-input
								wire:model.blur="contentTitle"
								:label="__( 'Title' )"
							/>

							<x-artisanpack-input
								wire:model.blur="contentSlug"
								:label="__( 'Slug' )"
							/>

							<div>
								<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( 'Excerpt' ) }}</label>
								<textarea
									wire:model.blur="contentExcerpt"
									class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
									rows="3"
									placeholder="{{ __( 'A short summary of the content...' ) }}"
								></textarea>
							</div>

							<div class="border-t border-gray-200 pt-4">
								<x-artisanpack-heading level="3" size="text-sm" semibold class="mb-3">
									{{ __( 'SEO' ) }}
								</x-artisanpack-heading>

								<div class="space-y-4">
									<x-artisanpack-input
										wire:model.blur="contentMetaTitle"
										:label="__( 'Meta Title' )"
									/>

									<div>
										<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( 'Meta Description' ) }}</label>
										<textarea
											wire:model.blur="contentMetaDescription"
											class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
											rows="3"
											placeholder="{{ __( 'A description for search engines...' ) }}"
										></textarea>
									</div>
								</div>
							</div>
						</div>
					@endif
				</div>
			</div>
		@endif
	</div>

	{{-- Pre-Publish Checklist Panel --}}
	<x-artisanpack-drawer
		wire:model="showPrePublishPanel"
		right
		:title="__( 'Pre-Publish Checklist' )"
		separator
		with-close-button
		close-on-escape
		class="z-50"
	>
		{{-- Checklist Items --}}
		<div class="space-y-3">
			@foreach ( $prePublishChecks as $check )
				<div wire:key="check-{{ $loop->index }}" class="flex items-start gap-3 rounded-md border border-gray-200 p-3">
					@if ( 'pass' === $check['status'] )
						<x-artisanpack-icon name="o-check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-green-500" />
					@elseif ( 'fail' === $check['status'] )
						<x-artisanpack-icon name="o-x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
					@else
						<x-artisanpack-icon name="o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" />
					@endif
					<div>
						<p class="text-sm font-medium text-gray-900">{{ $check['label'] }}</p>
						<p class="text-xs text-gray-500">{{ $check['message'] }}</p>
					</div>
				</div>
			@endforeach
		</div>

		{{-- Schedule Section --}}
		<div class="mt-6">
			<x-artisanpack-heading level="3" size="text-sm" semibold class="mb-2">
				{{ __( 'Schedule (optional)' ) }}
			</x-artisanpack-heading>
			<div class="flex gap-2">
				<x-artisanpack-input
					wire:model="scheduleDate"
					type="date"
					class="flex-1"
				/>
				<x-artisanpack-input
					wire:model="scheduleTime"
					type="time"
					class="flex-1"
				/>
			</div>
			<p class="mt-1 text-xs text-gray-400">
				{{ __( 'Leave empty to publish immediately.' ) }}
			</p>
		</div>

		{{-- Panel Footer --}}
		<x-slot:actions>
			@php
				$hasFailures = collect( $prePublishChecks )->contains( 'status', 'fail' );
			@endphp
			<x-artisanpack-button
				wire:click="confirmPublish"
				:label="'' !== $scheduleDate && '' !== $scheduleTime ? __( 'Schedule' ) : __( 'Publish' )"
				color="primary"
				class="w-full"
				:disabled="$hasFailures"
			/>
			@if ( $hasFailures )
				<x-artisanpack-alert
					:title="__( 'Fix all required checks before publishing.' )"
					color="error"
					icon="o-exclamation-triangle"
					class="mt-2"
				/>
			@endif
		</x-slot:actions>
	</x-artisanpack-drawer>
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

		if ( modKey && event.shiftKey && ',' === event.key ) {
			event.preventDefault();
			$wire.toggleSettingsDrawer();
		}

		if ( 'Escape' === event.key ) {
			$wire.deselectBlock();
		}
	};

	document.addEventListener( 'keydown', handler );

	// Autosave timer
	const interval = parseInt( $wire.$el.dataset.autosaveInterval, 10 ) || 60;
	const autosaveTimer = setInterval( () => $wire.autosave(), interval * 1000 );

	// Clean up event listeners and timers on navigation
	const cleanup = () => {
		document.removeEventListener( 'keydown', handler );
		clearInterval( autosaveTimer );
	};

	document.addEventListener( 'livewire:navigating', cleanup, { once: true } );
</script>
@endscript
