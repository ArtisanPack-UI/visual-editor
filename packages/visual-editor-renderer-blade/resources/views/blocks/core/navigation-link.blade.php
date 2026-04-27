@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$label      = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
	$url        = UrlSanitizer::safe( isset( $attributes['url'] ) && is_string( $attributes['url'] ) ? $attributes['url'] : '' );
	$opensInNew = ! empty( $attributes['opensInNewTab'] );
	$rel        = isset( $attributes['rel'] ) && is_string( $attributes['rel'] ) ? $attributes['rel'] : '';
	$title      = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : '';
	$description = isset( $attributes['description'] ) && is_string( $attributes['description'] ) ? $attributes['description'] : '';

	$classes = [ 'wp-block-navigation-item', 'wp-block-navigation-link' ];

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$linkAttrs = '' !== $url ? sprintf( ' href="%s"', e( $url ) ) : '';

	if ( $opensInNew ) {
		$linkAttrs .= sprintf( ' target="_blank" rel="%s"', e( trim( 'noopener noreferrer ' . $rel ) ) );
	} elseif ( '' !== $rel ) {
		$linkAttrs .= sprintf( ' rel="%s"', e( $rel ) );
	}

	if ( '' !== $title ) {
		$linkAttrs .= sprintf( ' title="%s"', e( $title ) );
	}
@endphp
<li class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	<a class="wp-block-navigation-item__content"{!! $linkAttrs !!}><span class="wp-block-navigation-item__label">{{ $label }}</span>@if ( '' !== $description )<span class="wp-block-navigation-item__description">{{ $description }}</span>@endif</a>
</li>
