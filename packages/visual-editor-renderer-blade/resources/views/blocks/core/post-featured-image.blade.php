@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\DuotoneSupport;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$isLink     = ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '';
	$rel        = isset( $attributes['rel'] ) && is_string( $attributes['rel'] ) ? $attributes['rel'] : '';
	$aspect     = isset( $attributes['aspectRatio'] ) && is_string( $attributes['aspectRatio'] ) ? $attributes['aspectRatio'] : '';
	$scale      = isset( $attributes['scale'] ) && is_string( $attributes['scale'] ) ? $attributes['scale'] : '';
	$frameWidth  = isset( $attributes['width'] ) && is_string( $attributes['width'] ) ? ( BlockSupports::safeCssValue( $attributes['width'] ) ?? '' ) : '';
	$frameHeight = isset( $attributes['height'] ) && is_string( $attributes['height'] ) ? ( BlockSupports::safeCssValue( $attributes['height'] ) ?? '' ) : '';
	$sizeSlug   = isset( $attributes['sizeSlug'] ) && is_string( $attributes['sizeSlug'] ) ? $attributes['sizeSlug'] : '';

	$imageUrl  = UrlSanitizer::safe( isset( $attributes['_resolvedImageUrl'] ) && is_string( $attributes['_resolvedImageUrl'] ) ? $attributes['_resolvedImageUrl'] : '' );
	$alt       = isset( $attributes['_resolvedImageAlt'] ) && is_string( $attributes['_resolvedImageAlt'] ) ? $attributes['_resolvedImageAlt'] : '';
	$width     = isset( $attributes['_resolvedImageWidth'] ) ? (int) $attributes['_resolvedImageWidth'] : 0;
	$height    = isset( $attributes['_resolvedImageHeight'] ) ? (int) $attributes['_resolvedImageHeight'] : 0;
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$baseClasses = [ 'wp-block-post-featured-image' ];

	if ( '' !== $sizeSlug ) {
		$baseClasses[] = 'size-' . $sizeSlug;
	}

	// #873 — duotone filter, scoped to the featured `<img>`. R-11: only
	// when there is an image, so a post without one emits no filter.
	$duotoneClass = '' !== $imageUrl ? DuotoneSupport::apply( $attributes, DuotoneSupport::TARGETS['core/post-featured-image'] ) : '';

	if ( '' !== $duotoneClass ) {
		$baseClasses[] = $duotoneClass;
	}

	// #948 — as core's render: `height` sizes the figure and the image,
	// `width` the figure, and a fixed frame (aspect ratio or height) crops
	// with `scale`, whose block.json default `cover` the editor never
	// saves — so an unsaved scale still covers instead of stretching.
	$figureStyles = [];

	if ( '' !== $frameWidth ) {
		$figureStyles[] = 'width:' . $frameWidth;
	}

	if ( '' !== $frameHeight ) {
		$figureStyles[] = 'height:' . $frameHeight;
	}

	$styleParts = [];

	if ( '' !== $aspect ) {
		$styleParts[] = 'aspect-ratio:' . $aspect;
	}

	if ( '' !== $frameHeight ) {
		$styleParts[] = 'height:' . $frameHeight;
	}

	if ( '' !== $scale ) {
		$styleParts[] = 'object-fit:' . $scale;
	} elseif ( '' !== $aspect || '' !== $frameHeight ) {
		$styleParts[] = 'object-fit:cover';
	}

	$imgStyle = '' !== implode( '', $styleParts ) ? sprintf( ' style="%s"', e( implode( ';', $styleParts ) ) ) : '';

	$imgAttrs = sprintf( ' src="%s" alt="%s"', e( $imageUrl ), e( $alt ) );

	if ( $width > 0 ) {
		$imgAttrs .= sprintf( ' width="%d"', $width );
	}

	if ( $height > 0 ) {
		$imgAttrs .= sprintf( ' height="%d"', $height );
	}

	$imgAttrs .= $imgStyle;
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses, $figureStyles ) !!}>
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
