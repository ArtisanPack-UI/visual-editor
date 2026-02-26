@php
	$type      = $content['type'] ?? 'unordered';
	$items     = $content['items'] ?? [];
	$start     = $content['start'] ?? '1';
	$reversed  = $content['reversed'] ?? false;
	$textColor = $styles['textColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;

	$tag = 'ordered' === $type ? 'ol' : 'ul';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}

	$classes = "ve-block ve-block-list ve-block-editing ve-list-{$type}";
	if ( $fontSize ) {
		$classes .= " text-{$fontSize}";
	}
@endphp

<{{ $tag }}
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( 'ordered' === $type && '1' !== $start ) start="{{ $start }}" @endif
	@if ( 'ordered' === $type && $reversed ) reversed @endif
	contenteditable="true"
	data-placeholder="{{ __( 'visual-editor::ve.list_item_text' ) }}"
>
	@foreach ( $items as $item )
		<li>{!! $item['text'] ?? '' !!}</li>
	@endforeach
	@if ( empty( $items ) )
		<li></li>
	@endif
</{{ $tag }}>
