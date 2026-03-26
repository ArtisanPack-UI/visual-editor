@php
	$layout    = $content['layout'] ?? 'list';
	$columns   = $content['columns'] ?? 3;
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$safeLayout  = in_array( $layout, [ 'list', 'grid' ], true ) ? $layout : 'list';
	$safeColumns = max( 1, min( 6, (int) $columns ) );

	$inlineStyles = '';

	if ( 'grid' === $safeLayout ) {
		$inlineStyles .= " display: grid; grid-template-columns: repeat({$safeColumns}, 1fr); gap: 1.5rem;";
	}

	if ( is_array( $padding ) ) {
		$top    = veSanitizeCssDimension( $padding['top'] ?? '0' );
		$right  = veSanitizeCssDimension( $padding['right'] ?? '0' );
		$bottom = veSanitizeCssDimension( $padding['bottom'] ?? '0' );
		$left   = veSanitizeCssDimension( $padding['left'] ?? '0' );
		$inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = veSanitizeCssDimension( $margin['top'] ?? '0' );
		$bottom = veSanitizeCssDimension( $margin['bottom'] ?? '0' );
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-post-template ve-block-post-template--' . $safeLayout;
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	{{-- TODO: BlockRenderer needs to support re-rendering inner blocks with
		 per-item context so each iteration shows the correct post data. --}}
	@foreach ( $innerBlocks as $innerBlock )
		<div class="ve-post-template-item">
			{!! $innerBlock !!}
		</div>
	@endforeach
</div>
