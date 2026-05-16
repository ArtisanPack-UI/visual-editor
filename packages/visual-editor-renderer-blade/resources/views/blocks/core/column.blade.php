@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$baseClasses = [ 'wp-block-column' ];

	if ( ! empty( $attributes['verticalAlignment'] ) ) {
		$baseClasses[] = 'is-vertically-aligned-' . $attributes['verticalAlignment'];
	}

	// `flex-basis` from the column's `width` attribute has to merge
	// with any block-supports inline style — compile() lets us splice
	// it in alongside the support declarations rather than emitting
	// two `style="…"` attributes (the second one would override the
	// first in some user agents).
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$styles   = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';

	if ( ! empty( $attributes['width'] ) ) {
		$width = $attributes['width'];

		if ( is_numeric( $width ) ) {
			$formatted = rtrim( rtrim( sprintf( '%F', (float) $width ), '0' ), '.' );
			$basis     = ( '' === $formatted ? '0' : $formatted ) . '%';
		} else {
			$basis = (string) $width;
		}

		$styles = ( '' !== $styles ? $styles . '; ' : '' ) . 'flex-basis: ' . $basis;
	}

	$styleAttr = '' !== $styles ? sprintf( ' style="%s"', e( $styles . ';' ) ) : '';
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';
@endphp
<div class="{{ implode( ' ', $classes ) }}"{!! $styleAttr !!}{!! $idAttr !!}>
	{!! $innerBlocksHtml !!}
</div>
