@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$ordered = ! empty( $attributes['ordered'] );
	$tag     = $ordered ? 'ol' : 'ul';

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
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-list' ] ) !!}{!! $extraAttrs !!}>{!! $children !!}</{{ $tag }}>
