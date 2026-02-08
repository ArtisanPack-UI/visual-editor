<?php

declare(strict_types=1);

/**
 * Visual Editor - Layer Item Partial
 *
 * Renders a single block in the layers panel with nested children.
 * Calls itself recursively for container blocks.
 *
 *
 * @since      2.0.0
 *
 * @var array $block         The block data array.
 * @var string|null $activeBlockId Currently active block ID.
 * @var int $depth         Nesting depth (0 = top level).
 */
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
			'block'          => $childBlock,
			'activeBlockId'  => $activeBlockId,
			'activeColumnId' => $activeColumnId ?? null,
			'depth'          => $depth + 1,
		] )
	@endforeach
@endif

@if ( $columnCount > 0 )
	{{-- Columns drag context with lifecycle management --}}
	<div
		wire:key="layer-columns-drag-container-{{ $blockId }}"
		x-drag-context
		x-drag-group="visual-editor-columns"
		x-data="{
			draggingColumn: false,
			isDragging: false,
			init() {
				// Prevent Livewire morphing during active drags
				Livewire.hook('morph', ({ component, cleanup }) => {
					if (this.isDragging) {
						console.log('Layers: Preventing morph during drag');
						return false;
					}
				});

				console.log('Layers: Column drag context initialized for {{ $blockId }}');
			}
		}"
		@dragstart.capture="
			draggingColumn = true;
			isDragging = true;
		"
		@dragend.capture="
			draggingColumn = false;
			// Small delay to ensure drag:end fires first
			setTimeout(() => {
				isDragging = false;
			}, 100);
		"
		@drag:end="
			const orderedIds = $event.detail.orderedIds;

			// Filter to only include column IDs for this specific columns block
			const blockIdPrefix = '{{ $blockId }}-col-';

			// Helper to check if an ID is a column ID
			const isColumnId = (id) => {
				return typeof id === 'string' &&
					id.startsWith(blockIdPrefix) &&
					id.split('-col-').length === 2;
			};

			// Determine drop position by analyzing the orderedIds pattern
			let sortedColumns;
			const lastIndex = orderedIds.length - 1;

			// If position 1 is a column ID, the column at position 0 was dragged to first position
			if (orderedIds.length > 1 && isColumnId(orderedIds[1])) {
				const draggedColumn = orderedIds[0];
				const otherColumns = [];

				orderedIds.forEach(id => {
					if (isColumnId(id) && id !== draggedColumn && !otherColumns.includes(id)) {
						otherColumns.push(id);
					}
				});

				sortedColumns = [draggedColumn, ...otherColumns];
			}
			// If the last element is a column ID, that column was dragged to last position
			else if (isColumnId(orderedIds[lastIndex])) {
				const draggedColumn = orderedIds[lastIndex];
				const otherColumns = [];

				orderedIds.forEach(id => {
					if (isColumnId(id) && id !== draggedColumn && !otherColumns.includes(id)) {
						otherColumns.push(id);
					}
				});

				sortedColumns = [...otherColumns, draggedColumn];
			}
			// Fallback: Filter to only column IDs, detect dragged column
			else {
				// Filter to only column IDs
				const columnIds = orderedIds.filter(id => isColumnId(id));

				// Detect which column was dragged (appears more than once)
				const columnCounts = {};
				columnIds.forEach(id => {
					columnCounts[id] = (columnCounts[id] || 0) + 1;
				});

				let draggedColumnId = null;
				for (const id in columnCounts) {
					if (columnCounts[id] > 1) {
						draggedColumnId = id;
						break;
					}
				}

				// If a column was dragged, move it to the end (assume left-to-right)
				if (draggedColumnId) {
					const otherColumns = [];
					const seenDragged = new Set();

					columnIds.forEach(id => {
						if (id !== draggedColumnId && !otherColumns.includes(id)) {
							otherColumns.push(id);
						}
					});

					sortedColumns = [...otherColumns, draggedColumnId];
				} else {
					// No duplicates, just use order as-is
					sortedColumns = [...new Set(columnIds)];
				}
			}

			// Extract column indexes from sorted IDs
			const newOrder = sortedColumns.map(id => {
				const parts = id.split('-col-');
				return parseInt(parts[parts.length - 1], 10);
			});

			// Only dispatch if we have a valid reorder
			if (newOrder.length > 0 && newOrder.every(idx => !isNaN(idx))) {
				$wire.dispatchColumnReorder('{{ $blockId }}', newOrder);
			} else {
				console.warn('Layers: Invalid column order, skipping reorder:', newOrder);
			}
		"
		@drag:cross-context="
			console.log('🔵 LAYERS: Cross-context handler FIRED', {
				sourceContext: $event.detail.sourceContext.getAttribute('wire:key'),
				targetContext: $event.detail.targetContext.getAttribute('wire:key'),
				itemId: $event.detail.itemId
			});

			const sourceContext = $event.detail.sourceContext;
			const targetContext = $event.detail.targetContext;
			const itemId = $event.detail.itemId;

			// Extract source and target parent block IDs from wire:key attributes
			const sourceKey = sourceContext.getAttribute('wire:key');
			const targetKey = targetContext.getAttribute('wire:key');

			const sourceMatch = sourceKey.match(/^layer-columns-drag-container-(.+)$/);
			const targetMatch = targetKey.match(/^layer-columns-drag-container-(.+)$/);

			if (!sourceMatch || !targetMatch) {
				console.warn('🔴 Could not parse source/target from wire:key');
				return;
			}

			const sourceParentId = sourceMatch[1];
			const targetParentId = targetMatch[1];

			console.log('🟢 Parsed parent IDs:', { sourceParentId, targetParentId });

			// Extract column index from itemId (format: blockId-col-index)
			const colMatch = itemId.match(/-col-(\\d+)$/);
			if (!colMatch) {
				console.warn('🔴 Could not extract column index from itemId:', itemId);
				return;
			}

			const sourceColumnIndex = parseInt(colMatch[1], 10);

			// Determine target column index from targetOrderedIds
			const targetOrderedIds = $event.detail.targetOrderedIds;

			// Find position of dragged column in targetOrderedIds
			const draggedIndex = targetOrderedIds.findIndex(id => id === itemId);

			if (draggedIndex === -1) {
				console.warn('🔴 Dragged column not found in targetOrderedIds');
				return;
			}

			// Count how many target block columns appear before the dragged column
			// This tells us where to insert in the target columns array
			const targetBlockPrefix = targetParentId + '-col-';
			let movedColumnIndex = 0;
			for (let i = 0; i < draggedIndex; i++) {
				const id = targetOrderedIds[i];
				if (typeof id === 'string' && id.startsWith(targetBlockPrefix)) {
					movedColumnIndex++;
				}
			}

			console.log('🟡 Calculated indices:', {
				sourceColumnIndex,
				movedColumnIndex,
				draggedIndex,
				targetOrderedIds
			});

			console.log('🚀 Calling $wire.dispatchCrossContextColumnMove with:', {
				sourceParentId,
				sourceColumnIndex,
				targetParentId,
				movedColumnIndex
			});

			$wire.dispatchCrossContextColumnMove(
				sourceParentId,
				sourceColumnIndex,
				targetParentId,
				movedColumnIndex
			);
		"
	>
		@for ( $colIndex = 0; $colIndex < $columnCount; $colIndex++ )
			@php
				$column         = $columns[ $colIndex ] ?? [];
				$colBlocks      = $column['blocks'] ?? [];
				// Always use index-based ID for drag-drop matching
				$columnId       = $blockId . '-col-' . $colIndex;
				// Fallback for wire:key if column doesn't have an ID yet
				$wireKey        = $column['id'] ?? $columnId;
				$isColumnActive = $columnId === ( $activeColumnId ?? null );
			@endphp

			{{-- Column item - draggable for reordering --}}
			<div
				x-drag-item="'{{ $columnId }}'"
				wire:key="layer-col-{{ $wireKey }}"
				wire:click="selectColumn( '{{ $blockId }}', {{ $colIndex }} )"
				class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors
					{{ $isColumnActive ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-100' }}"
				role="listitem"
				style="padding-left: {{ ( $depth + 1 ) * 1.25 }}rem;"
			>
				<x-artisanpack-icon name="fas.grip-vertical" class="h-3 w-3 shrink-0 cursor-grab text-gray-300" />
				<x-artisanpack-icon name="fas.table-columns" class="h-4 w-4 shrink-0 {{ $isColumnActive ? 'text-blue-500' : 'text-gray-400' }}" />
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
							'block'          => $childBlock,
							'activeBlockId'  => $activeBlockId,
							'activeColumnId' => $activeColumnId ?? null,
							'depth'          => $depth + 2,
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
					// Skip if this is a column drag (handled by column-specific handler)
					const itemId = $event.detail.itemId;
					if (itemId && itemId.includes('-col-')) {
						console.log('Layers: Skipping column drag in block handler');
						return;
					}

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
						'block'          => $childBlock,
						'activeBlockId'  => $activeBlockId,
						'activeColumnId' => $activeColumnId ?? null,
						'depth'          => $depth + 2,
					] )
				@endforeach
			</div>
		@endif
	@endfor
@endif
