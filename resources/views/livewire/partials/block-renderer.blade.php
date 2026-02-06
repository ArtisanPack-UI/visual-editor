<?php

declare(strict_types=1);

/**
 * Visual Editor - Block Renderer Partial
 *
 * Renders a single block (display or editing mode) with its toolbar.
 * Designed to be called recursively for container blocks with inner blocks.
 *
 *
 * @since      2.0.0
 *
 * @var array $block          The block data array.
 * @var int $blockIndex     The index within the parent array.
 * @var int $totalBlocks    Total siblings in the parent array.
 * @var string|null $activeBlockId  Currently active (selected) block ID.
 * @var string|null $editingBlockId Currently editing block ID.
 * @var int $depth          Nesting depth (0 = top level).
 * @var string|null $parentBlockId  Parent container block ID (null for top level).
 * @var int|null $slotIndex      Slot index for columns-type containers.
 */
?>

@php
	$blockConfig    = veBlocks()->get( $block['type'] ?? '' );
	$contentSchema  = $blockConfig['content_schema'] ?? [];
	$textFieldType  = $contentSchema['text']['type'] ?? null;
	$isRichText     = 'richtext' === $textFieldType;
	$isEditableText = in_array( $textFieldType, [ 'text', 'textarea', 'richtext' ], true );
	$blockType      = $block['type'] ?? '';
	$blockId        = $block['id'] ?? '';
	$isActive       = $blockId === $activeBlockId;
	$isEditing      = $blockId === $editingBlockId && $isEditableText;
	$depth          = $depth ?? 0;
	$parentBlockId  = $parentBlockId ?? null;
	$slotIndex      = $slotIndex ?? null;
@endphp

<div
	x-drag-item="'{{ $blockId ?: $blockIndex }}'"
	wire:key="block-{{ $blockId ?: $blockIndex }}"
	@if ( $isEditableText )
		@click.stop="$wire.startInlineEdit( '{{ $blockId }}' )"
	@else
		@click.stop="$wire.selectBlock( '{{ $blockId }}' )"
	@endif
	class="ve-canvas-block group relative rounded px-4 py-2 transition-colors
		{{ $isActive ? 'ring-2 ring-blue-200' : '' }}"
	role="listitem"
	@if ( $isEditing && $isRichText )
		x-data="richTextEditor( { htmlContent: @js( $block['content']['text'] ?? '' ) } )"
	@endif
