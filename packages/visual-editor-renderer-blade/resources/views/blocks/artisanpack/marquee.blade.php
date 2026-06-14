@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawWidth = $attributes['marqueeWidth'] ?? 100;
	$width    = is_numeric( $rawWidth ) ? (int) round( (float) $rawWidth ) : 100;
	$width    = max( 1, min( 100, $width ) );

	$rawSpeed = $attributes['marqueeSpeed'] ?? 5;
	$speed    = is_numeric( $rawSpeed ) ? (int) round( (float) $rawSpeed ) : 5;
	$speed    = max( 1, min( 100, $speed ) );

	$content = (string) ( $attributes['marqueeContent'] ?? '' );

	$baseClasses = [ 'ap-marquee' ];

	// The wrapper width is a per-instance inline declaration that has
	// to live alongside any block-supports style (background, padding,
	// border) on the same `style="…"` attribute — emit them as one
	// declaration so a second `style="…"` doesn't override the first.
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$styles   = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';
	$styles   = ( '' !== $styles ? $styles . '; ' : '' ) . 'width: ' . $width . '%';

	$classAttr = sprintf( ' class="%s"', e( implode( ' ', $classes ) ) );
	$styleAttr = sprintf( ' style="%s"', e( $styles . ';' ) );
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';

	$textStyle = 'animation: ap-marquee-scroll ' . $speed . 's linear infinite;';
@endphp
<div{!! $classAttr . $styleAttr . $idAttr !!}>
	<p class="ap-marquee__text" style="{{ $textStyle }}">{!! $content !!}</p>
</div>
