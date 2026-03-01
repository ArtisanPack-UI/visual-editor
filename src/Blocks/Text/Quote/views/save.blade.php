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

	$elementId = $htmlId ?: $anchor;

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	if ( is_array( $padding ) ) {
		$top    = $padding['top'] ?? '0';
		$right  = $padding['right'] ?? '0';
		$bottom = $padding['bottom'] ?? '0';
		$left   = $padding['left'] ?? '0';
		$inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = $margin['top'] ?? '0';
		$bottom = $margin['bottom'] ?? '0';
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth = ( $border['width'] ?? '0' ) . ( $border['widthUnit'] ?? 'px' );
		$bStyle = $border['style'] ?? 'solid';
		$bColor = $border['color'] ?? 'currentColor';
		$inlineStyles .= " border: {$bWidth} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadiusUnit = $border['radiusUnit'] ?? 'px';
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	if ( $backgroundImage ) {
		$inlineStyles .= " background-image: url('{$backgroundImage}');";
		$inlineStyles .= " background-size: {$backgroundSize};";
		$inlineStyles .= " background-position: {$backgroundPosition};";
	}

	$quoteTextStyles = '';
	if ( $blockSpacing ) {
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
