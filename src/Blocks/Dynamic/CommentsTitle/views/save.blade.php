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
	$htmlId        = $content['htmlId'] ?? null;
	$className     = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$count    = veGetContentCommentsCount( $context );
	$singular = $singularLabel ?: __( 'visual-editor::ve.comments_title_default_singular' );
	$plural   = $pluralLabel ?: __( 'visual-editor::ve.comments_title_default_plural' );
	$label    = 1 === $count ? $singular : $plural;

	if ( $showCount ) {
		$displayText = $count . ' ' . $label;
	} else {
		$displayText = $label;
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

	$safeFontSize  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );
	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-comments-title';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<{{ $safeLevel }}>{{ $displayText }}</{{ $safeLevel }}>
</div>
