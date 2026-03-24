@php
	$layout  = $content['layout'] ?? 'default';
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

	$layoutClass = '';
	if ( 'wide' === $layout ) {
		$layoutClass = ' ve-layout-wide';
	} elseif ( 'full' === $layout ) {
		$layoutClass = ' ve-layout-full';
	}

	$classes = 've-block ve-block-post-content ve-block-editing ve-block-dynamic-preview' . $layoutClass;
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div style="padding: 2rem; border: 1px dashed #d1d5db; border-radius: 4px; text-align: center; color: #9ca3af;">
		<p style="margin: 0; font-size: 0.9em;">{{ __( 'visual-editor::ve.post_content_placeholder' ) }}</p>
	</div>
</div>
