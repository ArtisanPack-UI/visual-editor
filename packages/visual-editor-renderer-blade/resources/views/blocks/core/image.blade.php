@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\DuotoneSupport;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url     = UrlSanitizer::safe( (string) ( $attributes['url'] ?? '' ) );
	$alt     = (string) ( $attributes['alt'] ?? '' );
	$caption = (string) ( $attributes['caption'] ?? '' );
	$href    = UrlSanitizer::safe( (string) ( $attributes['href'] ?? '' ) );

	// #1022 — the link's class / target / rel, in the save() attribute
	// order. A new-tab link always carries noopener + noreferrer, as in
	// `button.blade.php`.
	$linkClass  = trim( (string) ( $attributes['linkClass'] ?? '' ) );
	$linkTarget = (string) ( $attributes['linkTarget'] ?? '' );
	$linkRel    = trim( (string) ( $attributes['rel'] ?? '' ) );

	if ( '_blank' === $linkTarget ) {
		$relTokens = array_filter( preg_split( '/\s+/', $linkRel ) ?: [] );

		foreach ( [ 'noopener', 'noreferrer' ] as $required ) {
			if ( ! in_array( $required, $relTokens, true ) ) {
				$relTokens[] = $required;
			}
		}

		$linkRel = implode( ' ', $relTokens );
	}

	$linkAttrs = '';

	if ( '' !== $linkClass ) {
		$linkAttrs .= sprintf( ' class="%s"', e( $linkClass ) );
	}

	$linkAttrs .= sprintf( ' href="%s"', e( $href ) );

	if ( '' !== $linkTarget ) {
		$linkAttrs .= sprintf( ' target="%s"', e( $linkTarget ) );
	}

	if ( '' !== $linkRel ) {
		$linkAttrs .= sprintf( ' rel="%s"', e( $linkRel ) );
	}

	// #985 — `width` / `height` are string attributes (WordPress 6.3+:
	// `"auto"`, `"30px"`, `"50%"`), saved by the editor as an inline
	// `style` on the `<img>` plus `is-resized` on the figure. Legacy
	// numeric values (a JSON number or a unitless numeric string) keep
	// the old output: an integer HTML attribute and nothing else. A
	// string length prints the style; a whole-px one also prints the
	// HTML attribute. Anything else (hostile or unknown) is dropped.
	$resolveDimension = static function ( $value ): array {
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d+(\.\d+)?$/D', $value ) ) ) {
			return [ 'attr' => (int) $value, 'style' => null ];
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^(auto|(\d+(\.\d+)?|\.\d+)(px|em|rem|%|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|ch|ex|cm|mm|in|pt|pc))$/D', $value ) ) {
			return [ 'attr' => null, 'style' => null ];
		}

		$attr = 1 === preg_match( '/^(\d+)px$/D', $value, $matches ) ? (int) $matches[1] : null;

		return [ 'attr' => $attr, 'style' => $value ];
	};

	$width  = $resolveDimension( $attributes['width'] ?? null );
	$height = $resolveDimension( $attributes['height'] ?? null );

	$baseClasses = [ 'wp-block-image' ];

	if ( ! empty( $attributes['sizeSlug'] ) ) {
		$baseClasses[] = 'size-' . $attributes['sizeSlug'];
	}

	if ( null !== $width['style'] || null !== $height['style'] ) {
		$baseClasses[] = 'is-resized';
	}

	// #873 — duotone filter (`supports.filter.duotone`): the scope
	// class lands on the figure, the rule targets the inner `<img>`.
	// Only when the `<img>` renders, so an empty block emits no filter.
	$duotoneClass = '' !== $url ? DuotoneSupport::apply( $attributes, DuotoneSupport::TARGETS['core/image'] ) : '';

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

	// Declaration order matches the editor's `save()`: aspect-ratio,
	// object-fit, then width and height.
	if ( null !== $width['style'] ) {
		$imgStyle[] = 'width:' . $width['style'];
	}

	if ( null !== $height['style'] ) {
		$imgStyle[] = 'height:' . $height['style'];
	}

	$imgAttrs = sprintf( ' src="%s"', e( $url ) );
	$imgAttrs .= sprintf( ' alt="%s"', e( $alt ) );

	if ( '' !== $imgClass ) {
		$imgAttrs .= sprintf( ' class="%s"', e( $imgClass ) );
	}

	if ( null !== $width['attr'] ) {
		$imgAttrs .= sprintf( ' width="%d"', $width['attr'] );
	}

	if ( null !== $height['attr'] ) {
		$imgAttrs .= sprintf( ' height="%d"', $height['attr'] );
	}

	if ( [] !== $imgStyle ) {
		$imgAttrs .= sprintf( ' style="%s"', e( implode( ';', $imgStyle ) ) );
	}
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	@if ( '' !== $url )
		@if ( '' !== $href )
			<a{!! $linkAttrs !!}><img{!! $imgAttrs !!}/></a>
		@else
			<img{!! $imgAttrs !!}/>
		@endif
	@endif
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
