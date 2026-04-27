@php
	$align = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';

	$classes = [ 'entry-content', 'wp-block-post-content' ];

	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$content = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] ) ? $attributes['_resolvedContent'] : '';
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">{!! $content !!}</div>
