@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\DuotoneSupport;
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

	// #873 — duotone filter (`supports.filter.duotone`): the scope
	// class lands on the figure, the rule targets the inner `<img>`.
	$duotoneClass = DuotoneSupport::apply( $attributes, 'img' );

	if ( '' !== $duotoneClass ) {
		$baseClasses[] = $duotoneClass;
	}

	$imgClass = ! empty( $attributes['id'] ) ? 'wp-image-' . (int) $attributes['id'] : '';

	// #873 — aspect ratio + scale (object-fit) live on the `<img>`, as
	// the editor's save output and `post-featured-image` emit them.
	$aspectRatio = is_string( $attributes['aspectRatio'] ?? null )
		? BlockSupports::safeCssValue( $attributes['aspectRatio'] )
		: null;
	$scale       = $attributes['scale'] ?? null;
	$imgStyle    = [];

	if ( null !== $aspectRatio ) {
		$imgStyle[] = 'aspect-ratio:' . trim( $aspectRatio );
	}

	if ( is_string( $scale ) && in_array( $scale, [ 'cover', 'contain', 'fill', 'none', 'scale-down' ], true ) ) {
		$imgStyle[] = 'object-fit:' . $scale;
	}

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

	if ( [] !== $imgStyle ) {
		$imgAttrs .= sprintf( ' style="%s"', e( implode( ';', $imgStyle ) ) );
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
