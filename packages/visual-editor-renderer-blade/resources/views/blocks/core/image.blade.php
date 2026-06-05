@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url     = UrlSanitizer::safe( (string) ( $attributes['url'] ?? '' ) );
	$alt     = (string) ( $attributes['alt'] ?? '' );
	$caption = (string) ( $attributes['caption'] ?? '' );
	$href    = UrlSanitizer::safe( (string) ( $attributes['href'] ?? '' ) );
	$width   = isset( $attributes['width'] ) ? (int) $attributes['width'] : null;
	$height  = isset( $attributes['height'] ) ? (int) $attributes['height'] : null;

	$baseClasses = [ 'wp-block-image' ];

	if ( ! empty( $attributes['sizeSlug'] ) ) {
		$baseClasses[] = 'size-' . $attributes['sizeSlug'];
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
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
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
