@php
	$text         = $content['text'] ?? '';
	$icon         = $content['icon'] ?? '';
	$iconPosition = $content['iconPosition'] ?? 'left';
	$color        = $styles['color'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$size         = $styles['size'] ?? 'md';
	$variant      = $styles['variant'] ?? 'filled';
	$borderRadius = $styles['borderRadius'] ?? '';
	$width        = $styles['width'] ?? 'auto';

	$inlineStyles = '';
	if ( $color ) {
		$inlineStyles .= "color: {$color};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}
	if ( $borderRadius ) {
		$inlineStyles .= "border-radius: {$borderRadius};";
	}

	$classes = "ve-block ve-block-button ve-block-editing ve-button-{$size} ve-button-{$variant}";
	if ( 'full' === $width ) {
		$classes .= ' ve-button-full';
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@if ( $icon && 'left' === $iconPosition )
		<span class="ve-button-icon ve-button-icon-left">{{ $icon }}</span>
	@endif
	<span
		class="ve-button-text"
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.button_text' ) }}"
	>{{ $text }}</span>
	@if ( $icon && 'right' === $iconPosition )
		<span class="ve-button-icon ve-button-icon-right">{{ $icon }}</span>
	@endif
</div>
