@php
	$format      = $content['format'] ?? '';
	$displayType = $content['displayType'] ?? 'date';
	$isLink      = $content['isLink'] ?? false;
	$textColor   = $styles['textColor'] ?? null;
	$bgColor     = $styles['backgroundColor'] ?? null;
	$fontSize    = $styles['fontSize'] ?? null;
	$padding     = $styles['padding'] ?? null;
	$margin      = $styles['margin'] ?? null;
	$htmlId      = $content['htmlId'] ?? null;
	$className   = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$dateFormat = $format ?: 'M j, Y';

	$rawDate     = veGetContentDate( $context );
	$rawModified = veGetContentModifiedDate( $context );
	$permalink   = veGetContentPermalink( $context );

	$dateTs            = $rawDate ? strtotime( $rawDate ) : false;
	$modifiedTs        = $rawModified ? strtotime( $rawModified ) : false;
	$formattedDate     = ( false !== $dateTs ) ? date( $dateFormat, $dateTs ) : '';
	$formattedModified = ( false !== $modifiedTs ) ? date( $dateFormat, $modifiedTs ) : '';

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

	$classes = 've-block ve-block-post-date';
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
	@if ( ( 'date' === $displayType || 'both' === $displayType ) && $formattedDate )
		@if ( $isLink && $permalink )
			<a href="{{ $permalink }}"><time datetime="{{ $rawDate }}">{{ $formattedDate }}</time></a>
		@else
			<time datetime="{{ $rawDate }}">{{ $formattedDate }}</time>
		@endif
	@endif
	@if ( 'both' === $displayType && $formattedDate && $formattedModified )
		<span>&middot;</span>
	@endif
	@if ( ( 'modified' === $displayType || 'both' === $displayType ) && $formattedModified )
		@if ( 'both' === $displayType )
			<span>{{ __( 'visual-editor::ve.post_date_modified_label' ) }} <time datetime="{{ $rawModified }}">{{ $formattedModified }}</time></span>
		@else
			@if ( $isLink && $permalink )
				<a href="{{ $permalink }}"><time datetime="{{ $rawModified }}">{{ $formattedModified }}</time></a>
			@else
				<time datetime="{{ $rawModified }}">{{ $formattedModified }}</time>
			@endif
		@endif
	@endif
</div>
