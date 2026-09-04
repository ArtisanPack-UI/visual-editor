@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$info = ( isset( $attributes['_resolvedBusinessInfo'] ) && is_array( $attributes['_resolvedBusinessInfo'] ) )
		? $attributes['_resolvedBusinessInfo']
		: [];

	$email = isset( $info['email'] ) && is_string( $info['email'] ) ? trim( $info['email'] ) : '';

	// Defensive validity check — a garbage string from a host filter must
	// not silently render as a mailto: link. `filter_var` covers the
	// standard cases; anything else drops the block to an empty wrapper.
	$isValidEmail = '' !== $email && false !== filter_var( $email, FILTER_VALIDATE_EMAIL );

	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: $email;

	$showIcon = ! empty( $attributes['showIcon'] );

	$baseClasses = [ 'ap-business-email' ];
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	@if ( $isValidEmail )
		<a class="ap-business-email__link" href="mailto:{{ $email }}">
			@if ( $showIcon )
				<span class="ap-business-email__icon" aria-hidden="true">&#9993;</span>
			@endif
			<span class="ap-business-email__label">{{ $label }}</span>
		</a>
	@endif
</div>
