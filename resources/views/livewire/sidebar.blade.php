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

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
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
	 * The block search query.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $blockSearch = '';

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
	 * Get blocks grouped by category, filtered by search.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	#[Computed]
	public function groupedBlocks(): Collection
	{
		$registry = app( BlockRegistry::class );
		$grouped  = $registry->getGroupedByCategory();

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
	 * Get sections grouped by category.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	#[Computed]
	public function groupedSections(): Collection
	{
		return app( SectionRegistry::class )->getGroupedByCategory();
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
	 * @param string $blockType The block type to insert.
	 *
	 * @return void
	 */
	public function insertBlock( string $blockType ): void
	{
		$this->dispatch( 'block-insert', type: $blockType );
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
}; ?>

<div class="ve-sidebar flex w-72 flex-col border-r border-gray-200 bg-white"
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
				wire:click="setTab( '{{ $key }}' )"
				class="flex-1 border-b-2 px-3 py-2 text-center text-xs font-medium transition-colors
					{{ $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
			>
				{{ $label }}
			</button>
		@endforeach
	</div>

	{{-- Tab Content --}}
	<div class="flex-1 overflow-y-auto p-3">
		@if ( 'blocks' === $activeTab )
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
						@foreach ( $category['blocks'] as $blockType => $block )
							<button
								wire:click="insertBlock( '{{ $blockType }}' )"
								class="flex flex-col items-center gap-1 rounded-md border border-gray-200 p-2 text-center text-xs text-gray-700 hover:border-blue-300 hover:bg-blue-50"
							>
								<x-artisanpack-icon name="{{ $block['icon'] ?? 'fas.cube' }}" class="w-5 h-5 text-gray-400" />
								<span>{{ $block['name'] }}</span>
							</button>
						@endforeach
					</div>
				</div>
			@endforeach
		@elseif ( 'sections' === $activeTab )
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
								class="flex w-full items-center gap-2 rounded-md border border-gray-200 p-2 text-left text-sm text-gray-700 hover:border-blue-300 hover:bg-blue-50"
							>
								<x-artisanpack-icon name="{{ $section['icon'] ?? 'fas.layer-group' }}" class="w-5 h-5 text-gray-400" />
								<span>{{ $section['name'] }}</span>
							</button>
						@endforeach
					</div>
				</div>
			@endforeach
		@elseif ( 'layers' === $activeTab )
			@if ( empty( $blocks ) )
				<p class="text-sm text-gray-500">{{ __( 'No blocks on the canvas yet.' ) }}</p>
			@else
				<div
					x-drag-context
					@drag:end="$wire.reorderLayerBlocks( $event.detail.orderedIds )"
					class="space-y-1"
					role="list"
					aria-label="{{ __( 'Layer order' ) }}"
				>
					@foreach ( $blocks as $block )
						@php
							$blockConfig = app( BlockRegistry::class )->get( $block['type'] ?? '' );
							$blockName   = $blockConfig['name'] ?? ucfirst( $block['type'] ?? __( 'Block' ) );
							$blockIcon   = $blockConfig['icon'] ?? 'fas.cube';
							$isActive    = ( $block['id'] ?? '' ) === $activeBlockId;
						@endphp
						<div
							x-drag-item="'{{ $block['id'] ?? '' }}'"
							wire:key="layer-{{ $block['id'] ?? '' }}"
							wire:click="selectLayerBlock( '{{ $block['id'] ?? '' }}' )"
							class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors
								{{ $isActive ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-100' }}"
							role="listitem"
						>
							<x-artisanpack-icon name="fas.grip-vertical" class="h-3 w-3 shrink-0 cursor-grab text-gray-300" />
							<x-artisanpack-icon name="{{ $blockIcon }}" class="h-4 w-4 shrink-0 {{ $isActive ? 'text-blue-500' : 'text-gray-400' }}" />
							<span class="truncate">{{ $blockName }}</span>
						</div>
					@endforeach
				</div>
			@endif
		@endif
	</div>
</div>
