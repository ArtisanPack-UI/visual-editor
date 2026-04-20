@php
	$classes = [ 'wp-block-buttons', 'is-layout-flex', 'is-content-justification-left' ];

	if ( ! empty( $attributes['layout']['justifyContent'] ) ) {
		$classes[] = 'is-content-justification-' . $attributes['layout']['justifyContent'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
</div>
