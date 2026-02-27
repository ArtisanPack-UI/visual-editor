@php
	$type      = $content['type'] ?? 'unordered';
	$start     = $content['start'] ?? '1';
	$reversed  = $content['reversed'] ?? false;
	$textColor = $styles['textColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;

	$tag = 'ordered' === $type ? 'ol' : 'ul';

	$inlineStyles = '';
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}

	$listStyleClass = 'ordered' === $type ? 'list-decimal' : 'list-disc';
	$classes        = "ve-block ve-block-list ve-block-editing ve-list-{$type} {$listStyleClass} pl-6";
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
>
	<li data-placeholder="{{ __( 'visual-editor::ve.list_placeholder' ) }}"></li>
</{{ $tag }}>
