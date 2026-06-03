@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$type      = isset( $attributes['type'] ) && 'previous' === $attributes['type'] ? 'previous' : 'next';
	$label     = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
	$showTitle = ! empty( $attributes['showTitle'] );
	$arrow     = isset( $attributes['arrow'] ) && is_string( $attributes['arrow'] ) ? $attributes['arrow'] : 'none';

	$urlKey   = 'previous' === $type ? '_resolvedPrevUrl' : '_resolvedNextUrl';
	$titleKey = 'previous' === $type ? '_resolvedPrevTitle' : '_resolvedNextTitle';

	$url   = UrlSanitizer::safe( isset( $attributes[ $urlKey ] ) && is_string( $attributes[ $urlKey ] ) ? $attributes[ $urlKey ] : '' );
	$title = isset( $attributes[ $titleKey ] ) && is_string( $attributes[ $titleKey ] ) ? $attributes[ $titleKey ] : '';

	$glyph = '';
	if ( 'arrow' === $arrow ) {
		$glyph = 'previous' === $type ? '←' : '→';
	} elseif ( 'chevron' === $arrow ) {
		$glyph = 'previous' === $type ? '«' : '»';
	}

	// Compose the link text:
	//  - `label` (when provided) replaces the post title; the title is
	//    appended when `showTitle` is on.
	//  - Otherwise the resolved adjacent-post title is the link text.
	// The `aria-label` is always the unconditional "Previous post" /
	// "Next post" string so screen readers get a consistent landmark
	// regardless of how the visible label is configured.
	$visible = '';
	if ( $showTitle ) {
		if ( '' !== $label ) {
			$visible = $label . $title;
		} else {
			$visible = $title;
		}
	} elseif ( '' !== $label ) {
		$visible = $label;
	} else {
		$visible = $title;
	}

	if ( '' !== $glyph ) {
		$visible = 'previous' === $type
			? $glyph . ' ' . $visible
			: $visible . ' ' . $glyph;
	}

	$ariaLabel = 'previous' === $type ? __( 'Previous post' ) : __( 'Next post' );
@endphp
@if ( '' === $url )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-navigation-link' ] ) !!}></div>
@else
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-navigation-link' ] ) !!}><a href="{{ $url }}" aria-label="{{ $ariaLabel }}">{!! e( $visible ) !!}</a></div>
@endif
