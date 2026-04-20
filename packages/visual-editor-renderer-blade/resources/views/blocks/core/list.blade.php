@php
	$ordered = ! empty( $attributes['ordered'] );
	$tag     = $ordered ? 'ol' : 'ul';

	$classes = [ 'wp-block-list' ];

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$start   = isset( $attributes['start'] ) ? (int) $attributes['start'] : null;
	$reverse = ! empty( $attributes['reversed'] );

	$extraAttrs = '';

	if ( $ordered && null !== $start ) {
		$extraAttrs .= sprintf( ' start="%d"', $start );
	}

	if ( $ordered && $reverse ) {
		$extraAttrs .= ' reversed';
	}

	$legacyValues = (string) ( $attributes['values'] ?? '' );
	$children     = '' !== trim( $innerBlocksHtml ) ? $innerBlocksHtml : $legacyValues;
@endphp
<{{ $tag }} class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $extraAttrs !!}>{!! $children !!}</{{ $tag }}>
