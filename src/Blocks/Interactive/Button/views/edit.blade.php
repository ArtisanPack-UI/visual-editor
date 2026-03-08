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

	$widthMap = [
		'25'  => '25%',
		'50'  => '50%',
		'75'  => '75%',
		'100' => '100%',
	];

	if ( isset( $widthMap[ $width ] ) ) {
		$inlineStyles .= "width: {$widthMap[ $width ]};";
	}

	$classes = "ve-block ve-block-button ve-block-editing ve-button-{$size} ve-button-{$variant}";
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
