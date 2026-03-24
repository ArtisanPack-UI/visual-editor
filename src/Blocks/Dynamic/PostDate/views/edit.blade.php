@php
	$format      = $content['format'] ?? '';
	$displayType = $content['displayType'] ?? 'date';
	$isLink      = $content['isLink'] ?? false;
	$textColor   = $styles['textColor'] ?? null;
	$bgColor     = $styles['backgroundColor'] ?? null;
	$fontSize    = $styles['fontSize'] ?? null;
	$padding     = $styles['padding'] ?? null;
	$margin      = $styles['margin'] ?? null;

	$dateFormat   = $format ?: 'M j, Y';
	$sampleDate   = now()->subDays( 3 )->format( $dateFormat );
	$sampleModified = now()->subHours( 6 )->format( $dateFormat );

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

	$classes = 've-block ve-block-post-date ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@if ( 'date' === $displayType || 'both' === $displayType )
		@if ( $isLink )
			<a href="#" data-ve-preview-link style="pointer-events: none; cursor: default; color: inherit; text-decoration: inherit;"><time>{{ $sampleDate }}</time></a>
		@else
			<time>{{ $sampleDate }}</time>
		@endif
	@endif
	@if ( 'both' === $displayType )
		<span style="margin: 0 0.25rem;">&middot;</span>
	@endif
	@if ( 'modified' === $displayType || 'both' === $displayType )
		@if ( 'both' === $displayType )
			<span class="ve-post-date-modified">{{ __( 'visual-editor::ve.post_date_modified_label' ) }} <time>{{ $sampleModified }}</time></span>
		@else
			@if ( $isLink )
				<a href="#" data-ve-preview-link style="pointer-events: none; cursor: default; color: inherit; text-decoration: inherit;"><time>{{ $sampleModified }}</time></a>
			@else
				<time>{{ $sampleModified }}</time>
			@endif
		@endif
	@endif
</div>
