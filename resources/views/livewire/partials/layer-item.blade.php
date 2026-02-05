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
	$blockType   = $block['type'] ?? '';

	$innerBlocks = $block['content']['inner_blocks'] ?? [];
	$columns     = $block['content']['columns'] ?? [];
	$items       = $block['content']['items'] ?? [];

	// For columns block, determine number of columns from preset
	$columnCount = 0;
	if ( 'columns' === $blockType ) {
		$preset      = $block['settings']['preset'] ?? '50-50';
		$colWidths   = explode( '-', $preset );
		$columnCount = count( $colWidths );
	}

	// For grid block, determine number of items from settings
	$itemCount = 0;
	if ( 'grid' === $blockType ) {
		$itemCount = max( 1, min( 12, (int) ( $block['settings']['columns'] ?? '3' ) ) );
	}

	$hasChildren = !empty( $innerBlocks ) || $columnCount > 0 || $itemCount > 0;
@endphp

<div
	x-drag-item="'{{ $blockId }}'"
	wire:key="layer-{{ $blockId }}"
	wire:click="selectLayerBlock( '{{ $blockId }}' )"
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
				// For columns and grid blocks, count the column/item containers themselves
				$childCount = count( $innerBlocks ) + $columnCount + $itemCount;
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

@if ( $columnCount > 0 )
	@for ( $colIndex = 0; $colIndex < $columnCount; $colIndex++ )
		@php
			$column    = $columns[ $colIndex ] ?? [];
			$colBlocks = $column['blocks'] ?? [];
			$columnId  = $column['id'] ?? '';
		@endphp

		{{-- Show column container as a layer item --}}
		<div
			x-drag-item="'{{ $columnId ?: $blockId . '-col-' . $colIndex }}'"
			wire:key="layer-col-{{ $blockId }}-{{ $colIndex }}"
			@if ( $columnId ) wire:click="selectLayerBlock( '{{ $columnId }}' )" @endif
			class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors text-gray-700 hover:bg-gray-100"
			role="listitem"
			style="padding-left: {{ ( $depth + 1 ) * 1.25 }}rem;"
		>
			<x-artisanpack-icon name="fas.grip-vertical" class="h-3 w-3 shrink-0 cursor-grab text-gray-300" />
			<x-artisanpack-icon name="fas.table-columns" class="h-4 w-4 shrink-0 text-gray-400" />
			<span class="truncate">{{ __( 'Column :index', [ 'index' => $colIndex + 1 ] ) }}</span>
			@if ( !empty( $colBlocks ) )
				<span class="ml-auto text-xs text-gray-400">{{ count( $colBlocks ) }}</span>
			@endif
		</div>

		{{-- Show blocks inside this column --}}
		@foreach ( $colBlocks as $childBlock )
			@include( 'visual-editor::livewire.partials.layer-item', [
				'block'         => $childBlock,
				'activeBlockId' => $activeBlockId,
				'depth'         => $depth + 2,
			] )
		@endforeach
	@endfor
@endif

@if ( $itemCount > 0 )
	@for ( $itemIndex = 0; $itemIndex < $itemCount; $itemIndex++ )
		@php
			$item       = $items[ $itemIndex ] ?? [];
			$itemBlocks = $item['inner_blocks'] ?? [];
			$itemId     = $item['id'] ?? '';
		@endphp

		{{-- Show grid item container as a layer item --}}
		<div
			x-drag-item="'{{ $itemId ?: $blockId . '-item-' . $itemIndex }}'"
			wire:key="layer-item-{{ $blockId }}-{{ $itemIndex }}"
			@if ( $itemId ) wire:click="selectLayerBlock( '{{ $itemId }}' )" @endif
			class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors text-gray-700 hover:bg-gray-100"
			role="listitem"
			style="padding-left: {{ ( $depth + 1 ) * 1.25 }}rem;"
		>
			<x-artisanpack-icon name="fas.grip-vertical" class="h-3 w-3 shrink-0 cursor-grab text-gray-300" />
			<x-artisanpack-icon name="fas.table-cells-large" class="h-4 w-4 shrink-0 text-gray-400" />
			<span class="truncate">{{ __( 'Grid Item :index', [ 'index' => $itemIndex + 1 ] ) }}</span>
			@if ( !empty( $itemBlocks ) )
				<span class="ml-auto text-xs text-gray-400">{{ count( $itemBlocks ) }}</span>
			@endif
		</div>

		{{-- Show blocks inside this grid item --}}
		@foreach ( $itemBlocks as $childBlock )
			@include( 'visual-editor::livewire.partials.layer-item', [
				'block'         => $childBlock,
				'activeBlockId' => $activeBlockId,
				'depth'         => $depth + 2,
			] )
		@endforeach
	@endfor
@endif
