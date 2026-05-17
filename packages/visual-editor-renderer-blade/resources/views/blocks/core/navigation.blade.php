@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$overlayMenu = isset( $attributes['overlayMenu'] ) && is_string( $attributes['overlayMenu'] ) ? $attributes['overlayMenu'] : 'mobile';

	// `orientation` ships as a top-level attribute on older saves and
	// under `layout.orientation` on newer ones (Gutenberg's flex
	// layout). Check both, preferring the explicit attribute.
	$layout      = is_array( $attributes['layout'] ?? null ) ? $attributes['layout'] : [];
	$orientation = isset( $attributes['orientation'] ) && is_string( $attributes['orientation'] )
		? $attributes['orientation']
		: ( isset( $layout['orientation'] ) && is_string( $layout['orientation'] ) ? $layout['orientation'] : 'horizontal' );

	// Item justification — Gutenberg writes the modern flex-layout
	// path to `layout.justifyContent`. The legacy `itemsJustification`
	// attribute is still emitted by some older saves, so honor both
	// with the modern path winning when both are set (Keystone #52).
	$itemsJustify = isset( $layout['justifyContent'] ) && is_string( $layout['justifyContent'] )
		? $layout['justifyContent']
		: ( isset( $attributes['itemsJustification'] ) && is_string( $attributes['itemsJustification'] ) ? $attributes['itemsJustification'] : '' );

	$ariaLabel = isset( $attributes['ariaLabel'] ) && is_string( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';

	$baseClasses = [ 'wp-block-navigation' ];

	if ( 'horizontal' === $orientation ) {
		$baseClasses[] = 'is-horizontal';
	} elseif ( 'vertical' === $orientation ) {
		$baseClasses[] = 'is-vertical';
	}

	if ( '' !== $itemsJustify ) {
		$baseClasses[] = 'items-justified-' . $itemsJustify;
	}

	if ( 'never' !== $overlayMenu ) {
		$baseClasses[] = 'is-responsive';
	}

	$navAttrs = '';

	if ( '' !== $ariaLabel ) {
		$navAttrs .= sprintf( ' aria-label="%s"', e( $ariaLabel ) );
	}
@endphp
<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}{!! $navAttrs !!}>
	<ul class="wp-block-navigation__container">{!! $innerBlocksHtml !!}</ul>
</nav>
