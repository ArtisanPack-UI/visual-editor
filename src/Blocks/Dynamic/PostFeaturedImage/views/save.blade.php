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
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$imageUrl = veGetContentFeaturedImageUrl( $context );
	$imageAlt = veGetContentFeaturedImageAlt( $context );
	$permalink = veGetContentPermalink( $context );

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

	$safeScale     = in_array( $scale, [ 'cover', 'contain' ], true ) ? $scale : 'cover';
	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-post-featured-image';
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}

	$imgStyle = "width: 100%; height: 100%; object-fit: {$safeScale};";
@endphp

@if ( $imageUrl )
	<figure
		class="{{ $classes }}"
		style="{{ $containerStyles }}"
		@if ( $elementId ) id="{{ $elementId }}" @endif
	>
		@if ( $isLink && $permalink )
			<a href="{{ $permalink }}">
				<img src="{{ $imageUrl }}" alt="{{ $imageAlt }}" style="{{ $imgStyle }}" />
			</a>
		@else
			<img src="{{ $imageUrl }}" alt="{{ $imageAlt }}" style="{{ $imgStyle }}" />
		@endif
		@if ( $dimRatio > 0 && $overlayColor )
			@php
				$safeOverlay = veSanitizeCssColor( $overlayColor );
				$opacity     = $dimRatio / 100;
			@endphp
			@if ( $safeOverlay )
				<div style="position: absolute; inset: 0; background-color: {{ $safeOverlay }}; opacity: {{ $opacity }}; pointer-events: none;"></div>
			@endif
		@endif
	</figure>
@endif
