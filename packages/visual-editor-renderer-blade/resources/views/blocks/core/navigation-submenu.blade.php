@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\NavigationCurrentState;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$label      = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
	$url        = UrlSanitizer::safe( isset( $attributes['url'] ) && is_string( $attributes['url'] ) ? $attributes['url'] : '' );
	$opensInNew = ! empty( $attributes['opensInNewTab'] );
	$rel        = isset( $attributes['rel'] ) && is_string( $attributes['rel'] ) ? $attributes['rel'] : '';

	$linkAttrs = '' !== $url ? sprintf( ' href="%s"', e( $url ) ) : '';

	if ( $opensInNew ) {
		$linkAttrs .= sprintf( ' target="_blank" rel="%s"', e( trim( 'noopener noreferrer ' . $rel ) ) );
	} elseif ( '' !== $rel ) {
		$linkAttrs .= sprintf( ' rel="%s"', e( $rel ) );
	}

	// #975 — WordPress parity: the submenu's own link is marked like a
	// navigation link, and the submenu gets `current-menu-ancestor` when
	// it leads to the page being viewed (its own URL is a parent path, or
	// any link below it is current or a parent path).
	$currentUrl     = NavigationCurrentState::requestUrl();
	$currentState   = NavigationCurrentState::match( $url, $currentUrl );
	$currentClasses = NavigationCurrentState::CURRENT === $currentState ? [ 'current-menu-item' ] : [];

	if ( NavigationCurrentState::ANCESTOR === $currentState || NavigationCurrentState::hasMatchingDescendant( $innerBlocks ?? [], $currentUrl ) ) {
		$currentClasses[] = 'current-menu-ancestor';
	}

	if ( NavigationCurrentState::CURRENT === $currentState ) {
		$linkAttrs .= ' aria-current="page"';
	}
@endphp
<li{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-navigation-item', 'wp-block-navigation-submenu', 'has-child', ...$currentClasses ] ) !!}>
	<a class="wp-block-navigation-item__content"{!! $linkAttrs !!}><span class="wp-block-navigation-item__label">{{ $label }}</span></a>
	<ul class="wp-block-navigation__submenu-container">{!! $innerBlocksHtml !!}</ul>
</li>
