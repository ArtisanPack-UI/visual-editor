@php
	$classes = [ 'wp-block-separator', 'has-alpha-channel-opacity' ];

	$style = isset( $attributes['style'] ) ? (string) $attributes['style'] : 'default';

	if ( 'wide' === $style ) {
		$classes[] = 'is-style-wide';
	} elseif ( 'dots' === $style ) {
		$classes[] = 'is-style-dots';
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<hr class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"/>
