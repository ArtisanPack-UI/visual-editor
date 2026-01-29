<?php

declare( strict_types=1 );

/**
 * Visual Editor - Canvas
 *
 * The main editing surface where content sections and blocks
 * are rendered. Supports drag-and-drop reordering of sections
 * and blocks, inline editing, zoom controls, grid overlay,
 * and keyboard navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
	/**
	 * The content sections data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $sections = [];

	/**
	 * The ID of the currently active block.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $activeBlockId = null;

	/**
	 * The ID of the currently selected section.
	 *
	 * @since 1.1.0
	 *
	 * @var string|null
	 */
	public ?string $selectedSectionId = null;

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
	 * Select a section in the canvas.
	 *
	 * @since 1.1.0
	 *
	 * @param string $sectionId The section ID to select.
	 *
	 * @return void
	 */
	public function selectSection( string $sectionId ): void
	{
		$this->selectedSectionId = $sectionId;
		$this->activeBlockId     = null;
		$this->editingBlockId    = null;
		$this->dispatch( 'section-selected', sectionId: $sectionId );
	}

	/**
	 * Select a block in the canvas.
	 *
	 * @since 1.0.0
	 *
	 * @param string $blockId   The block ID to select.
	 * @param string $sectionId The parent section ID.
	 *
	 * @return void
	 */
	public function selectBlock( string $blockId, string $sectionId = '' ): void
	{
		$this->activeBlockId     = $blockId;
		$this->selectedSectionId = '' !== $sectionId ? $sectionId : null;
		$this->editingBlockId    = null;
		$this->dispatch( 'block-selected', blockId: $blockId );
	}

	/**
	 * Deselect all blocks and sections.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function deselectAll(): void
	{
		$this->activeBlockId     = null;
		$this->selectedSectionId = null;
		$this->editingBlockId    = null;
	}

	/**
	 * Handle drag-and-drop reordering of sections.
	 *
	 * Accepts an array of section IDs in the new order,
	 * as provided by the x-drag-context drag:end event.
	 *
	 * @since 1.0.0
	 *
	 * @param array $orderedIds The new section ID order.
	 *
	 * @return void
	 */
	public function reorderSections( array $orderedIds ): void
	{
		$indexed   = collect( $this->sections )->keyBy( 'id' );
		$reordered = [];

		foreach ( $orderedIds as $id ) {
			if ( $indexed->has( $id ) ) {
				$reordered[] = $indexed->get( $id );
			}
		}

		$this->sections = $reordered;
		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Handle drag-and-drop reordering of blocks within a section.
	 *
	 * @since 1.1.0
	 *
	 * @param string $sectionId  The section containing the blocks.
	 * @param array  $orderedIds The new block ID order.
	 *
	 * @return void
	 */
	public function reorderBlocks( string $sectionId, array $orderedIds ): void
	{
		foreach ( $this->sections as &$section ) {
			if ( ( $section['id'] ?? '' ) === $sectionId ) {
				$indexed   = collect( $section['blocks'] ?? [] )->keyBy( 'id' );
				$reordered = [];

				foreach ( $orderedIds as $id ) {
					if ( $indexed->has( $id ) ) {
						$reordered[] = $indexed->get( $id );
					}
				}

				$section['blocks'] = $reordered;
				break;
			}
		}

		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Move a block from one section to another.
	 *
	 * @since 1.1.0
	 *
	 * @param string $blockId       The block ID to move.
	 * @param string $fromSectionId The source section ID.
	 * @param string $toSectionId   The target section ID.
	 * @param int    $targetIndex   The insertion index in the target section.
	 *
	 * @return void
	 */
	public function moveBlockBetweenSections(
		string $blockId,
		string $fromSectionId,
		string $toSectionId,
		int $targetIndex
	): void {
		$movedBlock = null;

		// Remove from source section.
		foreach ( $this->sections as &$section ) {
			if ( ( $section['id'] ?? '' ) === $fromSectionId ) {
				$blocks            = $section['blocks'] ?? [];
				$movedBlock        = collect( $blocks )->firstWhere( 'id', $blockId );
				$section['blocks'] = array_values(
					array_filter( $blocks, fn ( $b ) => ( $b['id'] ?? '' ) !== $blockId )
				);
				break;
			}
		}

		if ( null === $movedBlock ) {
			return;
		}

		// Insert into target section.
		foreach ( $this->sections as &$section ) {
			if ( ( $section['id'] ?? '' ) === $toSectionId ) {
				$blocks = $section['blocks'] ?? [];
				array_splice( $blocks, $targetIndex, 0, [ $movedBlock ] );
				$section['blocks'] = $blocks;
				break;
			}
		}

		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Handle a block insert event from the sidebar.
	 *
	 * If a section is selected, the block is added to that section.
	 * Otherwise, a new generic section is created to wrap the block.
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
		$newBlock = [
			'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
			'type'     => $type,
			'content'  => [],
			'settings' => [],
		];

		if ( null !== $this->selectedSectionId ) {
			foreach ( $this->sections as &$section ) {
				if ( ( $section['id'] ?? '' ) === $this->selectedSectionId ) {
					$section['blocks']   = $section['blocks'] ?? [];
					$section['blocks'][] = $newBlock;
					break;
				}
			}
		} else {
			$this->sections[] = [
				'id'       => str_replace( '.', '-', uniqid( 've-section-', true ) ),
				'type'     => 'generic',
				'blocks'   => [ $newBlock ],
				'settings' => [],
			];
		}

		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Handle a section insert event from the sidebar.
	 *
	 * Creates a new section with default blocks from the SectionRegistry.
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

		$blocks = [];
		if ( null !== $config ) {
			foreach ( $config['default_blocks'] ?? [] as $blockDef ) {
				$blocks[] = [
					'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
					'type'     => $blockDef['type'] ?? 'text',
					'content'  => $blockDef['content'] ?? [],
					'settings' => [],
				];
			}
		}

		$this->sections[] = [
			'id'       => str_replace( '.', '-', uniqid( 've-section-', true ) ),
			'type'     => $type,
			'blocks'   => $blocks,
			'settings' => $config['default_settings'] ?? [],
		];

		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Delete a section from the canvas.
	 *
	 * @since 1.1.0
	 *
	 * @param string $sectionId The section ID to delete.
	 *
	 * @return void
	 */
	public function deleteSection( string $sectionId ): void
	{
		$this->sections = array_values(
			array_filter( $this->sections, fn ( $s ) => ( $s['id'] ?? '' ) !== $sectionId )
		);

		if ( $this->selectedSectionId === $sectionId ) {
			$this->selectedSectionId = null;
			$this->activeBlockId     = null;
			$this->editingBlockId    = null;
		}

		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Delete a block from its parent section.
	 *
	 * @since 1.1.0
	 *
	 * @param string $blockId   The block ID to delete.
	 * @param string $sectionId The parent section ID.
	 *
	 * @return void
	 */
	public function deleteBlock( string $blockId, string $sectionId ): void
	{
		foreach ( $this->sections as &$section ) {
			if ( ( $section['id'] ?? '' ) === $sectionId ) {
				$section['blocks'] = array_values(
					array_filter( $section['blocks'] ?? [], fn ( $b ) => ( $b['id'] ?? '' ) !== $blockId )
				);
				break;
			}
		}

		if ( $this->activeBlockId === $blockId ) {
			$this->activeBlockId  = null;
			$this->editingBlockId = null;
		}

		$this->dispatch( 'sections-updated', sections: $this->sections );
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
	 * @param string $blockId   The block ID being edited.
	 * @param string $sectionId The parent section ID.
	 * @param string $content   The updated text content.
	 *
	 * @return void
	 */
	public function saveInlineEdit( string $blockId, string $sectionId, string $content ): void
	{
		foreach ( $this->sections as &$section ) {
			if ( ( $section['id'] ?? '' ) === $sectionId ) {
				foreach ( $section['blocks'] as &$block ) {
					if ( ( $block['id'] ?? '' ) === $blockId ) {
						$block['content']['text'] = $content;
						break;
					}
				}
				break;
			}
		}

		$this->editingBlockId = null;
		$this->dispatch( 'sections-updated', sections: $this->sections );
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
		$allBlocks = [];

		foreach ( $this->sections as $section ) {
			foreach ( $section['blocks'] ?? [] as $block ) {
				$allBlocks[] = [
					'blockId'   => $block['id'] ?? '',
					'sectionId' => $section['id'] ?? '',
				];
			}
		}

		if ( empty( $allBlocks ) ) {
			return;
		}

		$currentIndex = null;

		foreach ( $allBlocks as $i => $item ) {
			if ( $item['blockId'] === $this->activeBlockId ) {
				$currentIndex = $i;
				break;
			}
		}

		if ( null === $currentIndex ) {
			$target = 'up' === $direction
				? $allBlocks[ count( $allBlocks ) - 1 ]
				: $allBlocks[0];
		} elseif ( 'up' === $direction ) {
			$target = $allBlocks[ max( 0, $currentIndex - 1 ) ];
		} else {
			$target = $allBlocks[ min( count( $allBlocks ) - 1, $currentIndex + 1 ) ];
		}

		$this->activeBlockId     = $target['blockId'];
		$this->selectedSectionId = $target['sectionId'];
		$this->editingBlockId    = null;
	}

	/**
	 * Handle keyboard deletion of selected block or section.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	#[On( 'canvas-delete-selected' )]
	public function deleteSelected(): void
	{
		if ( null !== $this->activeBlockId && null !== $this->selectedSectionId ) {
			$this->deleteBlock( $this->activeBlockId, $this->selectedSectionId );
		} elseif ( null !== $this->selectedSectionId ) {
			$this->deleteSection( $this->selectedSectionId );
		}
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

		@if ( empty( $sections ) )
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
			{{-- Sections with drag-and-drop --}}
			<div
				x-drag-context
				@drag:end="$wire.reorderSections( $event.detail.orderedIds )"
				class="space-y-4"
				role="list"
				aria-label="{{ __( 'Page sections' ) }}"
			>
				@foreach ( $sections as $index => $section )
					<div
						x-drag-item="'{{ $section['id'] ?? $index }}'"
						wire:key="section-{{ $section['id'] ?? $index }}"
						@click.stop="$wire.selectSection( '{{ $section['id'] ?? '' }}' )"
						class="ve-canvas-section group relative rounded-lg border bg-white shadow-sm transition-colors
							{{ ( $section['id'] ?? '' ) === $selectedSectionId ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300' }}"
						role="listitem"
					>
						{{-- Section Toolbar --}}
						<div class="absolute -top-3 left-4 z-10 flex items-center gap-1 rounded bg-gray-800 px-2 py-0.5 text-xs text-white
							{{ ( $section['id'] ?? '' ) === $selectedSectionId ? 'visible' : 'invisible group-hover:visible' }}">
							<span class="cursor-grab" aria-label="{{ __( 'Drag to reorder section' ) }}">
								<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
								</svg>
							</span>
							<span>{{ ucfirst( $section['type'] ?? __( 'Section' ) ) }}</span>
							<button
								wire:click.stop="deleteSection( '{{ $section['id'] ?? '' }}' )"
								class="ml-1 text-red-300 hover:text-red-100"
								title="{{ __( 'Delete Section' ) }}"
							>
								<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
								</svg>
							</button>
						</div>

						{{-- Blocks within section --}}
						<div
							x-drag-context
							@drag:end.stop="$wire.reorderBlocks( '{{ $section['id'] ?? '' }}', $event.detail.orderedIds )"
							class="space-y-2 p-4"
							role="list"
							aria-label="{{ __( 'Section blocks' ) }}"
						>
							@forelse ( $section['blocks'] ?? [] as $blockIndex => $block )
								<div
									x-drag-item="'{{ $block['id'] ?? $blockIndex }}'"
									wire:key="block-{{ $block['id'] ?? $blockIndex }}"
									@click.stop="$wire.selectBlock( '{{ $block['id'] ?? '' }}', '{{ $section['id'] ?? '' }}' )"
									@dblclick.stop="$wire.startInlineEdit( '{{ $block['id'] ?? '' }}' )"
									class="ve-canvas-block relative cursor-pointer rounded border p-3 transition-colors
										{{ ( $block['id'] ?? '' ) === $activeBlockId ? 'border-blue-500 ring-2 ring-blue-200' : 'border-transparent hover:border-gray-300' }}"
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
												@blur="$wire.saveInlineEdit( '{{ $block['id'] ?? '' }}', '{{ $section['id'] ?? '' }}', $el.textContent )"
												@keydown.escape.prevent="$wire.saveInlineEdit( '{{ $block['id'] ?? '' }}', '{{ $section['id'] ?? '' }}', $el.textContent )"
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
													wire:click.stop="deleteBlock( '{{ $block['id'] ?? '' }}', '{{ $section['id'] ?? '' }}' )"
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
							@empty
								<div class="py-4 text-center text-xs text-gray-400">
									{{ __( 'Empty section — add blocks from the sidebar.' ) }}
								</div>
							@endforelse
						</div>
					</div>
				@endforeach
			</div>
		@endif
	</div>
</div>

@script
<script>
	const canvasHandler = ( event ) => {
		if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) {
			event.preventDefault();
			$wire.dispatch( 'canvas-navigate', { direction: 'ArrowUp' === event.key ? 'up' : 'down' } );
		}

		if ( 'Tab' === event.key && !event.ctrlKey && !event.metaKey ) {
			event.preventDefault();
			$wire.dispatch( 'canvas-navigate', { direction: event.shiftKey ? 'up' : 'down' } );
		}

		if ( 'Escape' === event.key ) {
			$wire.deselectAll();
		}

		if ( ( 'Delete' === event.key || 'Backspace' === event.key ) && !event.target.isContentEditable ) {
			$wire.dispatch( 'canvas-delete-selected' );
		}
	};

	document.addEventListener( 'keydown', canvasHandler );

	const cleanup = () => {
		document.removeEventListener( 'keydown', canvasHandler );
	};

	document.addEventListener( 'livewire:navigating', cleanup, { once: true } );
</script>
@endscript
