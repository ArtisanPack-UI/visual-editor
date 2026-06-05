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
	$style    = ( '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) . '; ' : '' ) . sprintf( 'height: %s;', (string) $height );

	$idAttr = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';
@endphp
<div aria-hidden="true" class="{{ implode( ' ', $classes ) }}" style="{{ $style }}"{!! $idAttr !!}></div>
