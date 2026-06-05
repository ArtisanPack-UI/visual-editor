@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$tagline = isset( $attributes['_resolvedSiteTagline'] ) && is_string( $attributes['_resolvedSiteTagline'] ) ? $attributes['_resolvedSiteTagline'] : '';
@endphp
<p{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-site-tagline' ] ) !!}>{{ $tagline }}</p>
