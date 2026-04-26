@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$isLink     = ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '';
	$rel        = isset( $attributes['rel'] ) && is_string( $attributes['rel'] ) ? $attributes['rel'] : '';
	$aspect     = isset( $attributes['aspectRatio'] ) && is_string( $attributes['aspectRatio'] ) ? $attributes['aspectRatio'] : '';
	$scale      = isset( $attributes['scale'] ) && is_string( $attributes['scale'] ) ? $attributes['scale'] : '';
	$sizeSlug   = isset( $attributes['sizeSlug'] ) && is_string( $attributes['sizeSlug'] ) ? $attributes['sizeSlug'] : '';

	$imageUrl  = UrlSanitizer::safe( isset( $attributes['_resolvedImageUrl'] ) && is_string( $attributes['_resolvedImageUrl'] ) ? $attributes['_resolvedImageUrl'] : '' );
	$alt       = isset( $attributes['_resolvedImageAlt'] ) && is_string( $attributes['_resolvedImageAlt'] ) ? $attributes['_resolvedImageAlt'] : '';
	$width     = isset( $attributes['_resolvedImageWidth'] ) ? (int) $attributes['_resolvedImageWidth'] : 0;
	$height    = isset( $attributes['_resolvedImageHeight'] ) ? (int) $attributes['_resolvedImageHeight'] : 0;
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$classes = [ 'wp-block-post-featured-image' ];

	if ( '' !== $sizeSlug ) {
		$classes[] = 'size-' . $sizeSlug;
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$styleParts = [];

	if ( '' !== $aspect ) {
		$styleParts[] = 'aspect-ratio:' . $aspect;
	}

	if ( '' !== $scale ) {
		$styleParts[] = 'object-fit:' . $scale;
	}

	$style = '' !== implode( '', $styleParts ) ? sprintf( ' style="%s"', e( implode( ';', $styleParts ) ) ) : '';

	$imgAttrs = sprintf( ' src="%s" alt="%s"', e( $imageUrl ), e( $alt ) );

	if ( $width > 0 ) {
		$imgAttrs .= sprintf( ' width="%d"', $width );
	}

	if ( $height > 0 ) {
		$imgAttrs .= sprintf( ' height="%d"', $height );
	}

	$imgAttrs .= $style;
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( '' !== $imageUrl )
		@if ( $isLink && '' !== $permalink )
			@php
				$linkAttrs = sprintf( ' href="%s"', e( $permalink ) );
				if ( '_blank' === $linkTarget ) {
					$linkAttrs .= sprintf( ' target="_blank" rel="%s"', e( trim( 'noopener noreferrer ' . $rel ) ) );
				} elseif ( '' !== $rel ) {
					$linkAttrs .= sprintf( ' rel="%s"', e( $rel ) );
				}
			@endphp
			<a{!! $linkAttrs !!}><img{!! $imgAttrs !!}/></a>
		@else
			<img{!! $imgAttrs !!}/>
		@endif
	@endif
</figure>
