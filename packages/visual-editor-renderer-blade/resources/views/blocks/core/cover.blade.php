@php
	$url           = (string) ( $attributes['url'] ?? '' );
	$dimRatio      = isset( $attributes['dimRatio'] ) ? (int) $attributes['dimRatio'] : 50;
	$isDark        = ! isset( $attributes['isDark'] ) || ! empty( $attributes['isDark'] );
	$minHeight     = $attributes['minHeight'] ?? null;
	$minHeightUnit = (string) ( $attributes['minHeightUnit'] ?? 'px' );

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
		$styleAttr = sprintf( ' style="min-height: %s%s;"', e( (string) (float) $minHeight ), e( $minHeightUnit ) );
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
