@php
	$level         = $content['level'] ?? 'h2';
	$showCount     = $content['showCount'] ?? true;
	$singularLabel = $content['singularLabel'] ?? '';
	$pluralLabel   = $content['pluralLabel'] ?? '';
	$textColor     = $styles['textColor'] ?? null;
	$bgColor       = $styles['backgroundColor'] ?? null;
	$fontSize      = $styles['fontSize'] ?? null;
	$padding       = $styles['padding'] ?? null;
	$margin        = $styles['margin'] ?? null;

	$sampleCount = 5;
	$singular    = $singularLabel ?: __( 'visual-editor::ve.comments_title_default_singular' );
	$plural      = $pluralLabel ?: __( 'visual-editor::ve.comments_title_default_plural' );

	if ( $showCount ) {
		$displayText = $sampleCount . ' ' . $plural;
	} else {
		$displayText = $plural;
	}

	$safeLevel = in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $level : 'h2';

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

	$classes = 've-block ve-block-comments-title ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<{{ $safeLevel }}>{{ $displayText }}</{{ $safeLevel }}>
</div>
