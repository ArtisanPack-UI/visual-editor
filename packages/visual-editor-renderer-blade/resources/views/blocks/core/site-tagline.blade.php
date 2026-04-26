@php
	$align = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';

	$tagline = isset( $attributes['_resolvedSiteTagline'] ) && is_string( $attributes['_resolvedSiteTagline'] ) ? $attributes['_resolvedSiteTagline'] : '';

	$classes = [ 'wp-block-site-tagline' ];

	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<p class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">{{ $tagline }}</p>
