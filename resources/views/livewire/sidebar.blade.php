<?php

declare( strict_types=1 );

/**
 * Visual Editor - Sidebar
 *
 * Collapsible sidebar panel with tabs for blocks, sections,
 * layers, and settings. Provides block/section insertion
 * and navigation capabilities.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Models\UserSection;
use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
	/**
	 * Whether the sidebar is open.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $isOpen = true;

	/**
	 * The active sidebar tab.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $activeTab = 'blocks';

	/**
	 * Initialize the component.
	 *
	 * @since 2.0.0
	 *
	 * @param string $activeTab Initial active tab from parent.
	 *
	 * @return void
	 */
	public function mount( string $activeTab = 'blocks' ): void
	{
		// Store initial tab in Alpine-managed state
		$this->activeTab = $activeTab;
	}

	/**
	 * The block search query.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $blockSearch = '';

	/**
	 * The section search query.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public string $sectionSearch = '';

	/**
	 * The content blocks for the layers tab.
	 *
	 * @since 1.4.0
	 *
	 * @var array
	 */
	#[Reactive]
	public array $blocks = [];

	/**
	 * The currently active block ID.
	 *
	 * @since 1.4.0
	 *
	 * @var string|null
	 */
	#[Reactive]
	public ?string $activeBlockId = null;

	/**
	 * The block type for the variation picker.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	public ?string $variationPickerBlock = null;

	/**
	 * Get blocks grouped by category, filtered by search.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	#[Computed]
	public function groupedBlocks(): Collection
	{
		$registry = veBlocks();
		$grouped  = $registry->getGroupedByCategory();

		// Expand blocks with variations into separate entries and filter out blocks with parent constraints
		$grouped = $grouped->map( function ( $category ) use ( $registry ) {
			$expandedBlocks = collect();

			foreach ( $category['blocks'] as $blockType => $block ) {
				// Skip blocks that have parent constraints (only allowed inside specific blocks)
				if ( ! empty( $block['parent'] ) ) {
					continue;
				}

				if ( $registry->hasVariations( $blockType ) ) {
					// Add each variation as a separate block entry
					$variations = $registry->getVariations( $blockType );

					foreach ( $variations as $variationName => $variation ) {
						$expandedBlocks->put( $blockType . ':' . $variationName, [
							'name'         => $variation['title'] ?? $block['name'],
							'description'  => $variation['description'] ?? $block['description'],
							'icon'         => $variation['icon'] ?? $block['icon'],
							'keywords'     => $block['keywords'] ?? [],
							'blockType'    => $blockType,
							'variation'    => $variationName,
							'isVariation'  => true,
						] );
					}
				} else {
					// Keep regular blocks as-is
					$expandedBlocks->put( $blockType, array_merge( $block, [
						'blockType'   => $blockType,
						'variation'   => null,
						'isVariation' => false,
					] ) );
				}
			}

			return array_merge( $category, [ 'blocks' => $expandedBlocks ] );
		} );

		if ( '' === $this->blockSearch ) {
			return $grouped;
		}

		$search = strtolower( $this->blockSearch );

		return $grouped->map( function ( $category ) use ( $search ) {
			$filtered = $category['blocks']->filter( function ( $block ) use ( $search ) {
				$name     = strtolower( $block['name'] ?? '' );
				$keywords = array_map( 'strtolower', $block['keywords'] ?? [] );

				return str_contains( $name, $search )
					|| collect( $keywords )->contains( fn ( $kw ) => str_contains( $kw, $search ) );
			} );

			return array_merge( $category, [ 'blocks' => $filtered ] );
		} )->filter( fn ( $category ) => $category['blocks']->isNotEmpty() );
	}

	/**
	 * Get sections grouped by category, filtered by search.
	 *
	 * @since 1.1.0
	 *
	 * @return Collection
	 */
	#[Computed]
	public function groupedSections(): Collection
	{
		$grouped = app( SectionRegistry::class )->getGroupedByCategory();

		if ( '' === $this->sectionSearch ) {
			return $grouped;
		}

		$search = strtolower( $this->sectionSearch );

		return $grouped->map( function ( $category ) use ( $search ) {
			$filtered = $category['sections']->filter( function ( $section ) use ( $search ) {
				$name        = strtolower( $section['name'] ?? '' );
				$description = strtolower( $section['description'] ?? '' );

				return str_contains( $name, $search ) || str_contains( $description, $search );
			} );

			return array_merge( $category, [ 'sections' => $filtered ] );
		} )->filter( fn ( $category ) => $category['sections']->isNotEmpty() );
	}

	/**
	 * Get user-created section patterns.
	 *
	 * @since 1.1.0
	 *
	 * @return Collection
	 */
	#[Computed]
	public function userSections(): Collection
	{
		if ( null === auth()->id() ) {
			return collect();
		}

		$query = UserSection::query()
			->where( 'user_id', auth()->id() )
			->orWhere( 'is_shared', true )
			->orderBy( 'name' );

		if ( '' !== $this->sectionSearch ) {
			$search  = str_replace( [ '\\', '%', '_' ], [ '\\\\', '\\%', '\\_' ], $this->sectionSearch );
			$pattern = '%' . $search . '%';
			$query->where( function ( $q ) use ( $pattern ): void {
				$q->whereRaw( 'name LIKE ? ESCAPE ?', [ $pattern, '\\' ] )
					->orWhereRaw( 'description LIKE ? ESCAPE ?', [ $pattern, '\\' ] );
			} );
		}

		return $query->get();
	}

	/**
	 * Toggle the sidebar open/closed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function toggle(): void
	{
		$this->isOpen = !$this->isOpen;
	}

	/**
	 * Set the active tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab The tab to activate.
	 *
	 * @return void
	 */
	public function setTab( string $tab ): void
	{
		$this->activeTab = $tab;
	}

	/**
	 * Insert a block into the canvas.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $blockType The block type to insert.
	 * @param string|null $variation The variation name to use (optional).
	 *
	 * @return void
	 */
	public function insertBlock( string $blockType, ?string $variation = null ): void
	{
		$this->dispatch( 'block-insert', type: $blockType, variation: $variation );
		$this->variationPickerBlock = null;
	}

	/**
	 * Show the variation picker for a block.
	 *
	 * @since 2.0.0
	 *
	 * @param string $blockType The block type to show variations for.
	 *
	 * @return void
	 */
	public function showVariationPicker( string $blockType ): void
	{
		$this->variationPickerBlock = $blockType;
	}

	/**
	 * Close the variation picker.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function closeVariationPicker(): void
	{
		$this->variationPickerBlock = null;
	}

	/**
	 * Select a block from the layers tab.
	 *
	 * @since 1.4.0
	 *
	 * @param string $blockId The block ID to select.
	 *
	 * @return void
	 */
	public function selectLayerBlock( string $blockId ): void
	{
		$this->dispatch( 'block-selected', blockId: $blockId );
	}

	/**
	 * Reorder blocks from the layers tab via drag and drop.
	 *
	 * @since 1.4.0
	 *
	 * @param array $orderedIds The block IDs in their new order.
	 *
	 * @return void
	 */
	public function reorderLayerBlocks( array $orderedIds ): void
	{
		$indexed   = collect( $this->blocks )->keyBy( 'id' );
		$reordered = [];

		foreach ( $orderedIds as $id ) {
			if ( $indexed->has( $id ) ) {
				$reordered[] = $indexed->get( $id );
			}
		}

		$this->dispatch( 'layers-reordered', blocks: $reordered );
	}

	/**
	 * Dispatch cross-context drop event from layers panel to canvas.
	 *
	 * @since 2.1.0
	 *
	 * @param array $detail The cross-context drop detail.
	 *
	 * @return void
	 */
	public function dispatchCrossContextDrop( array $detail ): void
	{
		$this->dispatch( 'layers-cross-context-drop', detail: $detail );
	}

	/**
	 * Dispatch column reorder event from layers panel to canvas.
	 *
	 * @since 2.1.0
	 *
	 * @param string $parentBlockId The parent block ID.
	 * @param array  $newOrder      The new column order.
	 *
	 * @return void
	 */
	public function dispatchColumnReorder( string $parentBlockId, array $newOrder ): void
	{
		$this->dispatch( 'layers-column-reorder', parentBlockId: $parentBlockId, newOrder: $newOrder );
	}
}; ?>

