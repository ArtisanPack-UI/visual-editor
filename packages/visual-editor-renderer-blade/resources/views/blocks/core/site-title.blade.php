@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$rawLevel = isset( $attributes['level'] ) ? (int) $attributes['level'] : 1;
	$level    = max( 0, min( 6, $rawLevel ) );
	$tag      = 0 === $level ? 'p' : 'h' . $level;
	$isLink   = ! isset( $attributes['isLink'] ) || ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';

	$title   = isset( $attributes['_resolvedSiteTitle'] ) && is_string( $attributes['_resolvedSiteTitle'] ) ? $attributes['_resolvedSiteTitle'] : '';
	$siteUrl = UrlSanitizer::safe( isset( $attributes['_resolvedSiteUrl'] ) && is_string( $attributes['_resolvedSiteUrl'] ) ? $attributes['_resolvedSiteUrl'] : '' );
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-site-title' ] ) !!}>@if ( $isLink && '' !== $siteUrl )<a href="{{ $siteUrl }}" target="{{ $linkTarget }}" rel="{{ '_blank' === $linkTarget ? 'noopener noreferrer' : 'home' }}">{{ $title }}</a>@else{{ $title }}@endif</{{ $tag }}>
