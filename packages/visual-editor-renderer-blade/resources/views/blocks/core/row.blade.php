@php
	$classes = [ 'wp-block-group', 'is-layout-flex', 'is-horizontal' ];

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
</div>
