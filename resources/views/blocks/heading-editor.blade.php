@php
	$level     = $content['level'] ?? 'h2';
	$text      = $content['text'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;

	$tag = in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] ) ? $level : 'h2';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-heading ve-block-editing text-{$alignment}";
	if ( $fontSize ) {
		$classes .= " text-{$fontSize}";
	}
@endphp

<{{ $tag }}
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	contenteditable="true"
	data-placeholder="{{ __( 'visual-editor::ve.block_heading_placeholder' ) }}"
>{!! $text !!}</{{ $tag }}>
