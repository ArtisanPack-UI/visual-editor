@php
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;
	$borderColor  = $styles['borderColor'] ?? null;
	$borderWidth  = $styles['borderWidth'] ?? null;
	$borderRadius = $styles['borderRadius'] ?? null;

	$inlineStyles = '';

	if ( is_array( $padding ) ) {
		$top    = veSanitizeCssDimension( $padding['top'] ?? '0' );
		$right  = veSanitizeCssDimension( $padding['right'] ?? '0' );
		$bottom = veSanitizeCssDimension( $padding['bottom'] ?? '0' );
		$left   = veSanitizeCssDimension( $padding['left'] ?? '0' );
		$inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = veSanitizeCssDimension( $margin['top'] ?? '0' );
		$bottom = veSanitizeCssDimension( $margin['bottom'] ?? '0' );
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	$borderColor = veSanitizeCssColor( $borderColor );
	if ( $borderColor ) {
		$inlineStyles .= " border-color: {$borderColor};";
	}
	$borderWidth = veSanitizeCssDimension( $borderWidth );
	if ( $borderWidth ) {
		$inlineStyles .= " border-width: {$borderWidth}; border-style: solid;";
	}
	$borderRadius = veSanitizeCssDimension( $borderRadius );
	if ( $borderRadius ) {
		$inlineStyles .= " border-radius: {$borderRadius};";
	}

	$classes = 've-block ve-block-comment-template ve-block-editing ve-block-dynamic-preview';
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div style="opacity: 0.7; font-style: italic; padding: 0.5rem; border: 1px dashed currentColor; border-radius: 0.25rem;">
		{{ __( 'visual-editor::ve.comment_template_placeholder' ) }}
	</div>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
