@php
	$format    = $content['format'] ?? 'relative';
	$isLink    = $content['isLink'] ?? false;
	$textColor = $styles['textColor'] ?? null;
	$bgColor   = $styles['backgroundColor'] ?? null;
	$fontSize  = $styles['fontSize'] ?? null;
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$commentDate = veGetContentCommentDate( $context );
	$commentsUrl = veGetContentCommentsUrl( $context );

	$displayDate = $commentDate;
	if ( 'relative' === $format && $commentDate ) {
		try {
			$carbon      = \Carbon\Carbon::parse( $commentDate );
			$displayDate = $carbon->diffForHumans();
		} catch ( \Exception $e ) {
			$displayDate = $commentDate;
		}
	}

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

	$classes = 've-block ve-block-comment-date';
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
	@if ( $isLink && $commentsUrl )
		<a href="{{ $commentsUrl }}">
			<time @if ( $commentDate ) datetime="{{ $commentDate }}" @endif>{{ $displayDate }}</time>
		</a>
	@else
		<time @if ( $commentDate ) datetime="{{ $commentDate }}" @endif>{{ $displayDate }}</time>
	@endif
</div>
