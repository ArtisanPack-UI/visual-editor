@php
	$level = (int) ( $attributes['level'] ?? 2 );
	$level = max( 1, min( 6, $level ) );
	$tag   = 'h' . $level;

	$classes = [ 'wp-block-heading' ];

	if ( ! empty( $attributes['textAlign'] ) ) {
		$classes[] = 'has-text-align-' . $attributes['textAlign'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<{{ $tag }} class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">{!! $content !!}</{{ $tag }}>
