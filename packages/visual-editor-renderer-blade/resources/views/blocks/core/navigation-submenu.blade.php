@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
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
@endphp
<li{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-navigation-item', 'wp-block-navigation-submenu', 'has-child' ] ) !!}>
	<a class="wp-block-navigation-item__content"{!! $linkAttrs !!}><span class="wp-block-navigation-item__label">{{ $label }}</span></a>
	<ul class="wp-block-navigation__submenu-container">{!! $innerBlocksHtml !!}</ul>
</li>
