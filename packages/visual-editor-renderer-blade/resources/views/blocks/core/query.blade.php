@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'section', 'main' ], true )
		? $attributes['tagName']
		: 'div';

	$resolutionError = isset( $attributes['_resolutionError'] ) ? (string) $attributes['_resolutionError'] : '';

	// F-3 — the query supports every layout type (flow by default): the
	// layout pair, a `wp-container-*` scope for its layout settings and
	// Block spacing, and WordPress's layout modifier classes.
	$layoutClass = LayoutSupport::layoutClass( $attributes );
	$baseClasses = array_merge(
		[ 'wp-block-query' ],
		LayoutSupport::pair( 'query', $layoutClass ),
		LayoutSupport::containerClasses( $attributes, 'query', $layoutClass ),
		LayoutSupport::modifierClasses( $attributes )
	);
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	@if ( '' === $resolutionError )
		{!! $innerBlocksHtml !!}
	@endif
</{{ $tag }}>
