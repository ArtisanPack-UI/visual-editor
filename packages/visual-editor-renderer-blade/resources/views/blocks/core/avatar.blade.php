@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$size       = isset( $attributes['size'] ) ? max( 1, (int) $attributes['size'] ) : 96;
	$isLink     = ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';

	$avatarUrl = UrlSanitizer::safe( isset( $attributes['_resolvedAuthorAvatar'] ) && is_string( $attributes['_resolvedAuthorAvatar'] ) ? $attributes['_resolvedAuthorAvatar'] : '' );
	$alt       = isset( $attributes['_resolvedAuthorName'] ) && is_string( $attributes['_resolvedAuthorName'] ) ? $attributes['_resolvedAuthorName'] : '';
	$authorUrl = UrlSanitizer::safe( isset( $attributes['_resolvedAuthorUrl'] ) && is_string( $attributes['_resolvedAuthorUrl'] ) ? $attributes['_resolvedAuthorUrl'] : '' );

	$linkAttrs = 'href="' . e( $authorUrl ) . '"';
	if ( '_blank' === $linkTarget ) {
		$linkAttrs .= ' target="_blank" rel="noopener noreferrer"';
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-avatar' ] ) !!}>
	@if ( '' !== $avatarUrl )
		@if ( $isLink && '' !== $authorUrl )
			<a {!! $linkAttrs !!}><img alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}" src="{{ $avatarUrl }}"/></a>
		@else
			<img alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}" src="{{ $avatarUrl }}"/>
		@endif
	@endif
</div>
