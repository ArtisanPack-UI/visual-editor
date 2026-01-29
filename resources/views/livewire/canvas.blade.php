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
			array_filter( $this->blocks, fn ( $b ) => ( $b['id'] ?? '' ) !== $blockId )
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

		$this->activeBlockId  = $this->blocks[ $targetIndex ]['id'] ?? '';
		$this->editingBlockId = null;
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
	 * or from the slash command menu.
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
		$this->dispatch( 'blocks-updated', blocks: $this->blocks );
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
		class="relative mx-auto max-w-4xl transition-transform"
		x-bind:style="'transform: scale(' + ( {{ $zoomLevel }} / 100 ) + ') translate(' + panX + 'px, ' + panY + 'px); transform-origin: top center;'"
	>
		{{-- Grid Overlay --}}
		@if ( $showGrid )
			<div
				class="pointer-events-none absolute inset-0 z-0"
				style="background-image: linear-gradient(to right, rgba(209,213,219,0.3) 1px, transparent 1px), linear-gradient(to bottom, rgba(209,213,219,0.3) 1px, transparent 1px); background-size: 20px 20px;"
			></div>
		@endif

		@if ( empty( $blocks ) )
			{{-- Empty State --}}
			<div class="flex min-h-96 flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white p-12 text-center">
				<svg class="mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
				</svg>
				<h3 class="mb-1 text-lg font-medium text-gray-900">
					{{ __( 'Start building your page' ) }}
				</h3>
				<p class="text-sm text-gray-500">
					{{ __( 'Add blocks from the sidebar to begin creating content.' ) }}
				</p>
			</div>
		@else
			{{-- Blocks with drag-and-drop --}}
			<div
				x-drag-context
				@drag:end="$wire.reorderBlocks( $event.detail.orderedIds )"
				class="space-y-2"
				role="list"
				aria-label="{{ __( 'Page blocks' ) }}"
			>
				@foreach ( $blocks as $blockIndex => $block )
					<div
						x-drag-item="'{{ $block['id'] ?? $blockIndex }}'"
						wire:key="block-{{ $block['id'] ?? $blockIndex }}"
						@click.stop="$wire.selectBlock( '{{ $block['id'] ?? '' }}' )"
						@dblclick.stop="$wire.startInlineEdit( '{{ $block['id'] ?? '' }}' )"
						class="ve-canvas-block relative cursor-pointer rounded border bg-white p-3 shadow-sm transition-colors
							{{ ( $block['id'] ?? '' ) === $activeBlockId ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300' }}"
						role="listitem"
					>
						@if ( ( $block['id'] ?? '' ) === $editingBlockId && in_array( $block['type'] ?? '', [ 'heading', 'text', 'quote' ], true ) )
							{{-- Inline Edit Mode --}}
							<div
								x-data="{ content: @js( $block['content']['text'] ?? '' ) }"
								x-init="$nextTick( () => $refs.editor.focus() )"
							>
								<div
									x-ref="editor"
									contenteditable="true"
									x-text="content"
									@blur="$wire.saveInlineEdit( '{{ $block['id'] ?? '' }}', $el.textContent )"
									@keydown.escape.prevent="$wire.saveInlineEdit( '{{ $block['id'] ?? '' }}', $el.textContent )"
									class="min-h-[1.5rem] rounded px-1 outline-none ring-2 ring-blue-300"
								></div>
							</div>
						@else
							{{-- Display Mode --}}
							<div class="flex items-center gap-2">
								<span class="cursor-grab text-gray-400" aria-label="{{ __( 'Drag to reorder block' ) }}">
									<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
									</svg>
								</span>
								<span class="text-xs font-medium uppercase tracking-wider text-gray-400">
									{{ ucfirst( $block['type'] ?? __( 'Block' ) ) }}
								</span>
								@if ( ( $block['id'] ?? '' ) === $activeBlockId )
									<button
										wire:click.stop="deleteBlock( '{{ $block['id'] ?? '' }}' )"
										class="ml-auto text-red-400 hover:text-red-600"
										title="{{ __( 'Delete Block' ) }}"
									>
										<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
										</svg>
									</button>
								@endif
							</div>
							<div class="mt-1 text-sm text-gray-600">
								{{ $block['content']['text'] ?? __( 'Block content area' ) }}
							</div>
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
	const canvasHandler = ( event ) => {
		// Skip when typing area or slash menu is active
		if ( event.target.closest( '.ve-typing-area' ) ) {
			return
		}

		if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) {
			event.preventDefault()
			$wire.dispatch( 'canvas-navigate', { direction: 'ArrowUp' === event.key ? 'up' : 'down' } )
		}

		if ( 'Tab' === event.key && !event.ctrlKey && !event.metaKey ) {
			event.preventDefault()
			$wire.dispatch( 'canvas-navigate', { direction: event.shiftKey ? 'up' : 'down' } )
		}

		if ( 'Escape' === event.key ) {
			$wire.deselectAll()
		}

		if ( ( 'Delete' === event.key || 'Backspace' === event.key ) && !event.target.isContentEditable ) {
			$wire.dispatch( 'canvas-delete-selected' )
		}
	}

	document.addEventListener( 'keydown', canvasHandler )

	let cleanup = () => {
		document.removeEventListener( 'keydown', canvasHandler )
	}

	document.addEventListener( 'livewire:navigating', cleanup, { once: true } )

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
				let text = this.$refs.typingInput.textContent.trim()
				if ( '' !== text ) {
					$wire.insertBlockWithContent( 'text', text )
					this.$refs.typingInput.textContent = ''
					this.$nextTick( () => {
						this.$refs.typingInput.scrollIntoView( { behavior: 'smooth', block: 'nearest' } )
					} )
				}
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
