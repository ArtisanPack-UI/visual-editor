@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$content    = isset( $attributes['content'] ) && is_string( $attributes['content'] ) && '' !== $attributes['content']
		? $attributes['content']
		: __( 'Read more' );
	$linkTarget = isset( $attributes['linkTarget'] ) && '_blank' === $attributes['linkTarget'] ? '_blank' : '_self';
	$permalink  = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$hrefAttr = '' !== $permalink ? sprintf( ' href="%s"', e( $permalink ) ) : '';
	$targetAttr = '_blank' === $linkTarget ? ' target="_blank" rel="noopener noreferrer"' : '';
@endphp
<a{!! $hrefAttr !!}{!! $targetAttr !!}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-read-more' ] ) !!}>{{ $content }}</a>
