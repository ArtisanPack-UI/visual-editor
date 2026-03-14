@php
	$summary         = $content['summary'] ?? '';
	$isOpen          = $content['isOpenByDefault'] ?? false;
	$allowedIcons         = [ 'chevron', 'plus-minus', 'none' ];
	$allowedIconPositions = [ 'left', 'right' ];
	$allowedBorderStyles  = [ 'default', 'card', 'minimal', 'borderless' ];
	$iconCandidate        = $styles['icon'] ?? 'chevron';
	$iconPosCandidate     = $styles['iconPosition'] ?? 'left';
	$borderStyleCandidate = $styles['borderStyle'] ?? 'default';
	$icon            = in_array( $iconCandidate, $allowedIcons, true ) ? $iconCandidate : 'chevron';
	$iconPosition    = in_array( $iconPosCandidate, $allowedIconPositions, true ) ? $iconPosCandidate : 'left';
	$borderStyle     = in_array( $borderStyleCandidate, $allowedBorderStyles, true ) ? $borderStyleCandidate : 'default';
	$summaryBgColor  = $styles['summaryBackgroundColor'] ?? null;
	$contentBgColor  = $styles['contentBackgroundColor'] ?? null;
	$textColor       = $styles['textColor'] ?? null;
	$bgColor         = $styles['backgroundColor'] ?? null;
	$fontSize        = $styles['fontSize'] ?? null;
	$padding         = $styles['padding'] ?? null;
	$margin          = $styles['margin'] ?? null;
	$border          = $styles['border'] ?? [];
	$anchor          = $content['anchor'] ?? null;
	$htmlId          = $content['htmlId'] ?? null;
	$className       = $content['className'] ?? '';
	$innerBlocks     = $innerBlocks ?? [];

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-details';
	$classes .= " ve-details-style-{$borderStyle}";
	$classes .= " ve-details-icon-{$icon}";
	$classes .= " ve-details-icon-{$iconPosition}";
	if ( $className ) {
		$classes .= ' ' . preg_replace( '/[^a-zA-Z0-9\s\-_]/', '', $className );
	}

	$inlineStyles = '';

	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
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

	$summaryStyles = '';
	$summaryBgColor = veSanitizeCssColor( $summaryBgColor );
	if ( $summaryBgColor ) {
		$summaryStyles = "background-color: {$summaryBgColor};";
	}

	$contentStyles = '';
	$contentBgColor = veSanitizeCssColor( $contentBgColor );
	if ( $contentBgColor ) {
		$contentStyles = "background-color: {$contentBgColor};";
	}
@endphp

<details
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $isOpen ) open @endif
>
	<summary @if ( $summaryStyles ) style="{{ $summaryStyles }}" @endif>{!! kses( $summary ) !!}</summary>
	<div
		class="ve-details-content"
		@if ( $contentStyles ) style="{{ $contentStyles }}" @endif
	>
		@foreach ( $innerBlocks as $innerBlock )
			{!! $innerBlock !!}
		@endforeach
	</div>
</details>
