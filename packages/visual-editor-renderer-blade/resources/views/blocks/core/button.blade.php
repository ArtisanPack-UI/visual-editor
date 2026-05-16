@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$text   = (string) ( $attributes['text'] ?? '' );
	$url    = UrlSanitizer::safe( (string) ( $attributes['url'] ?? '' ) );
	$target = (string) ( $attributes['linkTarget'] ?? '' );
	$rel    = (string) ( $attributes['rel'] ?? '' );
	$title  = (string) ( $attributes['title'] ?? '' );

	if ( '_blank' === $target ) {
		$relTokens = array_filter( preg_split( '/\s+/', $rel ) ?: [] );

		foreach ( [ 'noopener', 'noreferrer' ] as $required ) {
			if ( ! in_array( $required, $relTokens, true ) ) {
				$relTokens[] = $required;
			}
		}

		$rel = implode( ' ', $relTokens );
	}

	// `core/button` splits its attributes between the outer wrapper
	// (block-level — align + className + style variations) and the
	// inner link / span (visual — color, border, spacing, typography).
	// This mirrors WP core's behavior: theme.json's
	// `styles.elements.button` targets `.wp-element-button`, which is
	// the inner element, so the visual block-supports have to land
	// there to take effect.
	$wrapperAttrs = [
		'align'     => $attributes['align'] ?? null,
		'className' => $attributes['className'] ?? null,
		'anchor'    => $attributes['anchor'] ?? null,
	];

	$innerAttrs = $attributes;
	unset( $innerAttrs['align'], $innerAttrs['className'], $innerAttrs['anchor'] );

	$linkAttrString = '';

	if ( '' !== $target ) {
		$linkAttrString .= sprintf( ' target="%s"', e( $target ) );
	}

	if ( '' !== $rel ) {
		$linkAttrString .= sprintf( ' rel="%s"', e( $rel ) );
	}

	if ( '' !== $title ) {
		$linkAttrString .= sprintf( ' title="%s"', e( $title ) );
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $wrapperAttrs, [ 'wp-block-button' ] ) !!}>
	@if ( '' !== $url )
		<a{!! BlockSupports::wrapperAttrs( $innerAttrs, [ 'wp-block-button__link', 'wp-element-button' ] ) !!} href="{{ $url }}"{!! $linkAttrString !!}>{!! $text !!}</a>
	@else
		<span{!! BlockSupports::wrapperAttrs( $innerAttrs, [ 'wp-block-button__link', 'wp-element-button' ] ) !!}>{!! $text !!}</span>
	@endif
</div>
