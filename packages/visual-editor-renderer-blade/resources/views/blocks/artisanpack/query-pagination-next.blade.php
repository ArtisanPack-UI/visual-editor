@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedNextPageUrl'] ) && is_string( $attributes['_resolvedNextPageUrl'] ) ? $attributes['_resolvedNextPageUrl'] : '' );
	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: __( 'Next Page' );
@endphp
@if ( '' !== $url )
	<a{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination-next' ] ) !!} href="{{ $url }}">{{ $label }} &rarr;</a>
@else
	<span{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination-next' ] ) !!}>{{ $label }}</span>
@endif
