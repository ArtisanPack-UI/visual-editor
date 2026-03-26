@php
	$level      = $content['level'] ?? 'h1';
	$showPrefix = $content['showPrefix'] ?? true;
	$prefixType = $content['prefixType'] ?? 'archive';
	$textColor  = $styles['textColor'] ?? null;
	$bgColor    = $styles['backgroundColor'] ?? null;
	$fontSize   = $styles['fontSize'] ?? null;
	$padding    = $styles['padding'] ?? null;
	$margin     = $styles['margin'] ?? null;

	$safeLevel = in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $level : 'h1';

	$sampleTitle = 'search' === $prefixType
		? __( 'visual-editor::ve.query_title_sample_search' )
		: __( 'visual-editor::ve.query_title_sample_archive' );

	if ( ! $showPrefix ) {
		$sampleTitle = 'search' === $prefixType
			? __( 'visual-editor::ve.query_title_sample_search_no_prefix' )
			: __( 'visual-editor::ve.query_title_sample_archive_no_prefix' );
	}

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

	$classes = 've-block ve-block-query-title ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<{{ $safeLevel }}>{{ $sampleTitle }}</{{ $safeLevel }}>
</div>
