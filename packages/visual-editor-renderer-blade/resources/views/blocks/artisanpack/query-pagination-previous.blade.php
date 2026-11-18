@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedPreviousPageUrl'] ) && is_string( $attributes['_resolvedPreviousPageUrl'] ) ? $attributes['_resolvedPreviousPageUrl'] : '' );
	$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
		? $attributes['label']
		: __( 'Previous Page' );

	// #1130 — the wrapper's Arrow and Show label settings, handed down by
	// QueryInliner as in WordPress. With no arrow the label always shows,
	// so the link is never empty; a hidden label stays for screen readers.
	$arrow     = isset( $attributes['_resolvedPaginationArrow'] ) && in_array( $attributes['_resolvedPaginationArrow'], [ 'arrow', 'chevron' ], true )
		? $attributes['_resolvedPaginationArrow']
		: 'none';
	$showLabel = 'none' === $arrow || ! array_key_exists( '_resolvedShowLabel', $attributes ) || false !== $attributes['_resolvedShowLabel'];
	$glyph     = [ 'arrow' => '←', 'chevron' => '«' ][ $arrow ] ?? '';
	$labelHtml = $showLabel ? e( $label ) : '<span class="screen-reader-text">' . e( $label ) . '</span>';
	$arrowHtml = '' !== $glyph ? '<span class="wp-block-query-pagination-previous-arrow is-arrow-' . $arrow . '" aria-hidden="true">' . $glyph . '</span>' : '';
@endphp
{{-- When the inliner could not resolve a previous-page URL (e.g. on
	page 1), the leaf renders nothing at all on the front end. The
	editor still shows a muted placeholder so authors can edit the
	block; the renderers stay quiet so themes don't have to style an
	always-present-but-sometimes-inactive affordance. Issue #599. --}}
@if ( '' !== $url )
	<a{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination-previous' ] ) !!} href="{{ $url }}">{!! $arrowHtml . $labelHtml !!}</a>
@endif
