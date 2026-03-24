@php
	$level      = $content['level'] ?? 'h1';
	$isLink     = $content['isLink'] ?? true;
	$linkTarget = $content['linkTarget'] ?? '_self';
	$textColor  = $styles['textColor'] ?? null;
	$bgColor    = $styles['backgroundColor'] ?? null;
	$fontSize   = $styles['fontSize'] ?? null;
	$padding    = $styles['padding'] ?? null;
	$margin     = $styles['margin'] ?? null;
	$htmlId     = $content['htmlId'] ?? null;
	$className  = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$siteTitle = veGetSiteTitle();
	$homeUrl   = veGetSiteHomeUrl();

	$allowedTags = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span' ];
	$tag         = in_array( $level, $allowedTags, true ) ? $level : 'h1';

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

	$classes = 've-block ve-block-site-title';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<{{ $tag }}
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $isLink )
		<a href="{{ $homeUrl }}"@if ( '_blank' === $linkTarget ) target="_blank" rel="noopener noreferrer"@endif>{{ $siteTitle }}</a>
	@else
		{{ $siteTitle }}
	@endif
</{{ $tag }}>
