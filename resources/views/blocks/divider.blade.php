@php
	$style     = $styles['style'] ?? 'solid';
	$width     = $styles['width'] ?? 'full';
	$color     = $styles['color'] ?? null;
	$thickness = $styles['thickness'] ?? '1px';

	$widthMap = [
		'full'   => '100%',
		'wide'   => '75%',
		'narrow' => '50%',
	];

	$widthValue = $widthMap[ $width ] ?? '100%';

	$inlineStyles = "border: none; border-top-style: {$style}; border-top-width: {$thickness}; width: {$widthValue};";

	if ( $color ) {
		$inlineStyles .= " border-top-color: {$color};";
	}

	$classes = "ve-block ve-block-divider";
	if ( 'full' !== $width ) {
		$classes .= " ve-divider-{$width}";
	}
@endphp

<hr
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
/>
