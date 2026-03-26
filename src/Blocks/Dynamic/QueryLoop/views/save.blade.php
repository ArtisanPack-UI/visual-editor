@php
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';
	$inherit   = $content['inherit'] ?? false;

	$elementId = veSanitizeHtmlId( $htmlId );

	$queryResults = veGetQueryResults( $context, $content );
	$results      = $queryResults['items'] ?? [];
	$totalResults = $queryResults['total'] ?? 0;

	$inlineStyles = '';

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

	$classes = 've-block ve-block-query-loop';
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
	data-ve-query-total="{{ $totalResults }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
