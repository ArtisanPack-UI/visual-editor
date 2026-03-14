@php
	$text      = $content['text'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$dropCap   = $styles['dropCap'] ?? false;
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;
	$border    = $styles['border'] ?? [];
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

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

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth     = veSanitizeCssNumber( $border['width'] ?? '0' );
		$bWidthUnit = veSanitizeCssUnit( $border['widthUnit'] ?? 'px' );
		$bStyle     = veSanitizeBorderStyle( $border['style'] ?? 'solid' );
		$bColor     = veSanitizeCssColor( $border['color'] ?? 'currentColor', 'currentColor' );
		$inlineStyles .= " border: {$bWidth}{$bWidthUnit} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadius     = veSanitizeCssNumber( $bRadius );
			$bRadiusUnit = veSanitizeCssUnit( $border['radiusUnit'] ?? 'px' );
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	$classes = "ve-block ve-block-paragraph text-{$alignment}";
	if ( $fontSize ) {
		$classes .= " text-{$fontSize}";
	}
	if ( $dropCap ) {
		$classes .= ' ve-drop-cap';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<p
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>{!! $text !!}</p>
