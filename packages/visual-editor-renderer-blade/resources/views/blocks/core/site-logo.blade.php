@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$width      = isset( $attributes['width'] ) ? (int) $attributes['width'] : 0;
	$isLink     = ! isset( $attributes['isLink'] ) || ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';

	$logoUrl = UrlSanitizer::safe( isset( $attributes['_resolvedLogoUrl'] ) && is_string( $attributes['_resolvedLogoUrl'] ) ? $attributes['_resolvedLogoUrl'] : '' );
	$siteUrl = UrlSanitizer::safe( isset( $attributes['_resolvedSiteUrl'] ) && is_string( $attributes['_resolvedSiteUrl'] ) ? $attributes['_resolvedSiteUrl'] : '' );
	$alt     = isset( $attributes['_resolvedSiteTitle'] ) && is_string( $attributes['_resolvedSiteTitle'] ) ? $attributes['_resolvedSiteTitle'] : '';

	$classes = [ 'wp-block-site-logo' ];

	if ( $width <= 0 ) {
		$classes[] = 'is-default-size';
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$imgAttrs = sprintf( ' src="%s" alt="%s" class="custom-logo"', e( $logoUrl ), e( $alt ) );

	if ( $width > 0 ) {
		$imgAttrs .= sprintf( ' width="%d"', $width );
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( '' !== $logoUrl )
		@if ( $isLink && '' !== $siteUrl )
			<a href="{{ $siteUrl }}" target="{{ $linkTarget }}" rel="{{ '_blank' === $linkTarget ? 'noopener noreferrer' : 'home' }}" class="custom-logo-link"><img{!! $imgAttrs !!}/></a>
		@else
			<img{!! $imgAttrs !!}/>
		@endif
	@endif
</div>
