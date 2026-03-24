@php
	$excerptLength     = max( 5, min( 200, (int) ( $content['excerptLength'] ?? 55 ) ) );
	$moreText          = $content['moreText'] ?? '';
	$showMoreOnNewLine = $content['showMoreOnNewLine'] ?? true;
	$textColor         = $styles['textColor'] ?? null;
	$bgColor           = $styles['backgroundColor'] ?? null;
	$fontSize          = $styles['fontSize'] ?? null;
	$padding           = $styles['padding'] ?? null;
	$margin            = $styles['margin'] ?? null;
	$htmlId            = $content['htmlId'] ?? null;
	$className         = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$excerpt   = veGetContentExcerpt( $context );
	$permalink = veGetContentPermalink( $context );

	$suffix    = $moreText ? '' : '…';
	$truncated = Illuminate\Support\Str::words( $excerpt, $excerptLength, $suffix );

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

	$classes = 've-block ve-block-post-excerpt';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $moreText && $permalink && $showMoreOnNewLine )
		<p>{{ $truncated }}</p>
		<p><a href="{{ $permalink }}">{{ $moreText }}</a></p>
	@elseif ( $moreText && $permalink )
		<p>{{ $truncated }} <a href="{{ $permalink }}">{{ $moreText }}</a></p>
	@else
		<p>{{ $truncated }}</p>
	@endif
</div>
