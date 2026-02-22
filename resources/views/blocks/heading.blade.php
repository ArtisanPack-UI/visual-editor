@php
	$level     = $content['level'] ?? 'h2';
	$text      = $content['text'] ?? '';
	$alignment = $styles['alignment'] ?? 'left';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$anchor    = $content['anchor'] ?? null;
	$className = $content['className'] ?? '';

	$tag = in_array( $level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] ) ? $level : 'h2';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}

	$classes = "ve-block ve-block-heading text-{$alignment}";
	if ( $fontSize ) {
		$classes .= " text-{$fontSize}";
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<{{ $tag }}
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $anchor ) id="{{ $anchor }}" @endif
>{!! $text !!}</{{ $tag }}>
