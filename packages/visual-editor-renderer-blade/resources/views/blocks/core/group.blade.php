@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav' ], true )
		? $attributes['tagName']
		: 'div';

	$layoutType = isset( $attributes['layout']['type'] ) ? (string) $attributes['layout']['type'] : '';

	if ( 'constrained' === $layoutType ) {
		$layoutClass = 'is-layout-constrained';
	} elseif ( 'flex' === $layoutType ) {
		$layoutClass = 'is-layout-flex';
	} elseif ( 'grid' === $layoutType ) {
		$layoutClass = 'is-layout-grid';
	} else {
		$layoutClass = 'is-layout-flow';
	}
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-group', $layoutClass ] ) !!}>
	{!! $innerBlocksHtml !!}
</{{ $tag }}>
