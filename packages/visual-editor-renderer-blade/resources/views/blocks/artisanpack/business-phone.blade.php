@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$info = ( isset( $attributes['_resolvedBusinessInfo'] ) && is_array( $attributes['_resolvedBusinessInfo'] ) )
		? $attributes['_resolvedBusinessInfo']
		: [];

	$phone = isset( $info['phone'] ) && is_string( $info['phone'] ) ? trim( $info['phone'] ) : '';

	// Strip everything except digits, `+`, and `x` for the tel: target so
	// screen readers and dialers get an unambiguous number. Keep the
	// original string for display.
	$telTarget = '' === $phone ? '' : preg_replace( '/[^0-9+xX,]/', '', $phone );

	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: $phone;

	$showIcon = ! empty( $attributes['showIcon'] );

	$baseClasses = [ 'ap-business-phone' ];
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	@if ( '' !== $phone )
		<a class="ap-business-phone__link" href="tel:{{ $telTarget }}">
			@if ( $showIcon )
				<span class="ap-business-phone__icon" aria-hidden="true">&#9742;</span>
			@endif
			<span class="ap-business-phone__label">{{ $label }}</span>
		</a>
	@endif
</div>
