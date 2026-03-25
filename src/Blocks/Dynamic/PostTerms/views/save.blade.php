@php
	$term      = $content['term'] ?? 'category';
	$separator = $content['separator'] ?? ', ';
	$prefix    = $content['prefix'] ?? '';
	$suffix    = $content['suffix'] ?? '';
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$terms = veGetContentTerms( $term, $context );

	$inlineStyles = '';
	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}
	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
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

	$safeFontSize  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );
	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-post-terms';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

@if ( ! empty( $terms ) )
	<div
		class="{{ $classes }}"
		@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
		@if ( $elementId ) id="{{ $elementId }}" @endif
	>
		@if ( $prefix )
			<span>{{ $prefix }}</span>
		@endif
		@foreach ( $terms as $index => $termItem )
			@if ( $termItem['url'] )
				<a href="{{ $termItem['url'] }}">{{ $termItem['name'] }}</a>
			@else
				<span>{{ $termItem['name'] }}</span>
			@endif
			@if ( $index < count( $terms ) - 1 )
				{{ $separator }}
			@endif
		@endforeach
		@if ( $suffix )
			<span>{{ $suffix }}</span>
		@endif
	</div>
@endif
