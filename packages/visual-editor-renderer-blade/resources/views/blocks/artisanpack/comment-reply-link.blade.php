@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedReplyLinkUrl'] ) && is_string( $attributes['_resolvedReplyLinkUrl'] ) ? $attributes['_resolvedReplyLinkUrl'] : '' );
	$label = isset( $attributes['_resolvedReplyLinkLabel'] ) && is_string( $attributes['_resolvedReplyLinkLabel'] ) ? $attributes['_resolvedReplyLinkLabel'] : __( 'Reply' );
@endphp
@if ( '' !== $url )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comment-reply-link' ] ) !!}>
		<a href="{{ $url }}">{{ $label }}</a>
	</div>
@endif
