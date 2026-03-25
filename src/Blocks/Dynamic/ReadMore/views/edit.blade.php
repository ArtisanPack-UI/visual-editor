@php
	$linkContent = $content['content'] ?? __( 'visual-editor::ve.read_more_default' );
	$showArrow   = $content['showArrow'] ?? false;
	$textColor   = $styles['textColor'] ?? null;
	$bgColor     = $styles['backgroundColor'] ?? null;
	$fontSize    = $styles['fontSize'] ?? null;
	$padding     = $styles['padding'] ?? null;
	$margin      = $styles['margin'] ?? null;

	$inlineStyles = '';
	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= " color: {$textColor};";
	}
	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
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

	$inlineStyles = ltrim( $inlineStyles );

	$safeFontSize = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );

	$classes = 've-block ve-block-read-more read-more-link ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}

	$arrowHtml = $showArrow ? ' <span class="read-more-arrow">' . __( 'visual-editor::ve.read_more_arrow' ) . '</span>' : '';
@endphp

<a
	href="#"
	data-ve-preview-link
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="pointer-events: none; cursor: default; text-decoration: inherit;{{ $inlineStyles }}" @else style="pointer-events: none; cursor: default; text-decoration: inherit;" @endif
>{{ $linkContent }}{!! $arrowHtml !!}</a>
