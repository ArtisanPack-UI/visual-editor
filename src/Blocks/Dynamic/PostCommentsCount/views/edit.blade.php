@php
	$format         = $content['format'] ?? 'short';
	$singular       = $content['singular'] ?? '';
	$plural         = $content['plural'] ?? '';
	$showIcon       = $content['showIcon'] ?? false;
	$linkToComments = $content['linkToComments'] ?? false;
	$textColor      = $styles['textColor'] ?? null;
	$bgColor        = $styles['backgroundColor'] ?? null;
	$fontSize       = $styles['fontSize'] ?? null;
	$padding        = $styles['padding'] ?? null;
	$margin         = $styles['margin'] ?? null;

	$sampleCount    = 5;
	$singularLabel  = $singular ?: __( 'visual-editor::ve.post_comments_count_default_singular' );
	$pluralLabel    = $plural ?: __( 'visual-editor::ve.post_comments_count_default_plural' );

	if ( 'number' === $format ) {
		$displayText = (string) $sampleCount;
	} elseif ( 'long' === $format ) {
		$displayText = __( 'visual-editor::ve.post_comments_count_long_format', [ 'count' => $sampleCount, 'label' => $pluralLabel ] );
	} else {
		$displayText = $sampleCount . ' ' . $pluralLabel;
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

	$safeFontSize = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );

	$classes = 've-block ve-block-post-comments-count ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@if ( $showIcon )
		<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1em; height: 1em; display: inline-block; vertical-align: middle; margin-right: 0.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" /></svg>
	@endif
	@if ( $linkToComments )
		<a href="#" data-ve-preview-link style="pointer-events: none; cursor: default; color: inherit; text-decoration: inherit;">{{ $displayText }}</a>
	@else
		{{ $displayText }}
	@endif
</div>
