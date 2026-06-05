@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'section', 'main' ], true )
		? $attributes['tagName']
		: 'div';

	$resolutionError = isset( $attributes['_resolutionError'] ) ? (string) $attributes['_resolutionError'] : '';
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query' ] ) !!}>
	@if ( '' === $resolutionError )
		{!! $innerBlocksHtml !!}
	@endif
</{{ $tag }}>
