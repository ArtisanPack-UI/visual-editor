@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedPreviousPageUrl'] ) && is_string( $attributes['_resolvedPreviousPageUrl'] ) ? $attributes['_resolvedPreviousPageUrl'] : '' );
	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: __( 'Older Comments' );
@endphp
@if ( '' !== $url )
	<a{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comments-pagination-previous' ] ) !!} href="{{ $url }}">&larr; {{ $label }}</a>
@else
	<span{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comments-pagination-previous' ] ) !!}>{{ $label }}</span>
@endif
