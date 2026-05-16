@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$overlayMenu  = isset( $attributes['overlayMenu'] ) && is_string( $attributes['overlayMenu'] ) ? $attributes['overlayMenu'] : 'mobile';
	$orientation  = isset( $attributes['orientation'] ) && is_string( $attributes['orientation'] ) ? $attributes['orientation'] : 'horizontal';
	$itemsJustify = isset( $attributes['itemsJustification'] ) && is_string( $attributes['itemsJustification'] ) ? $attributes['itemsJustification'] : '';

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
