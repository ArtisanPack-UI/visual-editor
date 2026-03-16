@php
	$mediaType        = $content['mediaType'] ?? 'image';
	$mediaUrl         = $content['mediaUrl'] ?? '';
	$mediaAlt         = $content['mediaAlt'] ?? '';
	$focalPoint       = $content['focalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
	$mediaPosition    = $styles['mediaPosition'] ?? 'left';
	$mediaWidth       = $styles['mediaWidth'] ?? 50;
	$verticalAlign    = $styles['verticalAlignment'] ?? 'top';
	$imageFill        = $styles['imageFill'] ?? false;
	$isStackedMobile  = $styles['isStackedOnMobile'] ?? true;
	$gridGap          = $styles['gridGap'] ?? '0';
	$borderRadius     = $styles['mediaBorderRadius'] ?? '0';
	$contentPadding   = $styles['contentPadding'] ?? '1rem';
	$contentBgColor   = $styles['contentBackgroundColor'] ?? null;
	$textColor        = $styles['textColor'] ?? null;
	$bgColor          = $styles['backgroundColor'] ?? null;
	$anchor           = $content['anchor'] ?? null;
	$htmlId           = $content['htmlId'] ?? null;
	$className        = $content['className'] ?? '';

	$elementId   = veSanitizeHtmlId( $htmlId ?: $anchor );
	$innerBlocks = $innerBlocks ?? [];

	$focalX = is_array( $focalPoint ) ? ( $focalPoint['x'] ?? 0.5 ) : 0.5;
	$focalY = is_array( $focalPoint ) ? ( $focalPoint['y'] ?? 0.5 ) : 0.5;
	$focalX = max( 0, min( 1, (float) $focalX ) );
	$focalY = max( 0, min( 1, (float) $focalY ) );
	$objectPosition = round( $focalX * 100 ) . '% ' . round( $focalY * 100 ) . '%';

	$allowedPositions = [ 'left', 'right' ];
	$mediaPosition    = in_array( $mediaPosition, $allowedPositions, true ) ? $mediaPosition : 'left';

	$mediaWidth   = max( 25, min( 75, (int) $mediaWidth ) );
	$contentWidth = 100 - $mediaWidth;

	$alignMap = [
		'top'    => 'flex-start',
		'center' => 'center',
		'bottom' => 'flex-end',
	];
	$alignItemsValue = $alignMap[ $verticalAlign ] ?? 'flex-start';

	$textColor    = $textColor ? veSanitizeCssColor( $textColor ) : null;
	$bgColor      = $bgColor ? veSanitizeCssColor( $bgColor ) : null;
	$contentBgColor = $contentBgColor ? veSanitizeCssColor( $contentBgColor ) : null;
	$gridGap      = veSanitizeCssDimension( $gridGap, '0' );
	$borderRadius = veSanitizeCssDimension( $borderRadius, '0' );
	$contentPadding = veSanitizeCssDimension( $contentPadding, '1rem' );

	$styleId    = 've-mt-' . ( $elementId ?: substr( md5( uniqid() ), 0, 8 ) );
	$renderedId = $elementId ?: $styleId;

	$containerStyles = "display: grid; grid-template-columns: {$mediaWidth}% {$contentWidth}%; align-items: {$alignItemsValue}; gap: {$gridGap};";
	if ( 'right' === $mediaPosition ) {
		$containerStyles = "display: grid; grid-template-columns: {$contentWidth}% {$mediaWidth}%; align-items: {$alignItemsValue}; gap: {$gridGap};";
	}
	if ( $textColor ) {
		$containerStyles .= " color: {$textColor};";
	}
	if ( $bgColor ) {
		$containerStyles .= " background-color: {$bgColor};";
	}

	$mediaStyles = '';
	if ( $imageFill && 'image' === $mediaType ) {
		$mediaStyles .= 'position: relative; min-height: 250px;';
	}
	$mediaStyles .= 'right' === $mediaPosition ? ' order: 1;' : ' order: 0;';
	if ( $borderRadius && '0' !== $borderRadius ) {
		$mediaStyles .= " overflow: hidden; border-radius: {$borderRadius};";
	}

	$contentStyles = "padding: {$contentPadding};";
	if ( $contentBgColor ) {
		$contentStyles .= " background-color: {$contentBgColor};";
	}
	$contentStyles .= 'right' === $mediaPosition ? ' order: 0;' : ' order: 1;';

	$classes = 've-block ve-block-media-text';
	if ( $isStackedMobile ) {
		$classes .= ' ve-media-text--stacked-mobile';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

@if ( $isStackedMobile )
<style>
	@@media (max-width: 600px) {
		#{{ $renderedId }}.ve-media-text--stacked-mobile {
			grid-template-columns: 1fr !important;
		}
		#{{ $renderedId }}.ve-media-text--stacked-mobile .ve-media-text__media {
			order: 0 !important;
		}
		#{{ $renderedId }}.ve-media-text--stacked-mobile .ve-media-text__content {
			order: 1 !important;
		}
	}
</style>
@endif

<div
	class="{{ $classes }}"
	style="{{ $containerStyles }}"
	id="{{ $renderedId }}"
>
	{{-- Media side --}}
	<div
		class="ve-media-text__media"
		style="{{ $mediaStyles }}"
	>
		@if ( 'image' === $mediaType && $mediaUrl )
			@if ( $imageFill )
				<img
					src="{{ $mediaUrl }}"
					alt="{{ $mediaAlt }}"
					style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $objectPosition }};"
					@if ( ! $mediaAlt ) aria-hidden="true" @endif
				/>
			@else
				<img
					src="{{ $mediaUrl }}"
					alt="{{ $mediaAlt }}"
					style="width: 100%; height: auto; display: block;"
					@if ( ! $mediaAlt ) aria-hidden="true" @endif
				/>
			@endif
		@elseif ( 'video' === $mediaType && $mediaUrl )
			<video
				src="{{ $mediaUrl }}"
				autoplay
				muted
				loop
				playsinline
				style="width: 100%; height: 100%; object-fit: cover; display: block;"
				aria-hidden="true"
			></video>
		@endif
	</div>

	{{-- Content side --}}
	<div
		class="ve-media-text__content"
		style="{{ $contentStyles }}"
	>
		@foreach ( $innerBlocks as $innerBlock )
			{!! $innerBlock !!}
		@endforeach
	</div>
</div>
