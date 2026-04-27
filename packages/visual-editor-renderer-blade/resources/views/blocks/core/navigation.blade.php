@php
	$overlayMenu  = isset( $attributes['overlayMenu'] ) && is_string( $attributes['overlayMenu'] ) ? $attributes['overlayMenu'] : 'mobile';
	$orientation  = isset( $attributes['orientation'] ) && is_string( $attributes['orientation'] ) ? $attributes['orientation'] : 'horizontal';
	$itemsJustify = isset( $attributes['itemsJustification'] ) && is_string( $attributes['itemsJustification'] ) ? $attributes['itemsJustification'] : '';

	$ariaLabel = isset( $attributes['ariaLabel'] ) && is_string( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';

	$classes = [ 'wp-block-navigation' ];

	if ( 'horizontal' === $orientation ) {
		$classes[] = 'is-horizontal';
	} elseif ( 'vertical' === $orientation ) {
		$classes[] = 'is-vertical';
	}

	if ( '' !== $itemsJustify ) {
		$classes[] = 'items-justified-' . $itemsJustify;
	}

	if ( 'never' !== $overlayMenu ) {
		$classes[] = 'is-responsive';
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$navAttrs = '';

	if ( '' !== $ariaLabel ) {
		$navAttrs .= sprintf( ' aria-label="%s"', e( $ariaLabel ) );
	}
@endphp
<nav class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $navAttrs !!}>
	<ul class="wp-block-navigation__container">{!! $innerBlocksHtml !!}</ul>
</nav>
