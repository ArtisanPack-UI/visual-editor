@php
	$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav' ], true )
		? $attributes['tagName']
		: 'div';

	$layoutType = isset( $attributes['layout']['type'] ) ? (string) $attributes['layout']['type'] : '';

	$classes = [ 'wp-block-group' ];

	if ( 'constrained' === $layoutType ) {
		$classes[] = 'is-layout-constrained';
	} elseif ( 'flex' === $layoutType ) {
		$classes[] = 'is-layout-flex';
	} else {
		$classes[] = 'is-layout-flow';
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<{{ $tag }} class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
</{{ $tag }}>
