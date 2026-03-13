@php
	$preContent      = $content['content'] ?? '';
	$fontFamily      = $styles['fontFamily'] ?? 'monospace';
	$fontSize        = $styles['fontSize'] ?? null;
	$textColor       = $styles['textColor'] ?? null;
	$bgColor         = $styles['backgroundColor'] ?? null;
	$showLineNumbers = $styles['showLineNumbers'] ?? false;
	$padding         = $styles['padding'] ?? null;
	$margin          = $styles['margin'] ?? null;
	$border          = $styles['border'] ?? [];
	$anchor          = $content['anchor'] ?? null;
	$htmlId          = $content['htmlId'] ?? null;
	$className       = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$allowedFonts = [ 'monospace', 'ui-monospace', 'Courier New', 'Consolas', 'Menlo', 'Source Code Pro' ];
	$fontFamily   = in_array( $fontFamily, $allowedFonts, true ) ? $fontFamily : 'monospace';

	$classes = 've-block ve-block-preformatted';
	if ( $showLineNumbers ) {
		$classes .= ' ve-pre-line-numbers';
	}
	if ( $className ) {
		$classes .= ' ' . preg_replace( '/[^a-zA-Z0-9\s\-_]/', '', $className );
	}

	$inlineStyles = "font-family: {$fontFamily}, monospace;";

	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= " color: {$textColor};";
	}

	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	if ( $fontSize ) {
		$fontSize = veSanitizeCssDimension( $fontSize );
		$inlineStyles .= " font-size: {$fontSize};";
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

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth     = veSanitizeCssDimension( $border['width'] ?? '0' );
		$bWidthUnit = veSanitizeCssUnit( $border['widthUnit'] ?? 'px' );
		$bStyle     = veSanitizeBorderStyle( $border['style'] ?? 'solid' );
		$bColor     = veSanitizeCssColor( $border['color'] ?? 'currentColor', 'currentColor' );
		$inlineStyles .= " border: {$bWidth}{$bWidthUnit} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadius     = veSanitizeCssDimension( $bRadius );
			$bRadiusUnit = veSanitizeCssUnit( $border['radiusUnit'] ?? 'px' );
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<pre style="{{ $inlineStyles }}">{{ $preContent }}</pre>
</div>
