@php
	$text      = $content['text'] ?? '';
	$citation  = $content['citation'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$style     = $styles['style'] ?? 'default';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$anchor    = $content['anchor'] ?? null;
	$className = $content['className'] ?? '';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-quote ve-block-quote--{$style} text-{$alignment}";
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<blockquote
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $anchor ) id="{{ $anchor }}" @endif
>
	<p>{!! $text !!}</p>
	@if ( $citation )
		<cite>{{ $citation }}</cite>
	@endif
</blockquote>
