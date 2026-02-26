@php
	$style     = $styles['style'] ?? 'solid';
	$width     = $styles['width'] ?? 'full';
	$color     = $styles['color'] ?? null;
	$thickness = $styles['thickness'] ?? '1px';
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$widthMap = [
		'full'   => '100%',
		'wide'   => '75%',
		'narrow' => '50%',
	];
	$widthValue = $widthMap[ $width ] ?? '100%';

	$inlineStyles = "border-top-style: {$style}; border-top-width: {$thickness}; width: {$widthValue};";
	if ( $color ) {
		$inlineStyles .= " border-top-color: {$color};";
	}

	$classes = "ve-block ve-block-divider ve-divider-{$width}";
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<hr
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
/>
