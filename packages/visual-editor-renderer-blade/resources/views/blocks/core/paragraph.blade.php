@php
	$classes = [ 'wp-block-paragraph' ];

	if ( ! empty( $attributes['align'] ) ) {
		$classes[] = 'has-text-align-' . $attributes['align'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<p class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">{!! $content !!}</p>