<div class="ve-sidebar flex w-72 flex-col border-r border-gray-200 bg-white"
	 x-data="{ sidebarTab: $persist('{{ $activeTab }}').as('ve-sidebar-tab') }"
	 @if ( !$isOpen ) style="display: none;" @endif>
	{{-- Sidebar Tabs --}}
	<div class="flex border-b border-gray-200">
		@php
			$tabs = [
				'blocks'   => __( 'Blocks' ),
				'sections' => __( 'Sections' ),
				'layers'   => __( 'Layers' ),
			];
		@endphp
		@foreach ( $tabs as $key => $label )
			<button
				@click="sidebarTab = '{{ $key }}'"
				:class="sidebarTab === '{{ $key }}' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
				class="flex-1 border-b-2 px-3 py-2 text-center text-xs font-medium transition-colors"
			>
				{{ $label }}
			</button>
		@endforeach
	</div>

	{{-- Tab Content --}}
	<div class="flex-1 overflow-y-auto p-3">
		<div x-show="sidebarTab === 'blocks'">
			{{-- Block Search --}}
			<div class="mb-3">
				<input
					wire:model.live.debounce.300ms="blockSearch"
					type="text"
					class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
					placeholder="{{ __( 'Search blocks...' ) }}"
				/>
			</div>

			{{-- Blocks by Category --}}
			@foreach ( $this->groupedBlocks as $categoryKey => $category )
				<div class="mb-4">
					<h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
						{{ $category['name'] }}
					</h3>
					<div class="grid grid-cols-2 gap-2">
						@foreach ( $category['blocks'] as $blockKey => $block )
							<button
								wire:click="insertBlock( '{{ $block['blockType'] }}', {{ $block['variation'] ? "'" . $block['variation'] . "'" : 'null' }} )"
								class="flex flex-col items-center gap-1 rounded-md border border-gray-200 p-2 text-center text-xs text-gray-700 hover:border-blue-300 hover:bg-blue-50 relative"
							>
								<x-artisanpack-icon name="{{ $block['icon'] ?? 'fas.cube' }}" class="w-5 h-5 text-gray-400" />
								<span>{{ $block['name'] }}</span>
							</button>
						@endforeach
					</div>
				</div>
			@endforeach
		</div>

		<div x-show="sidebarTab === 'sections'">
			{{-- Section Search --}}
			<div class="mb-3">
				<input
					wire:model.live.debounce.300ms="sectionSearch"
					type="text"
					class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
					placeholder="{{ __( 'Search sections...' ) }}"
				/>
			</div>

			{{-- Sections by Category --}}
			@foreach ( $this->groupedSections as $categoryKey => $category )
				<div class="mb-4">
					<h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
						{{ $category['name'] }}
					</h3>
					<div class="space-y-2">
						@foreach ( $category['sections'] as $sectionType => $section )
							<button
								wire:click="$dispatch( 'section-insert', { type: '{{ $sectionType }}' } )"
								class="flex w-full items-start gap-2 rounded-md border border-gray-200 p-2 text-left text-sm text-gray-700 hover:border-blue-300 hover:bg-blue-50"
							>
								<x-artisanpack-icon name="{{ $section['icon'] ?? 'fas.layer-group' }}" class="mt-0.5 w-5 h-5 shrink-0 text-gray-400" />
								<div class="min-w-0">
									<span class="block font-medium">{{ $section['name'] }}</span>
									@if ( !empty( $section['description'] ) )
										<span class="block truncate text-xs text-gray-400">{{ $section['description'] }}</span>
									@endif
								</div>
							</button>
						@endforeach
					</div>
				</div>
			@endforeach

			{{-- User Sections (My Patterns) --}}
			@if ( $this->userSections->isNotEmpty() )
				<div class="mb-4 mt-6 border-t border-gray-200 pt-4">
					<h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
						{{ __( 'My Patterns' ) }}
					</h3>
					<div class="space-y-2">
						@foreach ( $this->userSections as $userSection )
							<button
								wire:click="$dispatch( 'user-section-insert', { userSectionId: {{ $userSection->id }} } )"
								class="flex w-full items-start gap-2 rounded-md border border-gray-200 p-2 text-left text-sm text-gray-700 hover:border-blue-300 hover:bg-blue-50"
							>
								<x-artisanpack-icon name="fas.puzzle-piece" class="mt-0.5 w-5 h-5 shrink-0 text-gray-400" />
								<div class="min-w-0">
									<span class="block font-medium">{{ $userSection->name }}</span>
									@if ( !empty( $userSection->description ) )
										<span class="block truncate text-xs text-gray-400">{{ $userSection->description }}</span>
									@endif
								</div>
							</button>
						@endforeach
					</div>
				</div>
			@endif
		</div>

		<div x-show="sidebarTab === 'layers'">
			@if ( empty( $blocks ) )
				<p class="text-sm text-gray-500">{{ __( 'No blocks on the canvas yet.' ) }}</p>
			@else
				<div
					x-drag-context
					x-drag-group="visual-editor-blocks"
					@drag:end="$wire.reorderLayerBlocks( $event.detail.orderedIds )"
					@drag:cross-context="console.log('Top-level layers cross-context:', $event.detail); $wire.dispatchCrossContextDrop( $event.detail )"
					class="space-y-1"
					role="list"
					aria-label="{{ __( 'Layer order' ) }}"
				>
					@foreach ( $blocks as $block )
						@include( 'visual-editor::livewire.partials.layer-item', [
							'block'         => $block,
							'activeBlockId' => $activeBlockId,
							'depth'         => 0,
						] )
					@endforeach
				</div>
			@endif
		</div>
	</div>
