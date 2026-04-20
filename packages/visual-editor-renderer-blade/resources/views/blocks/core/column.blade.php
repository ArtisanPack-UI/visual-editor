@php
	$classes = [ 'wp-block-column' ];

	if ( ! empty( $attributes['verticalAlignment'] ) ) {
		$classes[] = 'is-vertically-aligned-' . $attributes['verticalAlignment'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$styleAttr = '';

	if ( ! empty( $attributes['width'] ) ) {
		$width     = $attributes['width'];
		$basis     = is_numeric( $width ) ? sprintf( '%d%%', (int) $width ) : (string) $width;
		$styleAttr = sprintf( ' style="flex-basis: %s;"', e( $basis ) );
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $styleAttr !!}>
	{!! $innerBlocksHtml !!}
</div>
