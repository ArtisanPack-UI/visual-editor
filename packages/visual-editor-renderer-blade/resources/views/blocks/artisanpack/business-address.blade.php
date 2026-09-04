@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$info = ( isset( $attributes['_resolvedBusinessInfo'] ) && is_array( $attributes['_resolvedBusinessInfo'] ) )
		? $attributes['_resolvedBusinessInfo']
		: [];

	$address = ( isset( $info['address'] ) && is_array( $info['address'] ) ) ? $info['address'] : [];

	$street     = isset( $address['street'] ) ? (string) $address['street'] : '';
	$street2    = isset( $address['street2'] ) ? (string) $address['street2'] : '';
	$city       = isset( $address['city'] ) ? (string) $address['city'] : '';
	$region     = isset( $address['region'] ) ? (string) $address['region'] : '';
	$postalCode = isset( $address['postal_code'] ) ? (string) $address['postal_code'] : '';
	$country    = isset( $address['country'] ) ? (string) $address['country'] : '';

	$hasAddress = '' !== ( $street . $street2 . $city . $region . $postalCode . $country );

	$mapEmbedUrl = null;
	if ( isset( $info['mapEmbedUrl'] ) && is_string( $info['mapEmbedUrl'] ) ) {
		$sanitized   = UrlSanitizer::safe( $info['mapEmbedUrl'] );
		$mapEmbedUrl = '' === $sanitized ? null : $sanitized;
	}

	$baseClasses = [ 'ap-business-address' ];
	$ariaLabel   = (string) ( $attributes['ariaLabel'] ?? __( 'Business address' ) );
@endphp
<section{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} aria-label="{{ $ariaLabel }}">
	@if ( $hasAddress )
		<address class="ap-business-address__address">
			@if ( '' !== $street ){{ $street }}@endif
			@if ( '' !== $street2 )<br />{{ $street2 }}@endif
			@if ( '' !== $city || '' !== $region || '' !== $postalCode )
				<br />{{ trim( $city . ( '' !== $region ? ( '' !== $city ? ', ' : '' ) . $region : '' ) . ( '' !== $postalCode ? ' ' . $postalCode : '' ) ) }}
			@endif
			@if ( '' !== $country )<br />{{ $country }}@endif
		</address>
	@endif
	@if ( null !== $mapEmbedUrl )
		<div class="ap-business-address__map">
			<iframe
				src="{{ $mapEmbedUrl }}"
				title="{{ __( 'Map' ) }}"
				loading="lazy"
				referrerpolicy="no-referrer"
				sandbox="allow-scripts allow-same-origin allow-popups"
				style="border:0;width:100%;height:100%;min-height:300px;"
				allowfullscreen
			></iframe>
		</div>
	@endif
</section>
