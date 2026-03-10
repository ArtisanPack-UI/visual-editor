@php
	$text               = $content['text'] ?? '';
	$citation           = $content['citation'] ?? '';
	$showCitation       = $content['showCitation'] ?? false;
	$alignment          = $styles['alignment'] ?? 'left';
	$textColor          = $styles['textColor'] ?? null;
	$bgColor            = $styles['backgroundColor'] ?? null;
	$fontSize           = $styles['fontSize'] ?? null;
	$padding            = $styles['padding'] ?? null;
	$margin             = $styles['margin'] ?? null;
	$blockSpacing       = $styles['blockSpacing'] ?? null;
	$border             = $styles['border'] ?? [];
	$backgroundImage    = $styles['backgroundImage'] ?? null;
	$backgroundSize     = $styles['backgroundSize'] ?? 'cover';
	$backgroundPosition = $styles['backgroundPosition'] ?? 'center center';
	$anchor             = $content['anchor'] ?? null;
	$htmlId             = $content['htmlId'] ?? null;
	$className          = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$inlineStyles = '';
	if ( $textColor ) {
		$textColor = veSanitizeCssColor( $textColor );
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$bgColor = veSanitizeCssColor( $bgColor );
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

	if ( $backgroundImage ) {
		$inlineStyles .= " background-image: url('{$backgroundImage}');";
		$allowedBgSizes = [ 'cover', 'contain', 'auto', 'inherit', 'initial', 'unset' ];
		$backgroundSize = in_array( $backgroundSize, $allowedBgSizes, true ) ? $backgroundSize : veSanitizeCssDimension( $backgroundSize, 'cover' );
		$inlineStyles .= " background-size: {$backgroundSize};";
		$backgroundPosition = preg_match( '/^[a-zA-Z0-9\s%]+$/', $backgroundPosition ) ? $backgroundPosition : 'center center';
		$inlineStyles .= " background-position: {$backgroundPosition};";
	}

	$quoteTextStyles = '';
	if ( $blockSpacing ) {
		$blockSpacing    = veSanitizeCssDimension( $blockSpacing );
		$quoteTextStyles = "display: flex; flex-direction: column; gap: {$blockSpacing};";
	}

	$classes = "ve-block ve-block-quote text-{$alignment}";
	if ( $fontSize ) {
		$classes .= " text-{$fontSize}";
	}
	if ( $className ) {
		$classes .= " {$className}";
	}

	$hasInnerBlocks = ! empty( $innerBlocks ?? [] );
@endphp

<blockquote
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $hasInnerBlocks )
		<div class="ve-quote-text" @if ( $quoteTextStyles ) style="{{ $quoteTextStyles }}" @endif>
			@foreach ( $innerBlocks as $innerBlock )
				{!! $innerBlock !!}
			@endforeach
		</div>
	@else
		<div class="ve-quote-text" @if ( $quoteTextStyles ) style="{{ $quoteTextStyles }}" @endif>{!! $text !!}</div>
	@endif

	@if ( $showCitation && $citation )
		<cite class="ve-quote-citation">{!! $citation !!}</cite>
	@endif
</blockquote>
