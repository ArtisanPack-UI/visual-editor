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

	$sampleCategory = __( 'visual-editor::ve.post_terms_sample_category' );
	$sampleTag      = __( 'visual-editor::ve.post_terms_sample_tag' );

	$sampleTerms = 'tag' === $term
		? [ $sampleTag, $sampleTag . ' Two', $sampleTag . ' Three' ]
		: [ $sampleCategory, $sampleCategory . ' Two', $sampleCategory . ' Three' ];

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

	$safeFontSize = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );

	$classes = 've-block ve-block-post-terms ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@if ( $prefix )
		<span>{{ $prefix }}</span>
	@endif
	@foreach ( $sampleTerms as $index => $sampleTerm )
		<a href="#" data-ve-preview-link style="pointer-events: none; cursor: default; color: inherit; text-decoration: underline;">{{ $sampleTerm }}</a>@if ( $index < count( $sampleTerms ) - 1 ){{ $separator }}@endif
	@endforeach
	@if ( $suffix )
		<span>{{ $suffix }}</span>
	@endif
</div>
