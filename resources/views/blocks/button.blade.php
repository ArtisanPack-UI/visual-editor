@php
	$text         = $content['text'] ?? '';
	$url          = $content['url'] ?? '#';
	$linkTarget   = $content['linkTarget'] ?? '_self';
	$icon         = $content['icon'] ?? '';
	$iconPosition = $content['iconPosition'] ?? 'left';
	$color        = $styles['color'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$size         = $styles['size'] ?? 'md';
	$variant      = $styles['variant'] ?? 'filled';
	$borderRadius = $styles['borderRadius'] ?? '';
	$width        = $styles['width'] ?? 'auto';

	$sizeMap = [
		'sm' => 'padding: 0.375rem 0.75rem; font-size: 0.875rem;',
		'md' => 'padding: 0.5rem 1rem; font-size: 1rem;',
		'lg' => 'padding: 0.625rem 1.25rem; font-size: 1.125rem;',
		'xl' => 'padding: 0.75rem 1.5rem; font-size: 1.25rem;',
	];

	$inlineStyles = $sizeMap[ $size ] ?? $sizeMap['md'];
	$inlineStyles .= ' display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; cursor: pointer;';

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

	$classes = "ve-block ve-block-button ve-button-{$variant} ve-button-{$size}";
@endphp

<a
	href="{{ $url }}"
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	@if ( '_blank' === $linkTarget ) target="_blank" rel="noopener noreferrer" @endif
>
	@if ( $icon && 'left' === $iconPosition )
		<span class="ve-button-icon">{{ $icon }}</span>
	@endif
	<span class="ve-button-text">{{ $text }}</span>
	@if ( $icon && 'right' === $iconPosition )
		<span class="ve-button-icon">{{ $icon }}</span>
	@endif
</a>
