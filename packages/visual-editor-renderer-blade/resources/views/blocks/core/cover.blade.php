@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$url    = (string) ( $attributes['url'] ?? '' );
	$isDark = ! isset( $attributes['isDark'] ) || ! empty( $attributes['isDark'] );

	$dimRatio = isset( $attributes['dimRatio'] ) ? (int) $attributes['dimRatio'] : 50;
	$dimRatio = max( 0, min( 100, $dimRatio ) );

	$allowedUnits  = [ 'px', 'em', 'rem', 'vh', 'vw', '%' ];
	$minHeight     = null;
	$minHeightUnit = 'px';

	if ( isset( $attributes['minHeight'] ) && is_numeric( $attributes['minHeight'] ) ) {
		$minHeight = max( 0.0, (float) $attributes['minHeight'] );

		if ( isset( $attributes['minHeightUnit'] ) && in_array( $attributes['minHeightUnit'], $allowedUnits, true ) ) {
			$minHeightUnit = (string) $attributes['minHeightUnit'];
		}
	}

	$baseClasses = [ 'wp-block-cover', $isDark ? 'is-dark' : 'is-light' ];

	// Merge cover's own `min-height` declaration with whatever
	// block-supports style emerges from compile().
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$style    = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';

	if ( null !== $minHeight ) {
		$style = ( '' !== $style ? $style . '; ' : '' ) . sprintf( 'min-height: %s%s', (string) $minHeight, $minHeightUnit );
	}

	$styleAttr = '' !== $style ? sprintf( ' style="%s"', e( $style . ';' ) ) : '';
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';
@endphp
<div class="{{ implode( ' ', $classes ) }}"{!! $styleAttr !!}{!! $idAttr !!}>
	<span aria-hidden="true" class="wp-block-cover__background has-background-dim" style="opacity: {{ $dimRatio / 100 }};"></span>
	@if ( '' !== $url )
		<img class="wp-block-cover__image-background" alt="" src="{{ $url }}"/>
	@endif
	<div class="wp-block-cover__inner-container">
		{!! $innerBlocksHtml !!}
	</div>
</div>
