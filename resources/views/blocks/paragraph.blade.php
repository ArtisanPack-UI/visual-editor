@php
	$text      = $content['text'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$dropCap   = $styles['dropCap'] ?? false;
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
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
