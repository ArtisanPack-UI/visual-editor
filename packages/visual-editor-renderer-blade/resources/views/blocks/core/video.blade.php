@php
	$src         = (string) ( $attributes['src'] ?? '' );
	$caption     = (string) ( $attributes['caption'] ?? '' );
	$autoplay    = ! empty( $attributes['autoplay'] );
	$loop        = ! empty( $attributes['loop'] );
	$muted       = ! empty( $attributes['muted'] );
	$controls    = ! isset( $attributes['controls'] ) || ! empty( $attributes['controls'] );
	$playsinline = ! empty( $attributes['playsInline'] );
	$preload     = isset( $attributes['preload'] ) ? (string) $attributes['preload'] : 'metadata';
	$poster      = (string) ( $attributes['poster'] ?? '' );

	$classes = [ 'wp-block-video' ];

	if ( ! empty( $attributes['align'] ) ) {
		$classes[] = 'align' . $attributes['align'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$videoAttrs = sprintf( ' src="%s"', e( $src ) );
	$videoAttrs .= sprintf( ' preload="%s"', e( $preload ) );

	if ( $controls ) {
		$videoAttrs .= ' controls';
	}

	if ( $autoplay ) {
		$videoAttrs .= ' autoplay';
	}

	if ( $loop ) {
		$videoAttrs .= ' loop';
	}

	if ( $muted ) {
		$videoAttrs .= ' muted';
	}

	if ( $playsinline ) {
		$videoAttrs .= ' playsinline';
	}

	if ( '' !== $poster ) {
		$videoAttrs .= sprintf( ' poster="%s"', e( $poster ) );
	}
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( '' !== $src )
		<video{!! $videoAttrs !!}></video>
	@endif
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
