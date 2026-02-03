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
			$search = str_replace( [ '\\', '%', '_' ], [ '\\\\', '\\%', '\\_' ], $this->sectionSearch );
			$pattern = '%' . $search . '%';
			$query->where( function ( $q ) use ( $pattern ) {
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
						@include( 'visual-editor::livewire.partials.layer-item', [
							'block'         => $block,
							'activeBlockId' => $activeBlockId,
							'depth'         => 0,
						] )
					@endforeach
				</div>
			@endif
		@endif
	</div>
</div>
