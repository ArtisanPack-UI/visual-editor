<?php

declare( strict_types=1 );

/**
 * Visual Editor - Canvas
 *
 * The main editing surface where content blocks are rendered.
 * Supports drag-and-drop reordering of blocks, inline editing,
 * zoom controls, grid overlay, and keyboard navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
	/**
	 * The content blocks data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $blocks = [];

	/**
	 * The ID of the currently active block.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $activeBlockId = null;

	/**
	 * The current zoom level as a percentage (50-200).
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	public int $zoomLevel = 100;

	/**
	 * Whether the alignment grid overlay is visible.
	 *
	 * @since 1.1.0
	 *
	 * @var bool
	 */
	public bool $showGrid = false;

	/**
	 * The ID of the block currently in inline edit mode.
	 *
	 * @since 1.1.0
	 *
	 * @var string|null
	 */
	public ?string $editingBlockId = null;

	/**
	 * Select a block in the canvas.
	 *
	 * @since 1.0.0
	 *
	 * @param string $blockId The block ID to select.
	 *
	 * @return void
	 */
	public function selectBlock( string $blockId ): void
	{
		$this->activeBlockId  = $blockId;
		$this->editingBlockId = null;
		$this->dispatch( 'block-selected', blockId: $blockId );
	}

	/**
	 * Handle block selection from external sources (e.g. layers tab).
	 *
	 * @since 1.4.0
	 *
	 * @param string $blockId The block ID to select.
	 *
	 * @return void
	 */
	#[On( 'block-selected' )]
	public function onBlockSelected( string $blockId ): void
	{
		$this->activeBlockId = $blockId;

		if ( $this->editingBlockId !== $blockId ) {
			$this->editingBlockId = null;
		}
	}

	/**
	 * Sync blocks from the editor (e.g. after layers reorder).
	 *
	 * @since 1.4.0
	 *
	 * @param array $blocks The updated blocks array.
	 *
	 * @return void
	 */
	#[On( 'canvas-sync-blocks' )]
	public function onCanvasSyncBlocks( array $blocks ): void
	{
		$this->blocks = $blocks;
	}

	/**
	 * Deselect all blocks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function deselectAll(): void
	{
		$this->activeBlockId  = null;
		$this->editingBlockId = null;
	}

	/**
	 * Handle drag-and-drop reordering of blocks.
	 *
	 * Accepts an array of block IDs in the new order,
	 * as provided by the x-drag-context drag:end event.
	 *
	 * @since 1.0.0
	 *
	 * @param array $orderedIds The new block ID order.
	 *
	 * @return void
	 */
	public function reorderBlocks( array $orderedIds ): void
	{
		$indexed   = collect( $this->blocks )->keyBy( 'id' );
		$reordered = [];

		foreach ( $orderedIds as $id ) {
			if ( $indexed->has( $id ) ) {
				$reordered[] = $indexed->get( $id );
			}
		}

		$this->blocks = $reordered;
		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Handle a block insert event from the sidebar.
	 *
	 * Appends a new block directly to the flat blocks list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type to insert.
	 *
	 * @return void
	 */
	#[On( 'block-insert' )]
	public function insertBlock( string $type ): void
	{
		$this->blocks[] = [
			'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
			'type'     => $type,
			'content'  => [],
			'settings' => [],
		];

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Handle a section insert event from the sidebar.
	 *
	 * Inserts the section's default blocks directly into the
	 * flat blocks list without a section wrapper.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type The section type to insert.
	 *
	 * @return void
	 */
	#[On( 'section-insert' )]
	public function insertSection( string $type ): void
	{
		$registry = app( SectionRegistry::class );
		$config   = $registry->get( $type );

		if ( null !== $config ) {
			foreach ( $config['default_blocks'] ?? [] as $blockDef ) {
				$this->blocks[] = [
					'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
					'type'     => $blockDef['type'] ?? 'text',
					'content'  => $blockDef['content'] ?? [],
					'settings' => [],
				];
			}
		}

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Move a block up by one position.
	 *
	 * @since 1.7.0
	 *
	 * @param string $blockId The block ID to move up.
	 *
	 * @return void
	 */
	public function moveBlockUp( string $blockId ): void
	{
		$index = null;

		foreach ( $this->blocks as $i => $block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				$index = $i;
				break;
			}
		}

		if ( null === $index || 0 === $index ) {
			return;
		}

		$temp                         = $this->blocks[ $index - 1 ];
		$this->blocks[ $index - 1 ]   = $this->blocks[ $index ];
		$this->blocks[ $index ]       = $temp;

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Move a block down by one position.
	 *
	 * @since 1.7.0
	 *
	 * @param string $blockId The block ID to move down.
	 *
	 * @return void
	 */
	public function moveBlockDown( string $blockId ): void
	{
		$index = null;

		foreach ( $this->blocks as $i => $block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				$index = $i;
				break;
			}
		}

		if ( null === $index || $index >= count( $this->blocks ) - 1 ) {
			return;
		}

		$temp                         = $this->blocks[ $index + 1 ];
		$this->blocks[ $index + 1 ]   = $this->blocks[ $index ];
		$this->blocks[ $index ]       = $temp;

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Change the heading level for a heading block.
	 *
	 * @since 1.7.0
	 *
	 * @param string $blockId The block ID to update.
	 * @param string $level   The new heading level (h1-h6).
	 *
	 * @return void
	 */
	public function changeHeadingLevel( string $blockId, string $level ): void
	{
		if ( !in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
			return;
		}

		foreach ( $this->blocks as &$block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				$block['content']['level'] = $level;
				break;
			}
		}

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Delete a block from the canvas.
	 *
	 * @since 1.1.0
	 *
	 * @param string $blockId The block ID to delete.
	 *
	 * @return void
	 */
	public function deleteBlock( string $blockId ): void
	{
		$this->blocks = array_values(
			array_filter( $this->blocks, fn ( $b ) => ( $b['id'] ?? '' ) !== $blockId ),
		);

		if ( $this->activeBlockId === $blockId ) {
			$this->activeBlockId  = null;
			$this->editingBlockId = null;
		}

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Set the canvas zoom level.
	 *
	 * @since 1.1.0
	 *
	 * @param int $level The zoom level as a percentage (50-200).
	 *
	 * @return void
	 */
	public function setZoomLevel( int $level ): void
	{
		$this->zoomLevel = max( 50, min( 200, $level ) );
	}

	/**
	 * Toggle the alignment grid overlay.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function toggleGrid(): void
	{
		$this->showGrid = !$this->showGrid;
	}

	/**
	 * Enter inline edit mode for a text block.
	 *
	 * @since 1.1.0
	 *
	 * @param string $blockId The block ID to edit inline.
	 *
	 * @return void
	 */
	public function startInlineEdit( string $blockId ): void
	{
		$this->editingBlockId = $blockId;
		$this->activeBlockId  = $blockId;
		$this->dispatch( 'block-selected', blockId: $blockId );
	}

	/**
	 * Save inline edit and exit edit mode.
	 *
	 * @since 1.1.0
	 *
	 * @param string $blockId The block ID being edited.
	 * @param string $content The updated text content.
	 *
	 * @return void
	 */
	public function saveInlineEdit( string $blockId, string $content ): void
	{
		foreach ( $this->blocks as &$block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				$block['content']['text'] = $content;
				break;
			}
		}

		$this->editingBlockId = null;
		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
	}

	/**
	 * Save inline edit and navigate to an adjacent block.
	 *
	 * Combines content saving with navigation to prevent data loss
	 * when moving between blocks via Tab or Arrow keys.
	 *
	 * @since 1.6.0
	 *
	 * @param string $blockId   The block ID being edited.
	 * @param string $content   The updated text content.
	 * @param string $direction The navigation direction ('up' or 'down').
	 *
	 * @return void
	 */
	public function saveAndNavigate( string $blockId, string $content, string $direction ): void
	{
		$currentIndex = null;

		foreach ( $this->blocks as $i => $block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				$this->blocks[ $i ]['content']['text'] = $content;
				$currentIndex                          = $i;
				break;
			}
		}

		if ( null === $currentIndex ) {
			$this->editingBlockId = null;
			$this->dispatch( 'blocks-updated', blocks: $this->blocks );

			return;
		}

		$lastIndex = count( $this->blocks ) - 1;

		// At the last block going down: exit edit mode and focus typing area.
		if ( 'down' === $direction && $currentIndex >= $lastIndex ) {
			$this->editingBlockId = null;
			$this->activeBlockId  = null;
			$this->dispatch( 'blocks-updated', blocks: $this->blocks );
			$this->dispatch( 'focus-typing-area' );

			return;
		}

		// At the first block going up: stay on the same block.
		if ( 'up' === $direction && 0 === $currentIndex ) {
			$this->dispatch( 'blocks-updated', blocks: $this->blocks );

			return;
		}

		$targetIndex   = 'up' === $direction ? $currentIndex - 1 : $currentIndex + 1;
		$targetBlock   = $this->blocks[ $targetIndex ];
		$targetBlockId = $targetBlock['id'] ?? '';

		$this->activeBlockId  = $targetBlockId;
		$this->editingBlockId = $this->isBlockEditable( $targetBlock['type'] ?? '' )
			? $targetBlockId
			: null;

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );

		if ( null !== $this->editingBlockId ) {
			$this->dispatch( 'focus-block', blockId: $this->editingBlockId );
		}
	}

	/**
	 * Handle keyboard navigation between blocks.
	 *
	 * @since 1.1.0
	 *
	 * @param string $direction The navigation direction ('up' or 'down').
	 *
	 * @return void
	 */
	#[On( 'canvas-navigate' )]
	public function navigateBlocks( string $direction ): void
	{
		if ( empty( $this->blocks ) ) {
			return;
		}

		$currentIndex = null;

		foreach ( $this->blocks as $i => $block ) {
			if ( ( $block['id'] ?? '' ) === $this->activeBlockId ) {
				$currentIndex = $i;
				break;
			}
		}

		if ( null === $currentIndex ) {
			$targetIndex = 'up' === $direction
				? count( $this->blocks ) - 1
				: 0;
		} elseif ( 'up' === $direction ) {
			$targetIndex = max( 0, $currentIndex - 1 );
		} else {
			$targetIndex = min( count( $this->blocks ) - 1, $currentIndex + 1 );
		}

		$targetBlock          = $this->blocks[ $targetIndex ];
		$this->activeBlockId  = $targetBlock['id'] ?? '';
		$this->editingBlockId = $this->isBlockEditable( $targetBlock['type'] ?? '' )
			? $this->activeBlockId
			: null;

		if ( null !== $this->editingBlockId ) {
			$this->dispatch( 'focus-block', blockId: $this->editingBlockId );
		}
	}

	/**
	 * Handle keyboard deletion of selected block.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	#[On( 'canvas-delete-selected' )]
	public function deleteSelected(): void
	{
		if ( null !== $this->activeBlockId ) {
			$this->deleteBlock( $this->activeBlockId );
		}
	}

	/**
	 * Insert a block with optional initial text content.
	 *
	 * Used by the typing area to create blocks from typed text
	 * or from the slash command menu. Does not dispatch blocks-updated
	 * to avoid the editor re-render cascade that would destroy the
	 * canvas component and lose editing state. The blocks-updated
	 * event is deferred until saveInlineEdit or insertBlockAfter.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type    The block type to insert.
	 * @param string $content Optional initial text content.
	 *
	 * @return void
	 */
	public function insertBlockWithContent( string $type, string $content = '' ): void
	{
		$newBlock = [
			'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
			'type'     => $type,
			'content'  => [],
			'settings' => [],
		];

		if ( '' !== $content ) {
			$newBlock['content']['text'] = $content;
		}

		$this->blocks[] = $newBlock;

		$this->activeBlockId  = $newBlock['id'];
		$this->editingBlockId = $newBlock['id'];

		$this->dispatch( 'focus-block', blockId: $newBlock['id'] );
	}

	/**
	 * Save current block content and insert a new text block after it.
	 *
	 * Triggered by pressing Enter inside an editable block. Saves the
	 * current block's content, creates a new text block immediately
	 * after it, and enters edit mode on the new block.
	 *
	 * @since 1.6.0
	 *
	 * @param string $blockId The block ID being edited.
	 * @param string $content The current block's text content.
	 *
	 * @return void
	 */
	public function insertBlockAfter( string $blockId, string $content ): void
	{
		$currentIndex = null;

		foreach ( $this->blocks as $i => $block ) {
			if ( ( $block['id'] ?? '' ) === $blockId ) {
				$this->blocks[ $i ]['content']['text'] = $content;
				$currentIndex                          = $i;
				break;
			}
		}

		if ( null === $currentIndex ) {
			return;
		}

		$newBlock = [
			'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
			'type'     => 'text',
			'content'  => [],
			'settings' => [],
		];

		array_splice( $this->blocks, $currentIndex + 1, 0, [ $newBlock ] );

		$this->activeBlockId  = $newBlock['id'];
		$this->editingBlockId = $newBlock['id'];

		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
		$this->dispatch( 'focus-block', blockId: $newBlock['id'] );
	}

	/**
	 * Get available blocks grouped by category for the slash command menu.
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */
	#[Computed]
	public function slashMenuBlocks(): array
	{
		$registry = app( BlockRegistry::class );

		return $registry->getGroupedByCategory()->map( function ( $category, $key ) {
			return [
				'key'    => $key,
				'name'   => $category['name'],
				'icon'   => $category['icon'],
				'blocks' => $category['blocks']->map( function ( $block, $type ) {
					return [
						'type'     => $type,
						'name'     => $block['name'],
						'icon'     => $block['icon'] ?? 'fas.cube',
						'keywords' => $block['keywords'] ?? [],
					];
				} )->values()->toArray(),
			];
		} )->values()->toArray();
	}

	/**
	 * Check whether a block type supports inline text editing.
	 *
	 * @since 1.5.0
	 *
	 * @param string $blockType The block type identifier.
	 *
	 * @return bool
	 */
	private function isBlockEditable( string $blockType ): bool
	{
		$config   = app( BlockRegistry::class )->get( $blockType );
		$textType = $config['content_schema']['text']['type'] ?? null;

		return in_array( $textType, [ 'text', 'textarea', 'richtext' ], true );
	}
}; ?>

