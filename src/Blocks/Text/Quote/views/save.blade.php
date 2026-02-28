@php
	$text         = $content['text'] ?? '';
	$citation     = $content['citation'] ?? '';
	$showCitation = $content['showCitation'] ?? false;
	$alignment    = $styles['alignment'] ?? 'left';
	$textColor    = $styles['textColor'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$anchor       = $content['anchor'] ?? null;
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-quote text-{$alignment}";
	if ( $className ) {
		$classes .= " {$className}";
	}

	$hasInnerBlocks = ! empty( $innerBlocks ?? [] );
@endphp

<blockquote
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $hasInnerBlocks )
		<div class="ve-quote-text">
			@foreach ( $innerBlocks as $innerBlock )
				{!! $innerBlock !!}
			@endforeach
		</div>
	@else
		<div class="ve-quote-text">{!! $text !!}</div>
	@endif

	@if ( $showCitation && $citation )
		<cite class="ve-quote-citation">{!! $citation !!}</cite>
	@endif
</blockquote>