</div>

{{-- Variation Picker Modal --}}
@if ( $variationPickerBlock )
	@php
		$blockConfig = veBlocks()->get( $variationPickerBlock );
		$variations  = veBlocks()->getVariations( $variationPickerBlock );
	@endphp
	<div
		class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50"
		style="position: fixed !important;"
		wire:click.self="closeVariationPicker()"
	>
		<div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
			<div class="mb-4 flex items-center justify-between">
				<h3 class="text-lg font-semibold text-gray-900">
					{{ __( 'Choose a variation' ) }}
				</h3>
				<button
					wire:click="closeVariationPicker()"
					class="text-gray-400 hover:text-gray-600"
					aria-label="{{ __( 'Close' ) }}"
				>
					<x-artisanpack-icon name="fas.times" class="h-5 w-5" />
				</button>
			</div>

			<div class="space-y-2">
				@foreach ( $variations as $variationName => $variation )
					<button
						wire:click="insertBlock( '{{ $variationPickerBlock }}', '{{ $variationName }}' )"
						class="flex w-full items-start gap-3 rounded-md border border-gray-200 p-3 text-left hover:border-blue-300 hover:bg-blue-50"
					>
						<div class="mt-0.5 shrink-0">
							<x-artisanpack-icon
								name="{{ $variation['icon'] ?? $blockConfig['icon'] ?? 'fas.cube' }}"
								class="h-6 w-6 text-gray-400"
							/>
						</div>
						<div class="min-w-0">
							<div class="font-medium text-gray-900">{{ $variation['title'] }}</div>
							@if ( !empty( $variation['description'] ) )
								<div class="mt-0.5 text-sm text-gray-500">{{ $variation['description'] }}</div>
							@endif
						</div>
					</button>
				@endforeach
			</div>
		</div>
	</div>
