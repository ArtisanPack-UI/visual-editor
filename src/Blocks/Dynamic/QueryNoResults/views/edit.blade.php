@php
	$padding = $styles['padding'] ?? null;
	$margin  = $styles['margin'] ?? null;

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

	$classes = 've-block ve-block-query-no-results ve-block-editing ve-block-dynamic-preview';
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div style="opacity: 0.7; font-style: italic; padding: 0.5rem; border: 1px dashed currentColor; border-radius: 0.25rem;">
		{{ __( 'visual-editor::ve.query_no_results_placeholder' ) }}
	</div>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
