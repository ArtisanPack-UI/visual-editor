@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawLevel = $attributes['skillLevel'] ?? 50;
	$level    = is_numeric( $rawLevel ) ? (int) round( (float) $rawLevel ) : 50;
	$level    = max( 1, min( 100, $level ) );

	$rawHeight = $attributes['barHeight'] ?? 5;
	$height    = is_numeric( $rawHeight ) ? (int) round( (float) $rawHeight ) : 5;
	$height    = max( 1, min( 100, $height ) );

	$barColor   = (string) ( $attributes['barColor'] ?? '' );
	$trackColor = (string) ( $attributes['trackColor'] ?? '' );
	$ariaLabel  = (string) ( $attributes['ariaLabel'] ?? '' );

	// The colour values come straight from a block attribute and end up
	// in an inline `style="…"` declaration. Allow only hex + the simple
	// numeric colour functions so an author can't break out of the
	// declaration with a stray `;` / `}` / `expression(...)` payload.
	$safeColor = static function ( string $color ): string {
		if ( '' === $color ) {
			return '';
		}
		if ( preg_match( '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color ) ) {
			return $color;
		}
		if ( preg_match( '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\)$/', $color ) ) {
			return $color;
		}
		return '';
	};
	$barColor   = $safeColor( $barColor );
	$trackColor = $safeColor( $trackColor );

	$baseClasses = [ 'ap-skills-slider' ];

	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$styles   = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';

	$classAttr = sprintf( ' class="%s"', e( implode( ' ', $classes ) ) );
	$styleAttr = '' !== $styles ? sprintf( ' style="%s"', e( $styles . ';' ) ) : '';
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';

	$trackStyle = 'height: ' . $height . 'px';
	if ( '' !== $trackColor ) {
		$trackStyle .= '; background-color: ' . $trackColor;
	}
	$trackStyle .= ';';

	$barStyle = 'width: ' . $level . '%; height: ' . $height . 'px';
	if ( '' !== $barColor ) {
		$barStyle .= '; background-color: ' . $barColor;
	}
	$barStyle .= ';';

	$ariaLabelText = '' !== $ariaLabel ? $ariaLabel : 'Skill level';
	$ariaLabelAttr = sprintf( ' aria-label="%s"', e( $ariaLabelText ) );
@endphp
<div{!! $classAttr . $styleAttr . $idAttr !!}>
	<div class="ap-skills-slider__track" style="{{ $trackStyle }}" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $level }}"{!! $ariaLabelAttr !!}>
		<div class="ap-skills-slider__bar" style="{{ $barStyle }}"></div>
	</div>
</div>
