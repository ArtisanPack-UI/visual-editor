@php
	$url     = (string) ( $attributes['url'] ?? '' );
	$alt     = (string) ( $attributes['alt'] ?? '' );
	$caption = (string) ( $attributes['caption'] ?? '' );
	$href    = (string) ( $attributes['href'] ?? '' );
	$width   = isset( $attributes['width'] ) ? (int) $attributes['width'] : null;
	$height  = isset( $attributes['height'] ) ? (int) $attributes['height'] : null;

	$classes = [ 'wp-block-image' ];

	if ( ! empty( $attributes['align'] ) ) {
		$classes[] = 'align' . $attributes['align'];
	}

	if ( ! empty( $attributes['sizeSlug'] ) ) {
		$classes[] = 'size-' . $attributes['sizeSlug'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$imgClass = ! empty( $attributes['id'] ) ? 'wp-image-' . (int) $attributes['id'] : '';

	$imgAttrs = sprintf( ' src="%s"', e( $url ) );
	$imgAttrs .= sprintf( ' alt="%s"', e( $alt ) );

	if ( '' !== $imgClass ) {
		$imgAttrs .= sprintf( ' class="%s"', e( $imgClass ) );
	}

	if ( null !== $width ) {
		$imgAttrs .= sprintf( ' width="%d"', $width );
	}

	if ( null !== $height ) {
		$imgAttrs .= sprintf( ' height="%d"', $height );
	}
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( '' !== $url )
		@if ( '' !== $href )
			<a href="{{ $href }}"><img{!! $imgAttrs !!}/></a>
		@else
			<img{!! $imgAttrs !!}/>
		@endif
	@endif
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
