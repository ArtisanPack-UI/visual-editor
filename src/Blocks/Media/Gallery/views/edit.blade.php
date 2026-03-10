@php
	$columnsData = $content['columns'] ?? [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];
	if ( is_array( $columnsData ) ) {
		$columns = ( 'responsive' === ( $columnsData['mode'] ?? 'global' ) )
			? ( $columnsData['desktop'] ?? 3 )
			: ( $columnsData['global'] ?? $columnsData['desktop'] ?? 3 );
	} else {
		$columns = $columnsData;
	}
	$gap         = $content['gap'] ?? 1;
	$crop        = $content['crop'] ?? true;
	$innerBlocks = $innerBlocks ?? [];

	$gapValue = $gap . 'rem';

	$classes = 've-block ve-block-gallery ve-block-editing';
	if ( $crop ) {
		$classes .= ' ve-block-gallery--crop';
	}

	$blockId      = $blockId ?? 'gallery-' . uniqid();
	$mediaContext = $blockId . ':gallery-add';
@endphp

<figure class="{{ $classes }}">
	@if ( empty( $innerBlocks ) )
		<x-ve-block-placeholder
			icon="images"
			:block-name="__( 'visual-editor::ve.block_gallery_name' )"
			:description="__( 'visual-editor::ve.gallery_placeholder_desc' )"
		>
			<button type="button" class="btn btn-sm btn-primary" data-ve-media-context="{{ $mediaContext }}">
				{{ __( 'visual-editor::ve.placeholder_upload' ) }}
			</button>
			<button type="button" class="btn btn-sm btn-outline" data-ve-media-context="{{ $mediaContext }}">
				{{ __( 'visual-editor::ve.placeholder_media_library' ) }}
			</button>
		</x-ve-block-placeholder>
	@else
		<div
			class="ve-block-gallery__grid"
			style="display: grid; grid-template-columns: repeat({{ $columns }}, 1fr); gap: {{ $gapValue }};"
		>
			<x-ve-inner-blocks
				:allowed-blocks="['image']"
				orientation="horizontal"
			/>
		</div>
	@endif
</figure>
