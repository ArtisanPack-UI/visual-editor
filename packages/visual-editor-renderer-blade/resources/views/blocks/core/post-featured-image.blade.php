@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveImages;
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
	// A post without an image keeps its empty figure unsized, so a query
	// loop doesn't leave an image-sized gap where there's nothing to show.
	$figureStyles = [];

	if ( '' !== $imageUrl && '' !== $frameWidth ) {
		$figureStyles[] = 'width:' . $frameWidth;
	}

	if ( '' !== $imageUrl && '' !== $frameHeight ) {
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

	// #1031 — outside a query loop this is the template's hero, the likely
	// LCP element. `sizes` needs pixels, so a `%` or `rem` frame leaves it to
	// the file's own width.
	$responsive = '' !== $imageUrl
		? app( ResponsiveImages::class )->forImage(
			$imageUrl,
			$attributes['_resolvedImageId'] ?? null,
			ResponsiveImages::ROLE_HERO,
			1 === preg_match( '/^(\d+)px$/D', $frameWidth, $frameMatch ) ? (int) $frameMatch[1] : null,
		)
		: null;

	// A record the stamp couldn't size still knows its own dimensions.
	$width  = $width > 0 ? $width : (int) $responsive?->width;
	$height = $height > 0 ? $height : (int) $responsive?->height;

	$imgAttrs = sprintf( ' src="%s" alt="%s"', e( $imageUrl ), e( $alt ) );

	if ( $width > 0 ) {
		$imgAttrs .= sprintf( ' width="%d"', $width );
	}

	if ( $height > 0 ) {
		$imgAttrs .= sprintf( ' height="%d"', $height );
	}

	$imgAttrs .= $imgStyle . ( $responsive?->attributes() ?? '' );
	$imgTag    = $responsive?->wrap( '<img' . $imgAttrs . '/>' ) ?? '';
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
			<a{!! $linkAttrs !!}>{!! $imgTag !!}</a>
		@else
			{!! $imgTag !!}
		@endif
	@endif
</figure>
