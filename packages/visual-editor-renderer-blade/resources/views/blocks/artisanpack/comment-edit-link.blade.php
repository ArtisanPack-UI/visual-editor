@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedEditLinkUrl'] ) && is_string( $attributes['_resolvedEditLinkUrl'] ) ? $attributes['_resolvedEditLinkUrl'] : '' );
	$label = isset( $attributes['_resolvedEditLinkLabel'] ) && is_string( $attributes['_resolvedEditLinkLabel'] ) ? $attributes['_resolvedEditLinkLabel'] : __( 'Edit' );

	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';
	$targetAttr = '_blank' === $linkTarget ? ' target="_blank" rel="noopener noreferrer"' : '';
@endphp
@if ( '' !== $url )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comment-edit-link' ] ) !!}>
		<a href="{{ $url }}"{!! $targetAttr !!}>{{ $label }}</a>
	</div>
@endif
