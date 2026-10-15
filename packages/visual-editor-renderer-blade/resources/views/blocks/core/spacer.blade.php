@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$height = $attributes['height'] ?? '100px';

	if ( is_numeric( $height ) ) {
		$height = $height . 'px';
	}

	// Spacer needs to merge its `height` declaration with whatever
	// block-supports style emerges from `compile()` (custom bg color,
	// margins, etc.) — emitting two `style="…"` attributes would let
	// the second one override the first in browsers. Build a combined
	// style string instead.
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( [ 'wp-block-spacer' ], $compiled['classes'] ) ) );

	// #877 — per-breakpoint `width` (horizontal spacers). The generic
	// emitter leaves `width` to the partial because the column block
	// maps it to `flex-basis` instead, so emit spacer widths here
	// through the same emitter with a spacer-only property map.
	$responsiveWidth = BlockSupports::compileResponsive(
		[ 'responsive' => array_intersect_key( (array) ( $attributes['responsive'] ?? [] ), [ 'width' => true ] ) ],
		[ 'width' => 'width' ],
	);

	if ( '' !== $responsiveWidth['class'] ) {
		$classes[] = $responsiveWidth['class'];

		BlockSupports::pushResponsive( $responsiveWidth['class'], $responsiveWidth['rules'] );
	}
	$style    = ( '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) . '; ' : '' ) . sprintf( 'height: %s;', (string) $height );

	$idAttr = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';
@endphp
<div aria-hidden="true" class="{{ implode( ' ', $classes ) }}" style="{{ $style }}"{!! $idAttr !!}></div>
