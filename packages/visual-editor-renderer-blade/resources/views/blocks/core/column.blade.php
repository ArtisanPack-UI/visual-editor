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
		$width = $attributes['width'];

		if ( is_numeric( $width ) ) {
			$formatted = rtrim( rtrim( sprintf( '%F', (float) $width ), '0' ), '.' );
			$basis     = ( '' === $formatted ? '0' : $formatted ) . '%';
		} else {
			$basis = (string) $width;
		}

		$styleAttr = sprintf( ' style="flex-basis: %s;"', e( $basis ) );
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $styleAttr !!}>
	{!! $innerBlocksHtml !!}
</div>
