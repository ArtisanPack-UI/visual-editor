@php
	$src      = (string) ( $attributes['src'] ?? '' );
	$caption  = (string) ( $attributes['caption'] ?? '' );
	$autoplay = ! empty( $attributes['autoplay'] );
	$loop     = ! empty( $attributes['loop'] );
	$preload  = isset( $attributes['preload'] ) ? (string) $attributes['preload'] : 'none';

	$classes = [ 'wp-block-audio' ];

	if ( ! empty( $attributes['align'] ) ) {
		$classes[] = 'align' . $attributes['align'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$audioAttrs = ' controls';
	$audioAttrs .= sprintf( ' src="%s"', e( $src ) );
	$audioAttrs .= sprintf( ' preload="%s"', e( $preload ) );

	if ( $autoplay ) {
		$audioAttrs .= ' autoplay';
	}

	if ( $loop ) {
		$audioAttrs .= ' loop';
	}
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( '' !== $src )
		<audio{!! $audioAttrs !!}></audio>
	@endif
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