>
	{{-- Global Block Toolbar (shown for any selected block) --}}
	@if ( $isActive )
		@include( 'visual-editor::livewire.partials.block-toolbar', [
			'blockId'     => $blockId,
			'blockType'   => $blockType,
			'blockConfig' => $blockConfig,
			'blockIndex'  => $blockIndex,
			'totalBlocks' => $totalBlocks,
			'isEditing'   => $isEditing,
			'isRichText'  => $isRichText,
			'block'       => $block,
		] )
	@endif

	{{-- Block Content --}}
	@if ( $isEditing )
		@if ( $isRichText )
			{{-- Rich Text Edit Mode --}}
			@php
				$editLevel   = $block['content']['level'] ?? 'h2';
				$editTag     = match ( $blockType ) {
					'heading' => in_array( $editLevel, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $editLevel : 'h2',
					'list'    => 'number' === ( $block['content']['style'] ?? 'bullet' ) ? 'ol' : 'ul',
					default   => 'div',
				};
				$editClasses = match ( $blockType ) {
					'heading' => match ( $editTag ) {
						'h1'    => 'text-4xl font-bold',
						'h2'    => 'text-3xl font-bold',
						'h3'    => 'text-2xl font-semibold',
						'h4'    => 'text-xl font-semibold',
						'h5'    => 'text-lg font-medium',
						'h6'    => 'text-base font-medium',
						default => 'text-3xl font-bold',
					},
					'list' => 'ol' === $editTag ? 'list-decimal list-inside' : 'list-disc list-inside',
					default => '',
				};
				$richTextClasses = match ( $blockType ) {
					'heading' => $editClasses . ' min-h-[1.5rem] rounded px-1 outline-none ring-2 ring-blue-300',
					'list'    => $editClasses . ' min-h-[1.5rem] rounded px-1 outline-none ring-2 ring-blue-300',
					default   => 'prose prose-sm min-h-[1.5rem] max-w-none rounded px-1 outline-none ring-2 ring-blue-300',
				};
				$isListBlock = 'list' === $blockType;
				$editContent = $block['content']['text'] ?? '';
			@endphp
			<div
				x-data="inlineSlashCommands( { blocks: @js( $this->slashMenuBlocks ), blockId: '{{ $blockId }}' } )"
				class="relative"
			>
				{{-- Slash Command Menu (positioned below by default, above if near bottom) --}}
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
					:class="menuPositionAbove ? 'absolute bottom-full left-0 z-50 mb-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg' : 'absolute top-full left-0 z-50 mt-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg'"
					role="listbox"
					aria-label="{{ __( 'Block types' ) }}"
				>
					<template x-for="( category, catIdx ) in filteredBlocks" :key="category.key">
						<div>
							<div
								class="sticky top-0 bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500"
								x-text="category.name"
							></div>
							<template x-for="( block, blockIdx ) in category.blocks" :key="`${category.key}-${blockIdx}`">
								<button
									type="button"
									@click="selectBlock( block, $refs.editor )"
									@mouseenter="setActiveIndex( getFlatIndex( block ) )"
									:class="{ 'bg-blue-50 text-blue-700': activeIndex === getFlatIndex( block ) }"
									class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50"
									role="option"
									:aria-selected="activeIndex === getFlatIndex( block )"
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

				<{{ $editTag }}
					x-ref="editor"
					contenteditable="true"
					x-init="$nextTick( () => {
						@if ( $isListBlock && '' === trim( $editContent ) )
							$el.innerHTML = '<li></li>';
						@endif
						// Only auto-focus if NOT being programmatically focused
						if ( !window.veFocusingBlock ) {
							$el.focus(); let s = window.getSelection(), r = document.createRange(); r.selectNodeContents( $el ); r.collapse( false ); s.removeAllRanges(); s.addRange( r )
						}
					} )"
					@blur="if ( !window.veNavigating && !window.veFocusingBlock ) { $wire.saveInlineEdit( '{{ $blockId }}', $el.innerHTML ) }"
					@keydown.escape.prevent="$wire.saveInlineEdit( '{{ $blockId }}', $el.innerHTML )"
					@if ( ! $isListBlock )
						@keydown.enter.prevent="window.veNavigating = true; $wire.insertBlockAfter( '{{ $blockId }}', $el.innerHTML )"
					@endif
					@keydown.tab.prevent="window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.innerHTML, $event.shiftKey ? 'up' : 'down' )"
					@keydown.arrow-up="if ( window.veAtTopOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.innerHTML, 'up' ) }"
					@keydown.arrow-down="if ( window.veAtBottomOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.innerHTML, 'down' ) }"
					@keydown.meta.b.prevent="format( 'bold' )"
					@keydown.ctrl.b.prevent="format( 'bold' )"
					@keydown.meta.i.prevent="format( 'italic' )"
					@keydown.ctrl.i.prevent="format( 'italic' )"
					@keydown.meta.u.prevent="format( 'underline' )"
					@keydown.ctrl.u.prevent="format( 'underline' )"
					@input="handleInput( $event, $el )"
					@keydown="handleKeydown( $event, $el )"
					class="{{ $richTextClasses }}"
				>{!! kses( $editContent ) !!}</{{ $editTag }}>
			</div>
		@else
			{{-- Plain Text Edit Mode --}}
			@php
				$editLevel   = $block['content']['level'] ?? 'h2';
				$editTag     = 'heading' === $blockType ? ( in_array( $editLevel, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $editLevel : 'h2' ) : 'div';
				$editClasses = match ( $blockType ) {
					'quote' => 'border-l-4 border-gray-300 pl-4 italic text-gray-700',
					default => '',
				};
			@endphp
			<{{ $editTag }}
				x-ref="editor"
				contenteditable="true"
				x-init="$nextTick( () => { $el.focus(); let s = window.getSelection(), r = document.createRange(); r.selectNodeContents( $el ); r.collapse( false ); s.removeAllRanges(); s.addRange( r ) } )"
				@blur="if ( !window.veNavigating && !window.veFocusingBlock ) { $wire.saveInlineEdit( '{{ $blockId }}', $el.textContent ) }"
				@keydown.escape.prevent="$wire.saveInlineEdit( '{{ $blockId }}', $el.textContent )"
				@keydown.enter.prevent="window.veNavigating = true; $wire.insertBlockAfter( '{{ $blockId }}', $el.textContent )"
				@keydown.tab.prevent="window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.textContent, $event.shiftKey ? 'up' : 'down' )"
				@keydown.arrow-up="if ( window.veAtTopOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.textContent, 'up' ) }"
				@keydown.arrow-down="if ( window.veAtBottomOfElement( $el ) ) { $event.preventDefault(); window.veNavigating = true; $wire.saveAndNavigate( '{{ $blockId }}', $el.textContent, 'down' ) }"
				class="{{ $editClasses }} min-h-[1.5rem] rounded px-1 outline-none ring-2 ring-blue-300"
			>{{ $block['content']['text'] ?? '' }}</{{ $editTag }}>
		@endif
	@else
		{{-- WYSIWYG Display Mode --}}
		@switch ( $blockType )
			@case ( 'heading' )
				@php
					$level          = $block['content']['level'] ?? 'h2';
					$headingTag     = in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $level : 'h2';
					$headingClasses = match ( $headingTag ) {
						'h1'    => 'text-4xl font-bold',
						'h2'    => 'text-3xl font-bold',
						'h3'    => 'text-2xl font-semibold',
						'h4'    => 'text-xl font-semibold',
						'h5'    => 'text-lg font-medium',
						'h6'    => 'text-base font-medium',
						default => 'text-3xl font-bold',
					};
					$headingAnchor  = $block['settings']['anchor'] ?? '';
				@endphp
				<{{ $headingTag }} class="{{ $headingClasses }}"@if ( '' !== $headingAnchor ) id="{{ $headingAnchor }}"@endif>
					@if ( '' !== ( $block['content']['text'] ?? '' ) )
						{!! kses( $block['content']['text'] ) !!}
					@else
						<span class="italic text-gray-400">{{ __( 'Type heading...' ) }}</span>
					@endif
				</{{ $headingTag }}>
				@break

			@case ( 'text' )
				@php
					$dropCap        = (bool) ( $block['settings']['drop_cap'] ?? false );
					$dropCapClasses = $dropCap ? 'first-letter:float-left first-letter:mr-2 first-letter:text-5xl first-letter:font-bold first-letter:leading-none' : '';
					$isEmpty        = '' === ( $block['content']['text'] ?? '' );
				@endphp
				@if ( $isActive && $isEmpty && !$isEditing )
					<div
						x-data="{ showInserter: false }"
						@mouseenter="showInserter = true"
						@mouseleave="showInserter = false"
						class="flex gap-2"
					>
						<div class="prose prose-sm max-w-none {{ $dropCapClasses }} flex-1">
							<p class="italic text-gray-400">{{ __( 'Type / to choose a block' ) }}</p>
						</div>
						<button
							x-show="showInserter"
							x-cloak
							@click.stop="$wire.startInlineEdit( '{{ $blockId }}' ); $nextTick( () => { const el = $refs.editor || document.querySelector( '[x-ref=editor]' ); if ( el ) { el.focus() } } )"
							class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded bg-blue-600 text-white hover:bg-blue-700"
							type="button"
							aria-label="{{ __( 'Start editing' ) }}"
						>
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
							</svg>
						</button>
					</div>
				@else
					<div class="prose prose-sm max-w-none {{ $dropCapClasses }}">
						@if ( '' !== ( $block['content']['text'] ?? '' ) )
							{!! kses( $block['content']['text'] ) !!}
						@else
							<p class="italic text-gray-400">{{ __( 'Type text...' ) }}</p>
						@endif
					</div>
				@endif
				@break

			@case ( 'list' )
				@php
					$listStyle   = $block['content']['style'] ?? 'bullet';
					$listTag     = 'number' === $listStyle ? 'ol' : 'ul';
					$listClasses = 'number' === $listStyle ? 'list-decimal list-inside' : 'list-disc list-inside';
				@endphp
				<{{ $listTag }} class="{{ $listClasses }}">
					@if ( '' !== trim( $block['content']['text'] ?? '' ) )
						{!! kses( $block['content']['text'] ) !!}
					@else
						<li class="italic text-gray-400">{{ __( 'Type list items...' ) }}</li>
					@endif
				</{{ $listTag }}>
				@break

			@case ( 'quote' )
				<blockquote class="border-l-4 border-gray-300 pl-4 italic text-gray-700">
					@if ( '' !== ( $block['content']['text'] ?? '' ) )
						{{ $block['content']['text'] }}
					@else
						<span class="not-italic text-gray-400">{{ __( 'Type quote...' ) }}</span>
					@endif
					@if ( '' !== ( $block['content']['citation'] ?? '' ) )
						<cite class="mt-1 block text-sm not-italic text-gray-500">
							&mdash; {{ $block['content']['citation'] }}
						</cite>
					@endif
				</blockquote>
				@break

			@case ( 'columns' )
				@php
					$preset     = $block['settings']['preset'] ?? '50-50';
					$gap        = $block['settings']['gap'] ?? 'medium';
					$vAlign     = $block['settings']['vertical_alignment'] ?? 'top';
					$colWidths  = explode( '-', $preset );
					$gapClass   = match ( $gap ) {
						'none'   => 'gap-0',
						'small'  => 'gap-2',
						'large'  => 'gap-8',
						default  => 'gap-4',
					};
					$alignClass = match ( $vAlign ) {
						'center'  => 'items-center',
						'bottom'  => 'items-end',
						'stretch' => 'items-stretch',
						default   => 'items-start',
					};
					$columns    = $block['content']['columns'] ?? [];
					$colCount   = $block['settings']['columns'] ?? '';
					$colCountSm = $block['settings']['columns_sm'] ?? '';
					$colCountMd = $block['settings']['columns_md'] ?? '';
					$colCountLg = $block['settings']['columns_lg'] ?? '';
					$colCountXl = $block['settings']['columns_xl'] ?? '';
					$responsiveCols = array_filter( [
						'' !== $colCount ? __( 'Base' ) . ': ' . $colCount : '',
						'' !== $colCountSm ? 'SM: ' . $colCountSm : '',
						'' !== $colCountMd ? 'MD: ' . $colCountMd : '',
						'' !== $colCountLg ? 'LG: ' . $colCountLg : '',
						'' !== $colCountXl ? 'XL: ' . $colCountXl : '',
					] );
				@endphp
				<div class="flex {{ $gapClass }} {{ $alignClass }}">
					@if ( !empty( $responsiveCols ) )
						<div class="mb-1 w-full text-xs text-gray-400">
							{{ implode( ' | ', $responsiveCols ) }}
						</div>
					@endif
					@foreach ( $colWidths as $colIdx => $width )
						@php
							$colBlocks = $columns[ $colIdx ]['blocks'] ?? [];
						@endphp
						<div
							class="min-h-[3rem] rounded border border-dashed border-gray-300 bg-gray-50/50 p-2"
							style="flex: 0 0 {{ $width }}%;"
						>
							@if ( !empty( $colBlocks ) )
								<div
									x-drag-context
									@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}', {{ $colIdx }} )"
									class="space-y-2"
									role="list"
									aria-label="{{ __( 'Column :index blocks', [ 'index' => $colIdx + 1 ] ) }}"
								>
									@foreach ( $colBlocks as $innerIndex => $innerBlock )
										@include( 'visual-editor::livewire.partials.block-renderer', [
											'block'          => $innerBlock,
											'blockIndex'     => $innerIndex,
											'totalBlocks'    => count( $colBlocks ),
											'activeBlockId'  => $activeBlockId,
											'editingBlockId' => $editingBlockId,
											'depth'          => $depth + 1,
											'parentBlockId'  => $blockId,
											'slotIndex'      => $colIdx,
										] )
									@endforeach
								</div>
							@endif
							@include( 'visual-editor::livewire.partials.inner-block-appender', [
								'parentBlockId' => $blockId,
				'parentBlockType' => $blockType,
								'slotIndex'     => $colIdx,
								'depth'         => $depth + 1,
							] )
						</div>
					@endforeach
				</div>
				@break

			@case ( 'column' )
				@php
					$colWidth   = $block['settings']['width'] ?? '';
					$colStyle   = '' !== $colWidth ? 'flex: 0 0 ' . max( 0, min( 100, (int) $colWidth ) ) . '%;' : '';

					$colDir     = match ( $block['settings']['flex_direction'] ?? 'column' ) {
						'row'   => 'flex-row',
						default => 'flex-col',
					};
					$colAlign   = match ( $block['settings']['align_items'] ?? 'stretch' ) {
						'start'  => 'items-start',
						'center' => 'items-center',
						'end'    => 'items-end',
						default  => 'items-stretch',
					};
					$colJustify = match ( $block['settings']['justify_content'] ?? 'start' ) {
						'center'  => 'justify-center',
						'end'     => 'justify-end',
						'between' => 'justify-between',
						'around'  => 'justify-around',
						'evenly'  => 'justify-evenly',
						default   => 'justify-start',
					};

					$colInnerBlocks = $block['content']['inner_blocks'] ?? [];
				@endphp
				<div
					class="flex {{ $colDir }} {{ $colAlign }} {{ $colJustify }} min-h-[3rem] rounded border border-dashed border-gray-300 bg-gray-50/50 p-2"
					@if ( '' !== $colStyle ) style="{{ $colStyle }}" @endif
				>
					@if ( !empty( $colInnerBlocks ) )
						<div
							x-drag-context
							@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}' )"
							class="w-full space-y-2"
							role="list"
							aria-label="{{ __( 'Column blocks' ) }}"
						>
							@foreach ( $colInnerBlocks as $innerIndex => $innerBlock )
								@include( 'visual-editor::livewire.partials.block-renderer', [
									'block'          => $innerBlock,
									'blockIndex'     => $innerIndex,
									'totalBlocks'    => count( $colInnerBlocks ),
									'activeBlockId'  => $activeBlockId,
									'editingBlockId' => $editingBlockId,
									'depth'          => $depth + 1,
									'parentBlockId'  => $blockId,
									'slotIndex'      => null,
								] )
							@endforeach
						</div>
					@endif
					@include( 'visual-editor::livewire.partials.inner-block-appender', [
						'parentBlockId' => $blockId,
				'parentBlockType' => $blockType,
						'slotIndex'     => null,
						'depth'         => $depth + 1,
					] )
				</div>
				@break

			@case ( 'group' )
				@php
					$innerBlocks    = $block['content']['inner_blocks'] ?? [];
					$groupDir       = match ( $block['settings']['flex_direction'] ?? 'column' ) {
						'row'   => 'flex-row',
						default => 'flex-col',
					};
					$groupWrap      = match ( $block['settings']['flex_wrap'] ?? 'nowrap' ) {
						'wrap'         => 'flex-wrap',
						'wrap-reverse' => 'flex-wrap-reverse',
						default        => 'flex-nowrap',
					};
					$groupAlign     = match ( $block['settings']['align_items'] ?? 'stretch' ) {
						'start'  => 'items-start',
						'center' => 'items-center',
						'end'    => 'items-end',
						default  => 'items-stretch',
					};
					$groupJustify   = match ( $block['settings']['justify_content'] ?? 'start' ) {
						'center'  => 'justify-center',
						'end'     => 'justify-end',
						'between' => 'justify-between',
						'around'  => 'justify-around',
						'evenly'  => 'justify-evenly',
						default   => 'justify-start',
					};
					$groupVariation        = $block['settings']['_variation'] ?? 'group';
					$hasVariations         = veBlocks()->hasVariations( 'group' );
					$variations            = $hasVariations ? veBlocks()->getVariations( 'group' ) : [];
					$variationNotSelected  = ! isset( $block['settings']['_variation'] );
					$showVariationPicker   = empty( $innerBlocks ) && $hasVariations && $variationNotSelected;
				@endphp
				<div class="flex {{ $groupDir }} {{ $groupWrap }} {{ $groupAlign }} {{ $groupJustify }} min-h-[8rem] rounded border border-dashed border-gray-300 bg-gray-50/30 p-4">
					@if ( $showVariationPicker )
						{{-- Variation Placeholder (shown when block is empty and no variation selected) --}}
						@include( 'visual-editor::livewire.partials.variation-picker', [
							'blockType'        => 'group',
							'blockId'          => $blockId,
							'variations'       => $variations,
							'currentVariation' => $groupVariation,
						] )
					@elseif ( !empty( $innerBlocks ) )
						<div
							x-drag-context
							@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}' )"
							class="w-full space-y-2"
							role="list"
							aria-label="{{ __( 'Group blocks' ) }}"
						>
							@foreach ( $innerBlocks as $innerIndex => $innerBlock )
								@include( 'visual-editor::livewire.partials.block-renderer', [
									'block'          => $innerBlock,
									'blockIndex'     => $innerIndex,
									'totalBlocks'    => count( $innerBlocks ),
									'activeBlockId'  => $activeBlockId,
									'editingBlockId' => $editingBlockId,
									'depth'          => $depth + 1,
									'parentBlockId'  => $blockId,
									'slotIndex'      => null,
								] )
							@endforeach
						</div>
					@endif
					@include( 'visual-editor::livewire.partials.inner-block-appender', [
						'parentBlockId' => $blockId,
				'parentBlockType' => $blockType,
						'slotIndex'     => null,
						'depth'         => $depth + 1,
					] )
				</div>
				@break

			@case ( 'divider' )
				<hr class="my-2 border-gray-300" />
				@break

			@case ( 'spacer' )
				@php
					$spacerHeight = $block['settings']['height'] ?? '40';
					$spacerUnit   = $block['settings']['unit'] ?? 'px';
					$spacerValue  = max( 0, (int) $spacerHeight );

					if ( 'rem' === $spacerUnit ) {
						$spacerStyle = 'height: ' . $spacerValue . 'rem;';
					} else {
						$spacerStyle = 'height: ' . $spacerValue . 'px;';
					}
				@endphp
				<div
					class="relative bg-gray-100/50"
					style="{{ $spacerStyle }}"
				>
					<span class="absolute inset-0 flex items-center justify-center text-xs text-gray-400">
						{{ $spacerValue }}{{ $spacerUnit }}
					</span>
				</div>
				@break

			@case ( 'grid' )
				@php
					$gridCols = max( 1, min( 12, (int) ( $block['settings']['columns'] ?? '3' ) ) );
					$gridGapX = $block['settings']['gap_x'] ?? '';
					$gridGapY = $block['settings']['gap_y'] ?? '';
					$gridGap  = $block['settings']['gap'] ?? 'medium';

					$gapMap = [
						'none'   => '0',
						'small'  => '2',
						'medium' => '4',
						'large'  => '8',
					];

					if ( '' !== $gridGapX || '' !== $gridGapY ) {
						$gapXVal      = $gapMap[ $gridGapX ] ?? $gapMap[ $gridGap ] ?? '4';
						$gapYVal      = $gapMap[ $gridGapY ] ?? $gapMap[ $gridGap ] ?? '4';
						$gridGapClass = 'gap-x-' . $gapXVal . ' gap-y-' . $gapYVal;
					} else {
						$gridGapClass = 'gap-' . ( $gapMap[ $gridGap ] ?? '4' );
					}

					$gridItems = $block['content']['items'] ?? [];
				@endphp
				<div
					class="grid {{ $gridGapClass }}"
					style="grid-template-columns: repeat({{ $gridCols }}, minmax(0, 1fr));"
				>
					@if ( !empty( $gridItems ) )
						@foreach ( $gridItems as $gridItemIndex => $gridItem )
							@php
								$giInner = $gridItem['inner_blocks'] ?? [];
							@endphp
							<div class="min-h-[3rem] rounded border border-dashed border-gray-300 bg-gray-50/50 p-2">
								@if ( !empty( $giInner ) )
									<div
										x-drag-context
										@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}', {{ $gridItemIndex }} )"
										class="space-y-2"
										role="list"
										aria-label="{{ __( 'Grid item :index blocks', [ 'index' => $gridItemIndex + 1 ] ) }}"
									>
										@foreach ( $giInner as $innerIndex => $innerBlock )
											@include( 'visual-editor::livewire.partials.block-renderer', [
												'block'          => $innerBlock,
												'blockIndex'     => $innerIndex,
												'totalBlocks'    => count( $giInner ),
												'activeBlockId'  => $activeBlockId,
												'editingBlockId' => $editingBlockId,
												'depth'          => $depth + 1,
												'parentBlockId'  => $blockId,
												'slotIndex'      => $gridItemIndex,
											] )
										@endforeach
									</div>
								@endif
								@include( 'visual-editor::livewire.partials.inner-block-appender', [
									'parentBlockId' => $blockId,
				'parentBlockType' => $blockType,
									'slotIndex'     => $gridItemIndex,
									'depth'         => $depth + 1,
								] )
							</div>
						@endforeach
					@else
						@for ( $gi = 0; $gi < $gridCols; $gi++ )
							<div class="min-h-[3rem] rounded border border-dashed border-gray-300 bg-gray-50/50 p-2">
								@include( 'visual-editor::livewire.partials.inner-block-appender', [
									'parentBlockId' => $blockId,
				'parentBlockType' => $blockType,
									'slotIndex'     => $gi,
									'depth'         => $depth + 1,
								] )
							</div>
						@endfor
					@endif
				</div>
				@break

			@case ( 'grid_item' )
				@php
					$giColSpan   = max( 1, min( 12, (int) ( $block['settings']['col_span'] ?? '1' ) ) );
					$giRowSpan   = max( 1, min( 12, (int) ( $block['settings']['row_span'] ?? '1' ) ) );
					$giSpanStyle = 'grid-column: span ' . $giColSpan . '; grid-row: span ' . $giRowSpan . ';';

					$giDir     = match ( $block['settings']['flex_direction'] ?? 'column' ) {
						'row'   => 'flex-row',
						default => 'flex-col',
					};
					$giAlign   = match ( $block['settings']['align_items'] ?? 'stretch' ) {
						'start'  => 'items-start',
						'center' => 'items-center',
						'end'    => 'items-end',
						default  => 'items-stretch',
					};
					$giJustify = match ( $block['settings']['justify_content'] ?? 'start' ) {
						'center'  => 'justify-center',
						'end'     => 'justify-end',
						'between' => 'justify-between',
						'around'  => 'justify-around',
						'evenly'  => 'justify-evenly',
						default   => 'justify-start',
					};

					$giInnerBlocks = $block['content']['inner_blocks'] ?? [];
				@endphp
				<div
					class="flex {{ $giDir }} {{ $giAlign }} {{ $giJustify }} min-h-[3rem] rounded border border-dashed border-gray-300 bg-gray-50/50 p-2"
					style="{{ $giSpanStyle }}"
				>
					@if ( !empty( $giInnerBlocks ) )
						<div
							x-drag-context
							@drag:end="$wire.reorderBlocks( $event.detail.orderedIds, '{{ $blockId }}' )"
							class="w-full space-y-2"
							role="list"
							aria-label="{{ __( 'Grid item blocks' ) }}"
						>
							@foreach ( $giInnerBlocks as $innerIndex => $innerBlock )
								@include( 'visual-editor::livewire.partials.block-renderer', [
									'block'          => $innerBlock,
									'blockIndex'     => $innerIndex,
									'totalBlocks'    => count( $giInnerBlocks ),
									'activeBlockId'  => $activeBlockId,
									'editingBlockId' => $editingBlockId,
									'depth'          => $depth + 1,
									'parentBlockId'  => $blockId,
									'slotIndex'      => null,
								] )
							@endforeach
						</div>
					@endif
					@include( 'visual-editor::livewire.partials.inner-block-appender', [
						'parentBlockId' => $blockId,
				'parentBlockType' => $blockType,
						'slotIndex'     => null,
						'depth'         => $depth + 1,
					] )
				</div>
				@break

			@case ( 'separator' )
				@php
					$sepStyle = $block['settings']['style'] ?? 'solid';
					$sepColor = $block['settings']['color'] ?? '';
					$sepWidth = $block['settings']['width'] ?? 'full';

					$sepWidthClass = match ( $sepWidth ) {
						'wide'   => 'mx-auto w-3/4',
						'narrow' => 'mx-auto w-1/2',
						'short'  => 'mx-auto w-1/4',
						default  => 'w-full',
					};

					$sepBorderStyle = match ( $sepStyle ) {
						'dashed' => 'border-dashed',
						'dotted' => 'border-dotted',
						'wide'   => 'border-solid border-t-4',
						default  => 'border-solid',
					};

					$sepInlineStyle = '' !== $sepColor ? 'border-color: ' . e( $sepColor ) . ';' : '';
				@endphp
				<hr
					class="my-2 {{ $sepWidthClass }} {{ $sepBorderStyle }} {{ '' === $sepColor ? 'border-gray-300' : '' }}"
					@if ( '' !== $sepInlineStyle ) style="{{ $sepInlineStyle }}" @endif
				/>
				@break

			@case ( 'image' )
				<div class="flex items-center justify-center rounded bg-gray-100 p-8 text-gray-400">
					<svg class="mr-2 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
					</svg>
					{{ __( 'Image block' ) }}
				</div>
				@break

			@case ( 'button' )
				<div class="py-1">
					<span class="inline-block rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white">
						{{ $block['content']['text'] ?? __( 'Button' ) }}
					</span>
				</div>
				@break

			@default
				<div class="rounded bg-gray-50 p-3 text-sm text-gray-500">
					<span class="font-medium">{{ ucfirst( $blockType ?: __( 'Block' ) ) }}</span>
				</div>
		@endswitch
	@endif
</div>
