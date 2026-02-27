@php
	$type      = $content['type'] ?? 'unordered';
	$start     = $content['start'] ?? '1';
	$reversed  = $content['reversed'] ?? false;
	$innerHTML = $content['innerHTML'] ?? '<li></li>';
	$textColor = $styles['textColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$tag = 'ordered' === $type ? 'ol' : 'ul';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}

	$listStyleClass = 'ordered' === $type ? 'list-decimal' : 'list-disc';
	$classes        = "ve-block ve-block-list ve-list-{$type} {$listStyleClass} pl-6";
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
	@if ( $elementId ) id="{{ $elementId }}" @endif
	@if ( 'ordered' === $type && '1' !== $start ) start="{{ $start }}" @endif
	@if ( 'ordered' === $type && $reversed ) reversed @endif
>
	{!! $innerHTML !!}
</{{ $tag }}>
