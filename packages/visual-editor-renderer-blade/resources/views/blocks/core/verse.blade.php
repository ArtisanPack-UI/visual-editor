@php
	$content = (string) ( $attributes['content'] ?? '' );

	$classes = [ 'wp-block-verse' ];

	if ( ! empty( $attributes['textAlign'] ) ) {
		$classes[] = 'has-text-align-' . $attributes['textAlign'];
	}
@endphp
<pre class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">{!! $content !!}</pre>
