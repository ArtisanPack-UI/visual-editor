@php
	$type      = $content['type'] ?? 'unordered';
	$items     = $content['items'] ?? [];
	$start     = $content['start'] ?? '1';
	$reversed  = $content['reversed'] ?? false;
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

	$classes = "ve-block ve-block-list ve-list-{$type}";
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
	@foreach ( $items as $item )
		<li>{!! $item['text'] ?? '' !!}</li>
	@endforeach
</{{ $tag }}>
