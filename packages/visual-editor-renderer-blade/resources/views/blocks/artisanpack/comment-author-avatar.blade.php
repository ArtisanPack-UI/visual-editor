@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url = UrlSanitizer::safe( isset( $attributes['_resolvedAvatarUrl'] ) && is_string( $attributes['_resolvedAvatarUrl'] ) ? $attributes['_resolvedAvatarUrl'] : '' );
	$alt = isset( $attributes['_resolvedAvatarAlt'] ) && is_string( $attributes['_resolvedAvatarAlt'] ) ? $attributes['_resolvedAvatarAlt'] : '';

	$width  = isset( $attributes['width'] ) && is_numeric( $attributes['width'] ) ? (int) $attributes['width'] : 96;
	$height = isset( $attributes['height'] ) && is_numeric( $attributes['height'] ) ? (int) $attributes['height'] : 96;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comment-author-avatar' ] ) !!}>
	@if ( '' !== $url )
		<img src="{{ $url }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}" loading="lazy" />
	@endif
</div>