@endif

<script>
	// Reset sidebar tab to default on true page loads only (not Livewire navigations)
	if ( !sessionStorage.getItem( 've-page-loaded' ) ) {
		localStorage.removeItem( '_x_ve-sidebar-tab' )
		sessionStorage.setItem( 've-page-loaded', 'true' )
	}
</script>

<script>
	// Preserve focus during Livewire updates to prevent layer items from stealing focus
	const setupFocusPreservation = () => {
		let savedElementSelector = null

		Livewire.hook( 'morph.updating', ( { component } ) => {
			if ( component.name && component.name.startsWith( 'visual-editor::' ) ) {
				const activeEl = document.activeElement

				// Don't save focus if on typing input (slash command area)
				const isTypingInput = activeEl?.classList?.contains( 'min-h-[2.5rem]' ) &&
				                      activeEl?.classList?.contains( 'border-dashed' )

				if ( activeEl && activeEl.isContentEditable && activeEl.closest( '.ve-canvas' ) && !isTypingInput ) {
					const blockContainer = activeEl.closest( '[wire\\:key^="block-"]' )
					if ( blockContainer ) {
						const wireKey = blockContainer.getAttribute( 'wire:key' )
						savedElementSelector = `[wire\\:key="${wireKey}"] [contenteditable="true"]`
					}
				} else {
					savedElementSelector = null
				}
			}
		} )

		Livewire.hook( 'morph.updated', ( { component } ) => {
			if ( component.name && component.name.startsWith( 'visual-editor::' ) && savedElementSelector ) {
				const elementToRestore = document.querySelector( savedElementSelector )

				if ( elementToRestore && document.activeElement !== elementToRestore && !window.veFocusingBlock ) {
					elementToRestore.focus()
				}

				savedElementSelector = null
			}
		} )
	}

	if ( typeof Livewire !== 'undefined' ) {
		setupFocusPreservation()
	} else {
		document.addEventListener( 'livewire:init', setupFocusPreservation )
	}
</script>
