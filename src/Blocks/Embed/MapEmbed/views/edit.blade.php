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

	$height = veSanitizeCssDimension( $height, '400px' );

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
@endphp

<div class="ve-block ve-block-map-embed ve-block-editing">
	@if ( ! $hasCoordinates )
		<div class="ve-map-placeholder flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 px-6 py-10" style="min-height: {{ $height }};">
			<svg class="w-10 h-10 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
				<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
			</svg>
			<p class="text-sm text-base-content/60">{{ __( 'visual-editor::ve.map_placeholder' ) }}</p>
			<div class="ve-map-address-input flex w-full max-w-md gap-2">
				<input
					type="text"
					class="input input-bordered input-sm flex-1"
					placeholder="{{ __( 'visual-editor::ve.map_address_placeholder' ) }}"
					aria-label="{{ __( 'visual-editor::ve.map_address' ) }}"
					data-ve-map-address-input
				/>
				<button
					type="button"
					class="btn btn-primary btn-sm"
					data-ve-map-search
				>{{ __( 'visual-editor::ve.map_search' ) }}</button>
			</div>
		</div>
	@else
		<div class="ve-map-wrapper" style="height: {{ $height }}; overflow: hidden;">
			@if ( $interactive && $iframeSrc )
				<iframe
					src="{{ $iframeSrc }}"
					sandbox="allow-scripts allow-same-origin"
					class="ve-map-iframe"
					title="{{ $markerLabel ?: __( 'visual-editor::ve.map_iframe_title' ) }}"
					aria-label="{{ $address ? __( 'visual-editor::ve.map_showing_location', ['address' => $address] ) : __( 'visual-editor::ve.map_showing_coordinates', ['lat' => $latitude, 'lng' => $longitude] ) }}"
					style="width: 100%; height: 100%; border: 0;"
					loading="lazy"
				></iframe>
			@else
				<div class="ve-map-static flex items-center justify-center bg-base-200 w-full h-full rounded">
					<div class="text-center">
						<svg class="w-8 h-8 text-base-content/40 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
						</svg>
						<p class="text-xs text-base-content/60">{{ $address ?: "{$latitude}, {$longitude}" }}</p>
						@if ( $markerLabel )
							<p class="text-xs font-medium text-base-content/80 mt-1">{{ $markerLabel }}</p>
						@endif
					</div>
				</div>
			@endif
		</div>
	@endif
</div>
