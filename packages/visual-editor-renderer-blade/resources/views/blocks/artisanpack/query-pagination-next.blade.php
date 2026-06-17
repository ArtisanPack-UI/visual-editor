@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedNextPageUrl'] ) && is_string( $attributes['_resolvedNextPageUrl'] ) ? $attributes['_resolvedNextPageUrl'] : '' );
	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: __( 'Next Page' );
@endphp
{{-- When the inliner could not resolve a next-page URL (e.g. on the
	last page), the leaf renders nothing at all on the front end. The
	editor still shows the muted placeholder so authors can edit the
	block; the renderers stay quiet. Issue #599. --}}
@if ( '' !== $url )
	<a{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination-next' ] ) !!} href="{{ $url }}">{{ $label }} &rarr;</a>
@endif
