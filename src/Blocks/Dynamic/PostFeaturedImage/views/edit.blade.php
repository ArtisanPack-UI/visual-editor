@php
	$isLink       = $content['isLink'] ?? false;
	$aspectRatio  = $content['aspectRatio'] ?? '';
	$width        = $content['width'] ?? '';
	$height       = $content['height'] ?? '';
	$scale        = $content['scale'] ?? 'cover';
	$overlayColor = $styles['overlayColor'] ?? null;
	$dimRatio     = max( 0, min( 100, (int) ( $styles['dimRatio'] ?? 0 ) ) );
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;
	$borderRadius = $styles['borderRadius'] ?? null;

	$containerStyles = 'position: relative; overflow: hidden;';

	if ( $aspectRatio ) {
		$safeAspect = preg_replace( '/[^0-9\/]/', '', $aspectRatio );
		if ( $safeAspect ) {
			$containerStyles .= " aspect-ratio: {$safeAspect};";
		}
	}

	$safeWidth = veSanitizeCssDimension( $width ?: '' );
	if ( $safeWidth && '0' !== $safeWidth ) {
		$containerStyles .= " width: {$safeWidth};";
	}

	$safeHeight = veSanitizeCssDimension( $height ?: '' );
	if ( $safeHeight && '0' !== $safeHeight ) {
		$containerStyles .= " height: {$safeHeight};";
	}

	$safeBorderRadius = veSanitizeCssDimension( $borderRadius ?: '' );
	if ( $safeBorderRadius && '0' !== $safeBorderRadius ) {
		$containerStyles .= " border-radius: {$safeBorderRadius};";
	}

	if ( is_array( $padding ) ) {
		$top    = veSanitizeCssDimension( $padding['top'] ?? '0' );
		$right  = veSanitizeCssDimension( $padding['right'] ?? '0' );
		$bottom = veSanitizeCssDimension( $padding['bottom'] ?? '0' );
		$left   = veSanitizeCssDimension( $padding['left'] ?? '0' );
		$containerStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = veSanitizeCssDimension( $margin['top'] ?? '0' );
		$bottom = veSanitizeCssDimension( $margin['bottom'] ?? '0' );
		$containerStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	$safeScale = in_array( $scale, [ 'cover', 'contain' ], true ) ? $scale : 'cover';

	$classes = 've-block ve-block-post-featured-image ve-block-editing ve-block-dynamic-preview';
@endphp

<figure
	class="{{ $classes }}"
	style="{{ $containerStyles }}"
>
	<div style="width: 100%; height: 100%; min-height: 200px; background-color: #e5e7eb; display: flex; align-items: center; justify-content: center;">
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="#9ca3af" style="width: 48px; height: 48px;">
			<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
		</svg>
	</div>
	@if ( $dimRatio > 0 && $overlayColor )
		@php
			$safeOverlay = veSanitizeCssColor( $overlayColor );
			$opacity     = $dimRatio / 100;
		@endphp
		@if ( $safeOverlay )
			<div style="position: absolute; inset: 0; background-color: {{ $safeOverlay }}; opacity: {{ $opacity }};"></div>
		@endif
	@endif
</figure>