<div
	class="ve-canvas flex-1 overflow-auto bg-gray-50 p-6"
	x-data="{
		panX: 0,
		panY: 0,
		isPanning: false,
		panStartX: 0,
		panStartY: 0,
		handlePanStart( e ) {
			if ( 1 === e.button || ( 0 === e.button && e.altKey ) ) {
				this.isPanning  = true;
				this.panStartX  = e.clientX - this.panX;
				this.panStartY  = e.clientY - this.panY;
				e.preventDefault();
			}
		},
		handlePanMove( e ) {
			if ( this.isPanning ) {
				this.panX = e.clientX - this.panStartX;
				this.panY = e.clientY - this.panStartY;
			}
		},
		handlePanEnd() {
			this.isPanning = false;
		},
	}"
	x-effect="if ( $refs.surface ) { $refs.surface.style.transform = 'scale(' + ( {{ $zoomLevel }} / 100 ) + ') translate(' + panX + 'px, ' + panY + 'px)'; $refs.surface.style.transformOrigin = 'top center'; }"
	@mousedown="handlePanStart( $event )"
	@mousemove.window="handlePanMove( $event )"
	@mouseup.window="handlePanEnd()"
	@click.self="$wire.deselectAll()"
>
	{{-- Zoom Controls --}}
	<div class="sticky top-0 z-10 mb-4 flex items-center justify-end gap-2">
		<x-artisanpack-button
			wire:click="setZoomLevel( {{ $zoomLevel - 10 }} )"
			icon="o-minus"
			color="ghost"
			size="sm"
			:disabled="50 >= $zoomLevel"
			:title="__( 'Zoom Out' )"
		/>
		<span class="min-w-[3rem] text-center text-xs text-gray-600">
			{{ $zoomLevel }}%
		</span>
		<x-artisanpack-button
			wire:click="setZoomLevel( {{ $zoomLevel + 10 }} )"
			icon="o-plus"
			color="ghost"
			size="sm"
			:disabled="200 <= $zoomLevel"
			:title="__( 'Zoom In' )"
		/>
		<x-artisanpack-button
			wire:click="setZoomLevel( 100 )"
			:label="__( 'Reset' )"
			color="ghost"
			size="sm"
			:title="__( 'Reset Zoom' )"
		/>
		<x-artisanpack-button
			wire:click="toggleGrid"
			icon="o-squares-2x2"
			:color="$showGrid ? 'primary' : 'ghost'"
			size="sm"
			:title="__( 'Toggle Grid' )"
		/>
	</div>

	{{-- Canvas Surface --}}
	<div
		x-ref="surface"
		class="relative mx-auto max-w-4xl transition-transform"
	>
		{{-- Grid Overlay --}}
		@if ( $showGrid )
			<div
				class="pointer-events-none absolute inset-0 z-0"
				style="background-image: linear-gradient(to right, rgba(209,213,219,0.3) 1px, transparent 1px), linear-gradient(to bottom, rgba(209,213,219,0.3) 1px, transparent 1px); background-size: 20px 20px;"
			></div>
		@endif

		@if ( !empty( $blocks ) )
			{{-- Blocks with drag-and-drop --}}
			<div
				x-drag-context
				@drag:end="$el._recentlyMovedKeys = []; $wire.reorderBlocks( $event.detail.orderedIds )"
				class="space-y-2"
				role="list"
				aria-label="{{ __( 'Page blocks' ) }}"
			>
				@foreach ( $blocks as $blockIndex => $block )
					@php
						$blockConfig    = app( BlockRegistry::class )->get( $block['type'] ?? '' );
						$contentSchema  = $blockConfig['content_schema'] ?? [];
						$textFieldType  = $contentSchema['text']['type'] ?? null;
						$isRichText     = 'richtext' === $textFieldType;
						$isEditableText = in_array( $textFieldType, [ 'text', 'textarea', 'richtext' ], true );
						$blockType      = $block['type'] ?? '';
						$blockId        = $block['id'] ?? '';
						$isActive       = $blockId === $activeBlockId;
						$isEditing      = $blockId === $editingBlockId && $isEditableText;
					@endphp
					<div
						x-drag-item="'{{ $blockId ?: $blockIndex }}'"
						wire:key="block-{{ $blockId ?: $blockIndex }}"
						@if ( $isEditableText )
							@click.stop="$wire.startInlineEdit( '{{ $blockId }}' )"
						@else
							@click.stop="$wire.selectBlock( '{{ $blockId }}' )"
						@endif
						class="ve-canvas-block group relative rounded px-4 py-2 transition-colors
							{{ $isActive ? 'ring-2 ring-blue-200' : '' }}"
						role="listitem"
						@if ( $isEditing && $isRichText )
							x-data="richTextEditor( { htmlContent: @js( $block['content']['text'] ?? '' ) } )"
						@endif
					>
						{{-- Global Block Toolbar (shown for any selected block) --}}
						@if ( $isActive )
							@include( 'visual-editor::livewire.partials.block-toolbar', [
								'blockId'     => $blockId,
								'blockType'   => $blockType,
								'blockConfig' => $blockConfig,
								'blockIndex'  => $blockIndex,
								'totalBlocks' => count( $blocks ),
								'isEditing'   => $isEditing,
								'isRichText'  => $isRichText,
								'block'       => $block,
							] )
						@endif

						{{-- Block Content --}}
						@if ( $isEditing )
							@if ( $isRichText )
								{{-- Rich Text Edit Mode --}}
								@php
									$editLevel   = $block['content']['level'] ?? 'h2';
									$editTag     = 'heading' === $blockType ? ( in_array( $editLevel, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $editLevel : 'h2' ) : 'div';
									$editClasses = match ( $blockType ) {
										'heading' => match ( $editTag ) {
											'h1'    => 'text-4xl font-bold',
											'h2'    => 'text-3xl font-bold',
											'h3'    => 'text-2xl font-semibold',
											'h4'    => 'text-xl font-semibold',
											'h5'    => 'text-lg font-medium',
											'h6'    => 'text-base font-medium',
											default => 'text-3xl font-bold',
										},
										default => '',
									};
									$richTextClasses = 'heading' === $blockType
										? $editClasses . ' min-h-[1.5rem] rounded px-1 outline-none ring-2 ring-blue-300'
										: 'prose prose-sm min-h-[1.5rem] max-w-none rounded px-1 outline-none ring-2 ring-blue-300';
								@endphp
								<div
									x-ref="editor"
									contenteditable="true"
									x-init="$nextTick( () => { $el.focus(); let s = window.getSelection(), r = document.createRange(); r.selectNodeContents( $el ); r.collapse( false ); s.removeAllRanges(); s.addRange( r ) } )"
									@blur="if ( !window.veNavigating && !window.veFocusingBlock ) { $wire.saveInlineEdit( '{{ $blockId }}', $el.innerHTML ) }"
									@keydown.escape.prevent="$wire.saveInlineEdit( '{{ $blockId }}', $el.innerHTML )"
									@keydown.enter.prevent="window.veNavigating = true; $wire.insertBlockAfter( '{{ $blockId }}', $el.innerHTML )"
									@keydown.tab.prevent="window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.innerHTML, $event.shiftKey ? 'up' : 'down' )"
									@keydown.arrow-up="if ( window.veAtTopOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.innerHTML, 'up' ) }"
									@keydown.arrow-down="if ( window.veAtBottomOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.innerHTML, 'down' ) }"
									@keydown.meta.b.prevent="format( 'bold' )"
									@keydown.ctrl.b.prevent="format( 'bold' )"
									@keydown.meta.i.prevent="format( 'italic' )"
									@keydown.ctrl.i.prevent="format( 'italic' )"
									@keydown.meta.u.prevent="format( 'underline' )"
									@keydown.ctrl.u.prevent="format( 'underline' )"
									class="{{ $richTextClasses }}"
								>{!! $block['content']['text'] ?? '' !!}</div>
							@else
								{{-- Plain Text Edit Mode --}}
								@php
									$editLevel   = $block['content']['level'] ?? 'h2';
									$editTag     = 'heading' === $blockType ? ( in_array( $editLevel, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $editLevel : 'h2' ) : 'div';
									$editClasses = match ( $blockType ) {
										'quote' => 'border-l-4 border-gray-300 pl-4 italic text-gray-700',
										default => '',
									};
								@endphp
								<{{ $editTag }}
									x-ref="editor"
									contenteditable="true"
									x-init="$nextTick( () => { $el.focus(); let s = window.getSelection(), r = document.createRange(); r.selectNodeContents( $el ); r.collapse( false ); s.removeAllRanges(); s.addRange( r ) } )"
									@blur="if ( !window.veNavigating && !window.veFocusingBlock ) { $wire.saveInlineEdit( '{{ $blockId }}', $el.textContent ) }"
									@keydown.escape.prevent="$wire.saveInlineEdit( '{{ $blockId }}', $el.textContent )"
									@keydown.enter.prevent="window.veNavigating = true; $wire.insertBlockAfter( '{{ $blockId }}', $el.textContent )"
									@keydown.tab.prevent="window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.textContent, $event.shiftKey ? 'up' : 'down' )"
									@keydown.arrow-up="if ( window.veAtTopOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.textContent, 'up' ) }"
									@keydown.arrow-down="if ( window.veAtBottomOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.textContent, 'down' ) }"
									class="{{ $editClasses }} min-h-[1.5rem] rounded px-1 outline-none ring-2 ring-blue-300"
								>{{ $block['content']['text'] ?? '' }}</{{ $editTag }}>
							@endif
						@else
							{{-- WYSIWYG Display Mode --}}
							@switch ( $blockType )
								@case ( 'heading' )
									@php
										$level          = $block['content']['level'] ?? 'h2';
										$headingTag     = in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $level : 'h2';
										$headingClasses = match ( $headingTag ) {
											'h1'    => 'text-4xl font-bold',
											'h2'    => 'text-3xl font-bold',
											'h3'    => 'text-2xl font-semibold',
											'h4'    => 'text-xl font-semibold',
											'h5'    => 'text-lg font-medium',
											'h6'    => 'text-base font-medium',
											default => 'text-3xl font-bold',
										};
									@endphp
									<{{ $headingTag }} class="{{ $headingClasses }}">
										@if ( '' !== ( $block['content']['text'] ?? '' ) )
											{!! kses( $block['content']['text'] ) !!}
										@else
											<span class="italic text-gray-400">{{ __( 'Type heading...' ) }}</span>
										@endif
									</{{ $headingTag }}>
									@break

								@case ( 'text' )
									<div class="prose prose-sm max-w-none">
										@if ( '' !== ( $block['content']['text'] ?? '' ) )
											{!! kses( $block['content']['text'] ) !!}
										@else
											<p class="italic text-gray-400">{{ __( 'Type text...' ) }}</p>
										@endif
									</div>
									@break

								@case ( 'quote' )
									<blockquote class="border-l-4 border-gray-300 pl-4 italic text-gray-700">
										@if ( '' !== ( $block['content']['text'] ?? '' ) )
											{{ $block['content']['text'] }}
										@else
											<span class="not-italic text-gray-400">{{ __( 'Type quote...' ) }}</span>
										@endif
										@if ( '' !== ( $block['content']['citation'] ?? '' ) )
											<cite class="mt-1 block text-sm not-italic text-gray-500">
												&mdash; {{ $block['content']['citation'] }}
											</cite>
										@endif
									</blockquote>
									@break

								@case ( 'divider' )
									<hr class="my-2 border-gray-300" />
									@break

								@case ( 'spacer' )
									@php
										$spacerSize = match ( $block['settings']['size'] ?? 'medium' ) {
											'small'  => 'h-4',
											'medium' => 'h-8',
											'large'  => 'h-16',
											'xlarge' => 'h-24',
											default  => 'h-8',
										};
									@endphp
									<div class="{{ $spacerSize }}"></div>
									@break

								@case ( 'image' )
									<div class="flex items-center justify-center rounded bg-gray-100 p-8 text-gray-400">
										<svg class="mr-2 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
										</svg>
										{{ __( 'Image block' ) }}
									</div>
									@break

								@case ( 'button' )
									<div class="py-1">
										<span class="inline-block rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white">
											{{ $block['content']['text'] ?? __( 'Button' ) }}
										</span>
									</div>
									@break

								@default
									<div class="rounded bg-gray-50 p-3 text-sm text-gray-500">
										<span class="font-medium">{{ ucfirst( $blockType ?: __( 'Block' ) ) }}</span>
									</div>
							@endswitch
						@endif
					</div>
				@endforeach
			</div>
		@endif

		{{-- Typing Area with Slash Command Menu --}}
		<div
			x-data="slashCommandInput( { blocks: @js( $this->slashMenuBlocks ) } )"
			wire:ignore
			class="ve-typing-area relative mt-2"
		>
			{{-- Slash Command Menu (positioned above the input) --}}
			<div
				x-show="menuOpen"
				x-transition:enter="transition ease-out duration-150"
				x-transition:enter-start="opacity-0 translate-y-1"
				x-transition:enter-end="opacity-100 translate-y-0"
				x-transition:leave="transition ease-in duration-100"
				x-transition:leave-start="opacity-100 translate-y-0"
				x-transition:leave-end="opacity-0 translate-y-1"
				@click.outside="closeMenu()"
				x-cloak
				class="absolute bottom-full left-0 z-50 mb-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
				role="listbox"
				aria-label="{{ __( 'Block types' ) }}"
			>
				<template x-for="( category, catIdx ) in filteredBlocks" :key="category.key">
					<div>
						<div
							class="sticky top-0 bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500"
							x-text="category.name"
						></div>
						<template x-for="( block, blockIdx ) in category.blocks" :key="block.type">
							<button
								type="button"
								@click="selectBlock( block.type )"
								@mouseenter="setActiveIndex( getFlatIndex( catIdx, blockIdx ) )"
								:class="{ 'bg-blue-50 text-blue-700': activeIndex === getFlatIndex( catIdx, blockIdx ) }"
								class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50"
								role="option"
								:aria-selected="activeIndex === getFlatIndex( catIdx, blockIdx )"
							>
								<span x-text="block.name"></span>
							</button>
						</template>
					</div>
				</template>
				<div x-show="0 === flatItems.length" class="px-3 py-4 text-center text-sm text-gray-400">
					{{ __( 'No matching blocks found' ) }}
				</div>
			</div>

			{{-- Editable Input --}}
			<div
				x-ref="typingInput"
				contenteditable="true"
				@input="handleInput( $event )"
				@keydown="handleKeydown( $event )"
				@keydown.escape.prevent="closeMenu()"
				@blur="handleBlur()"
				@focus="handleFocus()"
				class="min-h-[2.5rem] rounded border border-dashed border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 outline-none transition-colors focus:border-blue-400 focus:ring-1 focus:ring-blue-200"
				data-placeholder="{{ __( 'Type to add a block, or type / for commands...' ) }}"
			></div>
		</div>
	</div>
</div>

<style>
	[contenteditable][data-placeholder]:empty::before {
		content: attr( data-placeholder );
		color: #9ca3af;
		pointer-events: none;
	}
</style>

@script
<script>
	window.veNavigating = false
	window.veFocusingBlock = false

	Livewire.hook( 'morphed', () => {
		if ( !window.veFocusingBlock ) {
			window.veNavigating = false
		}
	} )

	const canvasHandler = ( event ) => {
		// Skip when typing area or slash menu is active
		if ( event.target.closest( '.ve-typing-area' ) ) {
			return
		}

		if ( ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) && !event.target.isContentEditable ) {
			event.preventDefault()
			$wire.dispatch( 'canvas-navigate', { direction: 'ArrowUp' === event.key ? 'up' : 'down' } )
		}

		if ( 'Tab' === event.key && !event.ctrlKey && !event.metaKey && !event.target.isContentEditable ) {
			event.preventDefault()
			$wire.dispatch( 'canvas-navigate', { direction: event.shiftKey ? 'up' : 'down' } )
		}

		if ( 'Escape' === event.key && !event.target.isContentEditable && ![ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( event.target.tagName ) ) {
			$wire.deselectAll()
		}

		if ( ( 'Delete' === event.key || 'Backspace' === event.key ) && !event.target.isContentEditable && ![ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( event.target.tagName ) ) {
			$wire.dispatch( 'canvas-delete-selected' )
		}
	}

	document.addEventListener( 'keydown', canvasHandler )

	let cleanup = () => {
		document.removeEventListener( 'keydown', canvasHandler )
	}

	document.addEventListener( 'livewire:navigating', cleanup, { once: true } )

	window.veAtTopOfElement = function( el ) {
		let sel = window.getSelection()
		if ( !sel || 0 === sel.rangeCount || !sel.isCollapsed ) return false
		let range = sel.getRangeAt( 0 )
		if ( 0 === range.startOffset && ( range.startContainer === el || range.startContainer === el.firstChild ) ) return true
		let elRect = el.getBoundingClientRect()
		let rangeRect = range.getBoundingClientRect()
		return rangeRect.top - elRect.top < ( rangeRect.height || 16 )
	}

	window.veAtBottomOfElement = function( el ) {
		let sel = window.getSelection()
		if ( !sel || 0 === sel.rangeCount || !sel.isCollapsed ) return false
		let range = sel.getRangeAt( 0 )
		let elRect = el.getBoundingClientRect()
		let rangeRect = range.getBoundingClientRect()
		if ( 0 === rangeRect.height ) {
			let tempRange = document.createRange()
			tempRange.selectNodeContents( el )
			tempRange.collapse( false )
			return range.startContainer === tempRange.startContainer && range.startOffset === tempRange.startOffset
		}
		return elRect.bottom - rangeRect.bottom < ( rangeRect.height || 16 )
	}

	Livewire.on( 'focus-typing-area', () => {
		setTimeout( () => {
			window.veNavigating = false
			let typingInput = document.querySelector( '.ve-typing-area input, .ve-typing-area [contenteditable]' )
			if ( typingInput ) {
				typingInput.focus()
			}
		}, 50 )
	} )

	Livewire.on( 'focus-block', ( { blockId } ) => {
		let startTime  = Date.now()
		let minRunTime = 800
		window.veFocusingBlock = true
		let focusInterval = setInterval( () => {
			window.veNavigating = false
			let blockEl = document.querySelector( `[wire\\:key="block-${blockId}"] [contenteditable="true"]` )
			if ( blockEl ) {
				if ( document.activeElement !== blockEl ) {
					blockEl.focus()
					let sel = window.getSelection()
					let range = document.createRange()
					range.selectNodeContents( blockEl )
					range.collapse( false )
					sel.removeAllRanges()
					sel.addRange( range )
				} else if ( Date.now() - startTime >= minRunTime ) {
					window.veFocusingBlock = false
					clearInterval( focusInterval )
				}
			}
			if ( Date.now() - startTime > 2000 ) {
				window.veFocusingBlock = false
				clearInterval( focusInterval )
			}
		}, 50 )
	} )

	Alpine.data( 'richTextEditor', ( { htmlContent } ) => ( {
		htmlContent: htmlContent || '',

		format( command ) {
			document.execCommand( command, false, null )
		},

		isActive( command ) {
			return document.queryCommandState( command )
		},

		insertLink() {
			let url = prompt( 'Enter URL:' )
			if ( url ) {
				document.execCommand( 'createLink', false, url )
			}
		},
	} ) )

	Alpine.data( 'globalBlockToolbar', () => ( {} ) )

	Alpine.data( 'slashCommandInput', ( { blocks } ) => ( {
		allBlocks: blocks,
		menuOpen: false,
		slashQuery: '',
		activeIndex: 0,
		flatItems: [],

		get filteredBlocks() {
			let categories = this.allBlocks

			if ( '' !== this.slashQuery ) {
				let query = this.slashQuery.toLowerCase()

				categories = this.allBlocks
					.map( ( cat ) => ( {
						...cat,
						blocks: cat.blocks.filter( ( b ) => {
							let nameMatch = b.name.toLowerCase().includes( query )
							let kwMatch = ( b.keywords || [] ).some(
								( kw ) => kw.toLowerCase().includes( query )
							)
							return nameMatch || kwMatch
						} ),
					} ) )
					.filter( ( cat ) => cat.blocks.length > 0 )
			}

			this.flatItems = []
			categories.forEach( ( cat, catIdx ) => {
				cat.blocks.forEach( ( block, blockIdx ) => {
					this.flatItems.push( { catIdx, blockIdx, type: block.type } )
				} )
			} )

			return categories
		},

		getFlatIndex( catIdx, blockIdx ) {
			return this.flatItems.findIndex(
				( item ) => item.catIdx === catIdx && item.blockIdx === blockIdx
			)
		},

		setActiveIndex( idx ) {
			this.activeIndex = idx
		},

		handleInput( event ) {
			let text = this.$refs.typingInput.textContent

			if ( text.startsWith( '/' ) ) {
				this.slashQuery = text.substring( 1 )
				if ( !this.menuOpen ) {
					this.menuOpen = true
					this.activeIndex = 0
				}
			} else if ( this.menuOpen ) {
				this.closeMenu()
			} else if ( '' !== text.trim() ) {
				this.$refs.typingInput.textContent = ''
				window.veNavigating = true
				this.$refs.typingInput.blur()
				$wire.insertBlockWithContent( 'text', text )
			}
		},

		handleKeydown( event ) {
			if ( this.menuOpen ) {
				if ( 'ArrowDown' === event.key ) {
					event.preventDefault()
					this.activeIndex = Math.min(
						this.activeIndex + 1,
						this.flatItems.length - 1
					)
				} else if ( 'ArrowUp' === event.key ) {
					event.preventDefault()
					this.activeIndex = Math.max( this.activeIndex - 1, 0 )
				} else if ( 'Enter' === event.key ) {
					event.preventDefault()
					if ( this.flatItems.length > 0 ) {
						this.selectBlock( this.flatItems[ this.activeIndex ].type )
					}
				} else if ( 'Escape' === event.key ) {
					this.closeMenu()
				}
				return
			}

			if ( 'Enter' === event.key && !event.shiftKey ) {
				event.preventDefault()
			}
		},

		selectBlock( type ) {
			$wire.insertBlockWithContent( type, '' )
			this.closeMenu()
			this.$refs.typingInput.textContent = ''
			this.$nextTick( () => {
				this.$refs.typingInput.focus()
				this.$refs.typingInput.scrollIntoView( { behavior: 'smooth', block: 'nearest' } )
			} )
		},

		closeMenu() {
			this.menuOpen = false
			this.slashQuery = ''
			this.activeIndex = 0
		},

		handleFocus() {},

		handleBlur() {
			setTimeout( () => {
				if ( !this.$el.contains( document.activeElement ) ) {
					this.closeMenu()
				}
			}, 150 )
		},
	} ) )
</script>
@endscript
