@php
	$text      = $content['text'] ?? '';
	$citation  = $content['citation'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$style     = $styles['style'] ?? 'default';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-quote ve-block-quote--{$style} ve-block-editing text-{$alignment}";
@endphp

<blockquote
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<p
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.block_quote_placeholder' ) }}"
		data-ve-enter-new-block="true"
	>{!! $text !!}</p>
	<cite
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.citation_placeholder' ) }}"
	>{{ $citation }}</cite>
</blockquote>
