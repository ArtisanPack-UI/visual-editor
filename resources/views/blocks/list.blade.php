@php
	$type      = $content['type'] ?? 'unordered';
	$items     = $content['items'] ?? [];
	$start     = $content['start'] ?? '1';
	$reversed  = $content['reversed'] ?? false;
	$textColor = $styles['textColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$anchor    = $content['anchor'] ?? null;
	$className = $content['className'] ?? '';

	$tag = 'ordered' === $type ? 'ol' : 'ul';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}

	$classes = 've-block ve-block-list';
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
	@if ( 'ordered' === $type && '1' !== $start ) start="{{ $start }}" @endif
	@if ( 'ordered' === $type && $reversed ) reversed @endif
>
	@foreach ( $items as $item )
		<li>{!! $item['text'] ?? '' !!}</li>
	@endforeach
</{{ $tag }}>
