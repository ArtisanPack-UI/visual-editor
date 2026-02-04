<?php

declare( strict_types=1 );

/**
 * Visual Editor - Layer Item Partial
 *
 * Renders a single block in the layers panel with nested children.
 * Calls itself recursively for container blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      2.0.0
 *
 * @var array       $block         The block data array.
 * @var string|null $activeBlockId Currently active block ID.
 * @var int         $depth         Nesting depth (0 = top level).
 */

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

?>

@php
	$blockConfig = veBlocks()->get( $block['type'] ?? '' );
	$blockName   = $blockConfig['name'] ?? ucfirst( $block['type'] ?? __( 'Block' ) );
	$blockIcon   = $blockConfig['icon'] ?? 'fas.cube';
	$blockId     = $block['id'] ?? '';
	$isActive    = $blockId === $activeBlockId;
	$depth       = $depth ?? 0;
	$indent      = $depth * 1.25;

	$innerBlocks = $block['content']['inner_blocks'] ?? [];
	$columns     = $block['content']['columns'] ?? [];
	$items       = $block['content']['items'] ?? [];
	$hasChildren = !empty( $innerBlocks ) || !empty( $columns ) || !empty( $items );
@endphp

<div
	x-drag-item="'{{ $blockId }}'"
	wire:key="layer-{{ $blockId }}"
	wire:click="selectLayerBlock( '{{ $blockId }}' )"
	tabindex="-1"
	class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors
		{{ $isActive ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-100' }}"
	role="listitem"
	@if ( $depth > 0 ) style="padding-left: {{ $indent }}rem;" @endif
>
	<x-artisanpack-icon name="fas.grip-vertical" class="h-3 w-3 shrink-0 cursor-grab text-gray-300" />
	<x-artisanpack-icon name="{{ $blockIcon }}" class="h-4 w-4 shrink-0 {{ $isActive ? 'text-blue-500' : 'text-gray-400' }}" />
	<span class="truncate">{{ $blockName }}</span>
	@if ( $hasChildren )
		<span class="ml-auto text-xs text-gray-400">
			@php
				$childCount = count( $innerBlocks );

				foreach ( $columns as $col ) {
					$childCount += count( $col['blocks'] ?? [] );
				}

				foreach ( $items as $item ) {
					$childCount += count( $item['inner_blocks'] ?? [] );
				}
			@endphp
			{{ $childCount }}
		</span>
	@endif
</div>

{{-- Render nested children --}}
@if ( !empty( $innerBlocks ) )
	@foreach ( $innerBlocks as $childBlock )
		@include( 'visual-editor::livewire.partials.layer-item', [
			'block'         => $childBlock,
			'activeBlockId' => $activeBlockId,
			'depth'         => $depth + 1,
		] )
	@endforeach
@endif

@if ( !empty( $columns ) )
	@foreach ( $columns as $colIndex => $column )
		@foreach ( $column['blocks'] ?? [] as $childBlock )
			@include( 'visual-editor::livewire.partials.layer-item', [
				'block'         => $childBlock,
				'activeBlockId' => $activeBlockId,
				'depth'         => $depth + 1,
			] )
		@endforeach
	@endforeach
@endif

@if ( !empty( $items ) )
	@foreach ( $items as $item )
		@foreach ( $item['inner_blocks'] ?? [] as $childBlock )
			@include( 'visual-editor::livewire.partials.layer-item', [
				'block'         => $childBlock,
				'activeBlockId' => $activeBlockId,
				'depth'         => $depth + 1,
			] )
		@endforeach
	@endforeach
@endif
