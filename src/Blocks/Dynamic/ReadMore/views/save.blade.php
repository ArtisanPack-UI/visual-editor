@php
	$linkContent = $content['content'] ?? __( 'visual-editor::ve.read_more_default' );
	$linkTarget  = $content['linkTarget'] ?? '_self';
	$showArrow   = $content['showArrow'] ?? false;
	$textColor   = $styles['textColor'] ?? null;
	$bgColor     = $styles['backgroundColor'] ?? null;
	$fontSize    = $styles['fontSize'] ?? null;
	$padding     = $styles['padding'] ?? null;
	$margin      = $styles['margin'] ?? null;
	$htmlId      = $content['htmlId'] ?? null;
	$className   = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );
	$permalink = veGetContentPermalink( $context );

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

	$isBlankTarget = '_blank' === $linkTarget;
	$targetAttr    = $isBlankTarget ? ' target="_blank"' : '';
	$relAttr       = $isBlankTarget ? ' rel="noopener noreferrer"' : '';

	$classes = 've-block ve-block-read-more read-more-link';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}

	$arrowHtml = $showArrow ? ' <span class="read-more-arrow">' . __( 'visual-editor::ve.read_more_arrow' ) . '</span>' : '';
@endphp

@if ( $permalink )
	<a
		href="{{ $permalink }}"
		class="{{ $classes }}"
		@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
		@if ( $elementId ) id="{{ $elementId }}" @endif
		{!! $targetAttr !!}{!! $relAttr !!}
	>{{ $linkContent }}{!! $arrowHtml !!}</a>
@endif
