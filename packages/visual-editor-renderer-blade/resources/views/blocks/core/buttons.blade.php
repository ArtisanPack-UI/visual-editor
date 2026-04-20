@php
	$classes = [ 'wp-block-buttons', 'is-layout-flex' ];

	$justify = $attributes['layout']['justifyContent'] ?? '';

	$classes[] = '' !== $justify
		? 'is-content-justification-' . $justify
		: 'is-content-justification-left';

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
</div>
