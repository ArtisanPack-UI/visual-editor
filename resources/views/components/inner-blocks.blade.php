{{--
 * Inner Blocks Component
 *
 * Renders a container for nested child blocks within a parent block.
 * In edit mode, provides a drop zone and placeholder for empty states.
 * In save mode, renders inner blocks with minimal wrapper markup.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      2.0.0
 --}}

@php
	$isEditing        = $editing;
	$hasBlocks        = ! empty( $innerBlocks );
	$placeholderText  = $placeholder ?? __( 'visual-editor::ve.inner_blocks_placeholder' );
	$orientationClass = 'horizontal' === $orientation ? 'flex flex-row flex-wrap gap-2' : 'flex flex-col';
@endphp

@if ( $isEditing )
	<div
		id="{{ $uuid }}"
		class="ve-inner-blocks {{ $orientationClass }}"
		data-ve-inner-blocks
		@if ( $parentId ) data-parent-id="{{ $parentId }}" @endif
		@if ( $allowedBlocks ) data-allowed-blocks="{{ e( json_encode( $allowedBlocks ) ) }}" @endif
		data-orientation="{{ $orientation }}"
	>
		@if ( $hasBlocks )
			@foreach ( $innerBlocks as $innerBlock )
				{!! $innerBlock !!}
			@endforeach
		@else
			<div
				class="ve-inner-blocks-placeholder"
				contenteditable="true"
				data-placeholder="{{ $placeholderText }}"
				data-ve-enter-new-block="true"
			></div>
		@endif
	</div>
@else
	<div class="ve-inner-blocks">
		@foreach ( $innerBlocks as $innerBlock )
			{!! $innerBlock !!}
		@endforeach
	</div>
@endif
