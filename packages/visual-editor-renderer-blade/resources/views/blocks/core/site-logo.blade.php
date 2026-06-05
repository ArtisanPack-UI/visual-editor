@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$width      = isset( $attributes['width'] ) ? (int) $attributes['width'] : 0;
	$isLink     = ! isset( $attributes['isLink'] ) || ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';

	$logoUrl = UrlSanitizer::safe( isset( $attributes['_resolvedLogoUrl'] ) && is_string( $attributes['_resolvedLogoUrl'] ) ? $attributes['_resolvedLogoUrl'] : '' );
	$siteUrl = UrlSanitizer::safe( isset( $attributes['_resolvedSiteUrl'] ) && is_string( $attributes['_resolvedSiteUrl'] ) ? $attributes['_resolvedSiteUrl'] : '' );
	$alt     = isset( $attributes['_resolvedSiteTitle'] ) && is_string( $attributes['_resolvedSiteTitle'] ) ? $attributes['_resolvedSiteTitle'] : '';

	$baseClasses = [ 'wp-block-site-logo' ];

	if ( $width <= 0 ) {
		$baseClasses[] = 'is-default-size';
	}

	$imgAttrs = sprintf( ' src="%s" alt="%s" class="custom-logo"', e( $logoUrl ), e( $alt ) );

	if ( $width > 0 ) {
		$imgAttrs .= sprintf( ' width="%d"', $width );
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	@if ( '' !== $logoUrl )
		@if ( $isLink && '' !== $siteUrl )
			<a href="{{ $siteUrl }}" target="{{ $linkTarget }}" rel="{{ '_blank' === $linkTarget ? 'noopener noreferrer' : 'home' }}" class="custom-logo-link"><img{!! $imgAttrs !!}/></a>
		@else
			<img{!! $imgAttrs !!}/>
		@endif
	@endif
</div>
