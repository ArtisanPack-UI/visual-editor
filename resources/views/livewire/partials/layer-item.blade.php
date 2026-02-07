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
	{{-- Columns drag context with pointer-events management --}}
	<div
		wire:key="layer-columns-drag-container-{{ $blockId }}"
		x-drag-context
		x-drag-group="visual-editor-columns"
		x-data="{ draggingColumn: false }"
		@dragstart.capture="draggingColumn = true"
		@dragend.capture="draggingColumn = false"
		@drag:end="
			console.log('Layers: Column drag ended:', $event.detail);
			console.log('Layers: Raw orderedIds:', $event.detail.orderedIds);
			const orderedIds = $event.detail.orderedIds;
			// Filter to only include column IDs (format: blockId-col-N)
			const columnIds = orderedIds.filter(id => id.includes('-col-') && id.split('-col-').length === 2);
			console.log('Layers: Filtered column IDs:', columnIds);

			// Count occurrences of each column ID
			const counts = {};
			columnIds.forEach(id => { counts[id] = (counts[id] || 0) + 1; });
			console.log('Layers: Column ID counts:', counts);

			// Find the dragged column (appears more than once)
			const draggedColumnId = Object.keys(counts).find(id => counts[id] > 1);
			console.log('Layers: Dragged column (from duplicates):', draggedColumnId);

			// Remove first occurrence of dragged column, keep all others
			const uniqueColumnIds = [];
			let removedFirst = false;
			for (const id of columnIds) {
				if (id === draggedColumnId && !removedFirst) {
					removedFirst = true; // Skip the first occurrence of dragged item
					continue;
				}
				if (!uniqueColumnIds.includes(id)) {
					uniqueColumnIds.push(id);
				}
			}

			const newOrder = uniqueColumnIds.map(id => parseInt(id.split('-col-')[1]));
			console.log('Layers: Unique column IDs:', uniqueColumnIds);
			console.log('Layers: New column order:', newOrder);
			$wire.dispatchColumnReorder('{{ $blockId }}', newOrder);
			draggingColumn = false;
		"
		@drag:cross-context="console.log('Layers: Column cross-context (should not happen):', $event.detail)"
	>
		@for ( $colIndex = 0; $colIndex < $columnCount; $colIndex++ )
			@php
				$column    = $columns[ $colIndex ] ?? [];
				$colBlocks = $column['blocks'] ?? [];
				$columnId  = $column['id'] ?? '';
			@endphp

			{{-- Column item - draggable for reordering --}}
			<div
				x-drag-item="'{{ $columnId ?: $blockId . '-col-' . $colIndex }}'"
				wire:key="layer-col-{{ $blockId }}-{{ $colIndex }}"
				wire:click="selectColumn( '{{ $blockId }}', {{ $colIndex }} )"
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

			{{-- Show blocks inside this column with drag context --}}
			@if ( !empty( $colBlocks ) )
				<div
					wire:key="drag-container-{{ $blockId }}-{{ $colIndex }}"
					x-drag-context
					x-drag-group="visual-editor-blocks"
					:style="draggingColumn ? 'pointer-events: none;' : ''"
					x-data="{
						parseWireKey(el) {
							const wireKey = el.getAttribute('wire:key');
							// Format: drag-container-{parentBlockId}-{columnIndex}
							const match = wireKey.match(/^drag-container-(.+)-(\d+)$/);
							if (match) {
								return { parentBlockId: match[1], slotIndex: parseInt(match[2]) };
							}
							return null;
						}
					}"
					@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}', {{ $colIndex }} )"
					@drag:cross-context="
						const source = parseWireKey($event.detail.sourceContext);
						const target = parseWireKey($event.detail.targetContext);
						console.log('Layers cross-context drag:', { itemId: $event.detail.itemId, source, target, sourceOrderedIds: $event.detail.sourceOrderedIds, targetOrderedIds: $event.detail.targetOrderedIds });
						$wire.dispatchCrossContextDrop({
							itemId: $event.detail.itemId,
							source: source,
							target: target,
							sourceOrderedIds: $event.detail.sourceOrderedIds,
							targetOrderedIds: $event.detail.targetOrderedIds
						})
					"
				>
					@foreach ( $colBlocks as $childBlock )
						@include( 'visual-editor::livewire.partials.layer-item', [
							'block'         => $childBlock,
							'activeBlockId' => $activeBlockId,
							'depth'         => $depth + 2,
						] )
					@endforeach
				</div>
			@endif
		@endfor
	</div>
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

		{{-- Show blocks inside this grid item with drag context --}}
		@if ( !empty( $itemBlocks ) )
			<div
				wire:key="drag-container-{{ $blockId }}-{{ $itemIndex }}"
				x-drag-context
				x-drag-group="visual-editor-blocks"
				x-data="{
					parseWireKey(el) {
						const wireKey = el.getAttribute('wire:key');
						// Format: drag-container-{parentBlockId}-{itemIndex}
						const match = wireKey.match(/^drag-container-(.+)-(\d+)$/);
						if (match) {
							return { parentBlockId: match[1], slotIndex: parseInt(match[2]) };
						}
						return null;
					}
				}"
				@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}', {{ $itemIndex }} )"
				@drag:cross-context="
					const source = parseWireKey($event.detail.sourceContext);
					const target = parseWireKey($event.detail.targetContext);
					console.log('Layers grid cross-context drag:', { itemId: $event.detail.itemId, source, target, sourceOrderedIds: $event.detail.sourceOrderedIds, targetOrderedIds: $event.detail.targetOrderedIds });
					$wire.dispatchCrossContextDrop({
						itemId: $event.detail.itemId,
						source: source,
						target: target,
						sourceOrderedIds: $event.detail.sourceOrderedIds,
						targetOrderedIds: $event.detail.targetOrderedIds
					})
				"
			>
				@foreach ( $itemBlocks as $childBlock )
					@include( 'visual-editor::livewire.partials.layer-item', [
						'block'         => $childBlock,
						'activeBlockId' => $activeBlockId,
						'depth'         => $depth + 2,
					] )
				@endforeach
			</div>
		@endif
	@endfor
@endif
