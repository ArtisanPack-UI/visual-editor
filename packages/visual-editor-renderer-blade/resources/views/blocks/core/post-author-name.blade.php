@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$isLink     = ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';

	$name      = isset( $attributes['_resolvedAuthorName'] ) && is_string( $attributes['_resolvedAuthorName'] ) ? $attributes['_resolvedAuthorName'] : '';
	$authorUrl = UrlSanitizer::safe( isset( $attributes['_resolvedAuthorUrl'] ) && is_string( $attributes['_resolvedAuthorUrl'] ) ? $attributes['_resolvedAuthorUrl'] : '' );

	$linkAttrs = 'href="' . e( $authorUrl ) . '"';
	if ( '_blank' === $linkTarget ) {
		$linkAttrs .= ' target="_blank" rel="noopener noreferrer"';
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-author-name' ] ) !!}>
	@if ( $isLink && '' !== $authorUrl )
		<a {!! $linkAttrs !!}>{{ $name }}</a>
	@else
		{{ $name }}
	@endif
</div>
