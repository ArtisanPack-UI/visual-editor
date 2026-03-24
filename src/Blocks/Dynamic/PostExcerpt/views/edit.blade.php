@php
	$excerptLength     = max( 5, min( 200, (int) ( $content['excerptLength'] ?? 55 ) ) );
	$moreText          = $content['moreText'] ?? '';
	$showMoreOnNewLine = $content['showMoreOnNewLine'] ?? true;
	$textColor         = $styles['textColor'] ?? null;
	$bgColor           = $styles['backgroundColor'] ?? null;
	$fontSize          = $styles['fontSize'] ?? null;
	$padding           = $styles['padding'] ?? null;
	$margin            = $styles['margin'] ?? null;

	$sampleExcerpt = __( 'visual-editor::ve.post_excerpt_sample' );

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

	$classes = 've-block ve-block-post-excerpt ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}

	$truncated = Illuminate\Support\Str::words( $sampleExcerpt, $excerptLength, '' );
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@if ( $moreText && $showMoreOnNewLine )
		<p style="margin: 0;">{{ $truncated }}</p>
		<p style="margin: 0.5rem 0 0;">
			<a href="#" data-ve-preview-link style="pointer-events: none; cursor: default;">{{ $moreText }}</a>
		</p>
	@elseif ( $moreText )
		<p style="margin: 0;">
			{{ $truncated }} <a href="#" data-ve-preview-link style="pointer-events: none; cursor: default;">{{ $moreText }}</a>
		</p>
	@else
		<p style="margin: 0;">{{ $truncated }}</p>
	@endif
</div>
