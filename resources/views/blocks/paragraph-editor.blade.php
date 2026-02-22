@php
	$text      = $content['text'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$dropCap   = $styles['dropCap'] ?? false;

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-paragraph ve-block-editing text-{$alignment}";
	if ( $fontSize ) {
		$classes .= " text-{$fontSize}";
	}
	if ( $dropCap ) {
		$classes .= ' ve-drop-cap';
	}
@endphp

<p
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	contenteditable="true"
	data-placeholder="{{ __( 'visual-editor::ve.block_paragraph_placeholder' ) }}"
>{!! $text !!}</p>
