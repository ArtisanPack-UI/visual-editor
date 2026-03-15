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

	$mapTypeMap = [
		'roadmap'   => 'm',
		'satellite' => 'k',
		'terrain'   => 'p',
		'hybrid'    => 'h',
	];

	$iframeSrc = '';
	if ( $hasCoordinates ) {
		$lat = (float) $latitude;
		$lng = (float) $longitude;

		if ( 'openstreetmap' === $provider ) {
			$span = 180 / pow( 2, $zoom );
			$bbox = ( $lng - $span ) . ',' . ( $lat - $span / 2 ) . ',' . ( $lng + $span ) . ',' . ( $lat + $span / 2 );
			$iframeSrc = "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$lat},{$lng}";
		} else {
			$query        = "{$lat},{$lng}";
			$mapTypeParam = $mapTypeMap[ $mapType ] ?? 'm';
			$iframeSrc    = "https://maps.google.com/maps?q=" . urlencode( $query ) . "&z={$zoom}&output=embed&t={$mapTypeParam}";
		}
	}
@endphp

<div
	class="ve-block ve-block-map-embed ve-block-editing"
	x-data="{
		mapAddress: '',
		mapLat: '',
		mapLng: '',
		loading: false,
		getBlockId() {
			return Alpine.store( 'selection' )?.focused;
		},
		async searchAddress() {
			const query   = this.mapAddress.trim();
			const blockId = this.getBlockId();
			if ( ! query || ! blockId ) return;

			this.loading = true;

			try {
				const response = await fetch(
					'/api/visual-editor/geocode?q=' + encodeURIComponent( query ),
					{ headers: { 'Accept': 'application/json' } }
				);
				const j = await response.json();

				if ( j.success && j.results && j.results.length > 0 ) {
					Alpine.store( 'editor' ).updateBlock( blockId, {
						address:   j.results[0].display_name || query,
						latitude:  j.results[0].lat,
						longitude: j.results[0].lon,
					} );
				}
			} catch ( e ) {
				console.error( 'Geocoding failed:', e );
			}

			this.loading = false;
		},
		setCoordinates() {
			const lat = parseFloat( this.mapLat.trim() );
			const lng = parseFloat( this.mapLng.trim() );
			if ( ! Number.isFinite( lat ) || ! Number.isFinite( lng ) ) return;
			if ( lat < -90 || lat > 90 || lng < -180 || lng > 180 ) return;

			const blockId = this.getBlockId();
			if ( blockId ) {
				Alpine.store( 'editor' ).updateBlock( blockId, {
					latitude:  String( lat ),
					longitude: String( lng ),
				} );
			}
		},
	}"
>
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
					x-model="mapAddress"
					x-on:keydown.enter.prevent="searchAddress()"
				/>
				<button
					type="button"
					class="btn btn-primary btn-sm"
					x-on:click="searchAddress()"
					:disabled="loading"
				>
					<span x-show="! loading">{{ __( 'visual-editor::ve.map_search' ) }}</span>
					<span x-show="loading" x-cloak class="loading loading-spinner loading-xs"></span>
				</button>
			</div>
			<div class="flex w-full max-w-md gap-2 mt-1">
				<input
					type="text"
					class="input input-bordered input-sm flex-1"
					placeholder="{{ __( 'visual-editor::ve.map_latitude' ) }}"
					aria-label="{{ __( 'visual-editor::ve.map_latitude' ) }}"
					x-model="mapLat"
				/>
				<input
					type="text"
					class="input input-bordered input-sm flex-1"
					placeholder="{{ __( 'visual-editor::ve.map_longitude' ) }}"
					aria-label="{{ __( 'visual-editor::ve.map_longitude' ) }}"
					x-model="mapLng"
				/>
				<button
					type="button"
					class="btn btn-ghost btn-sm"
					x-on:click="setCoordinates()"
				>
					<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
					</svg>
				</button>
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
