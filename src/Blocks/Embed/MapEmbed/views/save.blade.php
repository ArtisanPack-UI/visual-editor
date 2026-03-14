@php
	$provider    = $content['provider'] ?? 'openstreetmap';
	$latitude    = $content['latitude'] ?? '';
	$longitude   = $content['longitude'] ?? '';
	$zoom        = (int) ( $content['zoom'] ?? 13 );
	$mapType     = $content['mapType'] ?? 'roadmap';
	$address     = $content['address'] ?? '';
	$markerLabel = $content['markerLabel'] ?? '';
	$interactive = $content['interactive'] ?? true;
	$height      = $styles['height'] ?? '400px';
	$anchor      = $content['anchor'] ?? null;
	$htmlId      = $content['htmlId'] ?? null;
	$className   = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );
	$height    = veSanitizeCssDimension( $height, '400px' );

	$hasCoordinates = '' !== $latitude && '' !== $longitude && is_numeric( $latitude ) && is_numeric( $longitude );

	$allowedProviders = [ 'openstreetmap', 'google' ];
	$provider         = in_array( $provider, $allowedProviders ) ? $provider : 'openstreetmap';

	$zoom = max( 1, min( 20, $zoom ) );

	$iframeSrc = '';
	if ( $hasCoordinates ) {
		$lat = (float) $latitude;
		$lng = (float) $longitude;

		$mapTypeMap = [
			'roadmap'   => 'm',
			'satellite' => 'k',
			'terrain'   => 'p',
			'hybrid'    => 'h',
		];

		if ( 'openstreetmap' === $provider ) {
			$marker    = "mlat={$lat}&mlon={$lng}";
			$iframeSrc = "https://www.openstreetmap.org/export/embed.html?{$marker}&zoom={$zoom}&layers=mapnik";
		} else {
			$query        = $address ?: "{$lat},{$lng}";
			$mapTypeParam = $mapTypeMap[ $mapType ] ?? 'm';
			$iframeSrc    = "https://maps.google.com/maps?q=" . urlencode( $query ) . "&z={$zoom}&output=embed&t={$mapTypeParam}";
		}
	}

	$classes = 've-block ve-block-map-embed';
	if ( $className ) {
		$classes .= " {$className}";
	}

	// Build border styles from supports.
	$border       = $styles['border'] ?? [];
	$inlineStyles = "height: {$height}; overflow: hidden;";

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth     = veSanitizeCssNumber( $border['width'] ?? '0' );
		$bWidthUnit = veSanitizeCssUnit( $border['widthUnit'] ?? 'px' );
		$bStyle     = veSanitizeBorderStyle( $border['style'] ?? 'solid' );
		$bColor     = veSanitizeCssColor( $border['color'] ?? 'currentColor', 'currentColor' );
		$inlineStyles .= " border: {$bWidth}{$bWidthUnit} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadius     = veSanitizeCssNumber( $bRadius );
			$bRadiusUnit = veSanitizeCssUnit( $border['radiusUnit'] ?? 'px' );
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	$ariaLabel = $address
		? __( 'visual-editor::ve.map_showing_location', [ 'address' => $address ] )
		: ( $hasCoordinates
			? __( 'visual-editor::ve.map_showing_coordinates', [ 'lat' => $latitude, 'lng' => $longitude ] )
			: __( 'visual-editor::ve.map_iframe_title' ) );
@endphp

<div
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $hasCoordinates && $interactive && $iframeSrc )
		<iframe
			src="{{ $iframeSrc }}"
			sandbox="allow-scripts allow-same-origin"
			class="ve-map-iframe"
			title="{{ $markerLabel ?: __( 'visual-editor::ve.map_iframe_title' ) }}"
			aria-label="{{ $ariaLabel }}"
			style="width: 100%; height: 100%; border: 0;"
			loading="lazy"
		></iframe>
	@elseif ( $hasCoordinates )
		<div class="ve-map-static" role="img" aria-label="{{ $ariaLabel }}">
			<p class="ve-map-static-label">{{ $address ?: "{$latitude}, {$longitude}" }}</p>
			@if ( $markerLabel )
				<p class="ve-map-static-marker">{{ $markerLabel }}</p>
			@endif
			<noscript>
				<p>{{ $address ?: "{$latitude}, {$longitude}" }}</p>
			</noscript>
		</div>
	@endif
</div>
