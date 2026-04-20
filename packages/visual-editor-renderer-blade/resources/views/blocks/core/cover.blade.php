@php
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

	$classes = [ 'wp-block-cover' ];

	$classes[] = $isDark ? 'is-dark' : 'is-light';

	if ( ! empty( $attributes['align'] ) ) {
		$classes[] = 'align' . $attributes['align'];
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$styleAttr = '';

	if ( null !== $minHeight ) {
		$styleAttr = sprintf( ' style="min-height: %s%s;"', e( (string) $minHeight ), e( $minHeightUnit ) );
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $styleAttr !!}>
	<span aria-hidden="true" class="wp-block-cover__background has-background-dim" style="opacity: {{ $dimRatio / 100 }};"></span>
	@if ( '' !== $url )
		<img class="wp-block-cover__image-background" alt="" src="{{ $url }}"/>
	@endif
	<div class="wp-block-cover__inner-container">
		{!! $innerBlocksHtml !!}
	</div>
</div>
