@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedPreviousPageUrl'] ) && is_string( $attributes['_resolvedPreviousPageUrl'] ) ? $attributes['_resolvedPreviousPageUrl'] : '' );
	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: __( 'Previous Page' );
@endphp
{{-- When the inliner could not resolve a previous-page URL (e.g. on
	page 1), the leaf renders nothing at all on the front end. The
	editor still shows a muted placeholder so authors can edit the
	block; the renderers stay quiet so themes don't have to style an
	always-present-but-sometimes-inactive affordance. Issue #599. --}}
@if ( '' !== $url )
	<a{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination-previous' ] ) !!} href="{{ $url }}">&larr; {{ $label }}</a>
@endif
