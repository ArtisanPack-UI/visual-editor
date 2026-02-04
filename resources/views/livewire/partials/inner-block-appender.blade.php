<?php

declare( strict_types=1 );

/**
 * Visual Editor - Inner Block Appender
 *
 * A small "+" button with a dropdown block picker for inserting
 * blocks inside container blocks (group, column, columns, grid).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      2.0.0
 *
 * @var string   $parentBlockId The parent container block ID.
 * @var int|null $slotIndex     Slot index for columns-type containers (null for inner_blocks).
 * @var int      $depth         Nesting depth.
 */

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

?>

@php
	$registry   = veBlocks();
	$allBlocks  = $registry->getGroupedByCategory();
	$appenderId = 'appender-' . $parentBlockId . ( null !== $slotIndex ? '-' . $slotIndex : '' );

	// Filter blocks based on parent context
	$appenderBlocks = $allBlocks->map( function ( $category ) use ( $parentBlockType ) {
		$filteredBlocks = $category['blocks']->filter( function ( $block, $blockType ) use ( $parentBlockType ) {
			// If block has no parent constraint, it can go anywhere
			if ( empty( $block['parent'] ) ) {
				return true;
			}

			// If block has parent constraint, only show if current parent matches
			if ( null !== $parentBlockType && in_array( $parentBlockType, $block['parent'], true ) ) {
				return true;
			}

			return false;
		} );

		return array_merge( $category, [ 'blocks' => $filteredBlocks ] );
	} )->filter( fn ( $category ) => $category['blocks']->isNotEmpty() );
@endphp

<div
	x-data="{
		open: false,
		search: '',
		toggle() {
			this.open = !this.open;
			if ( this.open ) {
				this.search = '';
				this.$nextTick( () => this.$refs.appenderSearch?.focus() );
			}
		},
		close() {
			this.open = false;
			this.search = '';
		},
		insertBlock( type ) {
			$wire.insertBlockIntoContainer( type, '{{ $parentBlockId }}', {{ $slotIndex ?? -1 }} );
			this.close();
		},
		matchesSearch( name, keywords ) {
			if ( '' === this.search ) return true;
			const s = this.search.toLowerCase();
			if ( name.toLowerCase().includes( s ) ) return true;
			for ( const kw of keywords ) {
				if ( kw.toLowerCase().includes( s ) ) return true;
			}
			return false;
		},
	}"
	class="mt-1"
>
	<button
		type="button"
		@click="toggle()"
		class="flex w-full items-center justify-center gap-1 rounded border border-dashed border-gray-300 px-2 py-1 text-xs text-gray-400 transition-colors hover:border-blue-400 hover:bg-blue-50 hover:text-blue-500"
	>
		<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
		</svg>
		{{ __( 'Add block' ) }}
	</button>

	<div
		x-show="open"
		x-transition:enter="transition ease-out duration-150"
		x-transition:enter-start="opacity-0 scale-95"
		x-transition:enter-end="opacity-100 scale-100"
		x-transition:leave="transition ease-in duration-100"
		x-transition:leave-start="opacity-100 scale-100"
		x-transition:leave-end="opacity-0 scale-95"
		@click.outside="close()"
		@keydown.escape.prevent="close()"
		x-cloak
		class="absolute z-50 mt-1 max-h-64 w-64 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
	>
		{{-- Search input --}}
		<div class="sticky top-0 border-b border-gray-100 bg-white p-2">
			<input
				x-ref="appenderSearch"
				x-model.debounce.200ms="search"
				type="text"
				class="w-full rounded border border-gray-300 px-2 py-1 text-xs placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
				placeholder="{{ __( 'Search blocks...' ) }}"
			/>
		</div>

		{{-- Block list by category --}}
		@foreach ( $appenderBlocks as $categoryKey => $category )
			<div x-show="[
				@foreach ( $category['blocks'] as $bType => $bDef )
					matchesSearch( @js( $bDef['name'] ?? '' ), @js( $bDef['keywords'] ?? [] ) ),
				@endforeach
			].some( v => v )">
				<div class="sticky top-[2.5rem] bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-gray-500">
					{{ $category['name'] }}
				</div>
				@foreach ( $category['blocks'] as $bType => $bDef )
					<button
						type="button"
						x-show="matchesSearch( @js( $bDef['name'] ?? '' ), @js( $bDef['keywords'] ?? [] ) )"
						@click="insertBlock( '{{ $bType }}' )"
						class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-gray-700 hover:bg-blue-50 hover:text-blue-700"
					>
						<x-artisanpack-icon name="{{ $bDef['icon'] ?? 'fas.cube' }}" class="h-4 w-4 text-gray-400" />
						<span>{{ $bDef['name'] ?? ucfirst( $bType ) }}</span>
					</button>
				@endforeach
			</div>
		@endforeach
	</div>
</div>
