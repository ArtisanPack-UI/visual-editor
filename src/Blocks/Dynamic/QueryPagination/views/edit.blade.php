@php
	$showNumbers   = $content['showNumbers'] ?? true;
	$showPrevNext  = $content['showPrevNext'] ?? true;
	$previousLabel = $content['previousLabel'] ?? '';
	$nextLabel     = $content['nextLabel'] ?? '';
	$textColor     = $styles['textColor'] ?? null;
	$bgColor       = $styles['backgroundColor'] ?? null;
	$fontSize      = $styles['fontSize'] ?? null;
	$padding       = $styles['padding'] ?? null;
	$margin        = $styles['margin'] ?? null;

	$prevText = $previousLabel ?: __( 'visual-editor::ve.query_pagination_default_previous' );
	$nextText = $nextLabel ?: __( 'visual-editor::ve.query_pagination_default_next' );

	$inlineStyles = '';
	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

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

	$safeFontSize = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );

	$classes = 've-block ve-block-query-pagination ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<nav style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
		@if ( $showPrevNext )
			<a href="#" data-ve-preview-link tabindex="-1" aria-disabled="true" style="pointer-events: none; cursor: default; color: inherit; text-decoration: inherit;">{{ $prevText }}</a>
		@endif
		@if ( $showNumbers )
			<span style="opacity: 0.5;">1</span>
			<span style="font-weight: bold;">2</span>
			<span style="opacity: 0.5;">3</span>
		@endif
		@if ( $showPrevNext )
			<a href="#" data-ve-preview-link tabindex="-1" aria-disabled="true" style="pointer-events: none; cursor: default; color: inherit; text-decoration: inherit;">{{ $nextText }}</a>
		@endif
	</nav>
</div>
