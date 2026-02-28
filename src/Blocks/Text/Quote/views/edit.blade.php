@php
	$text         = $content['text'] ?? '';
	$citation     = $content['citation'] ?? '';
	$showCitation = $content['showCitation'] ?? false;
	$alignment    = $styles['alignment'] ?? 'left';
	$textColor    = $styles['textColor'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$hasInner     = ! empty( $innerBlocks ?? [] );

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-quote ve-block-editing text-{$alignment}";
@endphp

<blockquote
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<x-ve-inner-blocks
		:inner-blocks="$innerBlocks"
		:parent-id="$context['blockId'] ?? null"
		:placeholder="__( 'visual-editor::ve.block_quote_placeholder' )"
		:editing="true"
	/>

	@if ( $showCitation )
		<cite
			class="ve-quote-citation block mt-2 text-sm not-italic text-base-content/70"
			contenteditable="true"
			data-placeholder="{{ __( 'visual-editor::ve.citation_placeholder' ) }}"
		>{!! $citation !!}</cite>
	@endif
</blockquote>
