@php
	$text         = $content['text'] ?? '';
	$size         = $styles['size'] ?? 'md';
	$variant      = $styles['variant'] ?? 'filled';
	$color        = $styles['color'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$borderRadius = $styles['borderRadius'] ?? '';
	$width        = $styles['width'] ?? 'auto';

	$sizeMap = [
		'sm' => 'padding: 0.375rem 0.75rem; font-size: 0.875rem;',
		'md' => 'padding: 0.5rem 1rem; font-size: 1rem;',
		'lg' => 'padding: 0.625rem 1.25rem; font-size: 1.125rem;',
		'xl' => 'padding: 0.75rem 1.5rem; font-size: 1.25rem;',
	];

	$inlineStyles = $sizeMap[ $size ] ?? $sizeMap['md'];
	$inlineStyles .= ' display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; cursor: text;';

	if ( 'filled' === $variant ) {
		if ( $bgColor ) {
			$inlineStyles .= " background-color: {$bgColor};";
		}
		if ( $color ) {
			$inlineStyles .= " color: {$color};";
		}
		$inlineStyles .= ' border: none;';
	} elseif ( 'outline' === $variant ) {
		$inlineStyles .= ' background-color: transparent;';
		$borderColor   = $bgColor ?? 'currentColor';
		$inlineStyles .= " border: 2px solid {$borderColor};";
		if ( $color ) {
			$inlineStyles .= " color: {$color};";
		}
	} else {
		$inlineStyles .= ' background-color: transparent; border: none;';
		if ( $color ) {
			$inlineStyles .= " color: {$color};";
		}
	}

	if ( $borderRadius ) {
		$inlineStyles .= " border-radius: {$borderRadius};";
	}

	if ( 'full' === $width ) {
		$inlineStyles .= ' width: 100%; justify-content: center;';
	}
@endphp

<div
	class="ve-block ve-block-button ve-block-editing ve-button-{{ $variant }} ve-button-{{ $size }}"
	style="{{ $inlineStyles }}"
	contenteditable="true"
	data-placeholder="{{ __( 'visual-editor::ve.block_button_placeholder' ) }}"
>{{ $text }}</div>
