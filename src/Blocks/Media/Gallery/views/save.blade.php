@php
	$galleryId   = 've-gallery-' . uniqid();
	$columnsData = $content['columns'] ?? [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];
	if ( is_array( $columnsData ) ) {
		$colMode     = $columnsData['mode'] ?? 'global';
		$globalCols  = $columnsData['global'] ?? $columnsData['desktop'] ?? 3;
		$desktopCols = ( 'responsive' === $colMode ) ? ( $columnsData['desktop'] ?? 3 ) : $globalCols;
		$tabletCols  = ( 'responsive' === $colMode ) ? ( $columnsData['tablet'] ?? 2 ) : $globalCols;
		$mobileCols  = ( 'responsive' === $colMode ) ? ( $columnsData['mobile'] ?? 1 ) : $globalCols;
	} else {
		$desktopCols = $columnsData;
		$tabletCols  = $columnsData;
		$mobileCols  = $columnsData;
	}
	$gap            = $content['gap'] ?? 1;
	$crop           = $content['crop'] ?? true;
	$randomizeOrder = $content['randomizeOrder'] ?? false;
	$openInNewTab   = $content['openInNewTab'] ?? false;
	$aspectRatio    = $content['aspectRatio'] ?? 'original';
	$anchor         = $content['anchor'] ?? null;
	$htmlId         = $content['htmlId'] ?? null;
	$className      = $content['className'] ?? '';
	$innerBlocks    = $innerBlocks ?? [];

	$elementId = $htmlId ?: $anchor;
	$styleId   = $elementId ?: $galleryId;

	$gapValue = $gap . 'rem';

	$classes = 've-block ve-block-gallery';
	if ( $crop ) {
		$classes .= ' ve-block-gallery--crop';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}

	$cellStyle = '';
	if ( $crop && 'original' !== $aspectRatio ) {
		$cellStyle = 'aspect-ratio: ' . $aspectRatio . '; overflow: hidden;';
	} elseif ( $crop ) {
		$cellStyle = 'aspect-ratio: 1/1; overflow: hidden;';
	}

	if ( $randomizeOrder && ! empty( $innerBlocks ) ) {
		shuffle( $innerBlocks );
	}
@endphp

<style>
	#{{ $styleId }} .ve-block-gallery__grid { display: grid; grid-template-columns: repeat({{ $desktopCols }}, 1fr); gap: {{ $gapValue }}; }
	@@media (max-width: 1024px) { #{{ $styleId }} .ve-block-gallery__grid { grid-template-columns: repeat({{ $tabletCols }}, 1fr); } }
	@@media (max-width: 640px) { #{{ $styleId }} .ve-block-gallery__grid { grid-template-columns: repeat({{ $mobileCols }}, 1fr); } }
</style>
<figure
	class="{{ $classes }}"
	id="{{ $styleId }}"
>
	<div class="ve-block-gallery__grid">
		@foreach ( $innerBlocks as $innerBlock )
			<div class="ve-block-gallery__item" @if ( $cellStyle ) style="{{ $cellStyle }}" @endif>
				@if ( $openInNewTab && ( $innerBlock['attributes']['url'] ?? '' ) )
					<a href="{{ $innerBlock['attributes']['url'] }}" target="_blank" rel="noopener noreferrer">
						{!! $innerBlock['html'] ?? '' !!}
					</a>
				@else
					{!! $innerBlock['html'] ?? '' !!}
				@endif
			</div>
		@endforeach
	</div>
</figure>
