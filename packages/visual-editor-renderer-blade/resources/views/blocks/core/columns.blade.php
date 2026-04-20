@php
	$classes = [ 'wp-block-columns' ];

	if ( ! empty( $attributes['isStackedOnMobile'] ) || ! isset( $attributes['isStackedOnMobile'] ) ) {
		$classes[] = 'is-stacked-on-mobile';
	}

	if ( ! empty( $attributes['verticalAlignment'] ) ) {
		$classes[] = 'are-vertically-aligned-' . $attributes['verticalAlignment'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
</div>
