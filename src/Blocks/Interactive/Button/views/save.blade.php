@php
	$text         = $content['text'] ?? '';
	$url          = $content['url'] ?? '';
	$linkTarget   = $content['linkTarget'] ?? '_self';
	$icon         = $content['icon'] ?? '';
	$iconPosition = $content['iconPosition'] ?? 'left';
	$color        = $styles['color'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$size         = $styles['size'] ?? 'md';
	$variant      = $styles['variant'] ?? 'filled';
	$borderRadius = $styles['borderRadius'] ?? '';
	$width        = $styles['width'] ?? 'auto';
	$anchor       = $content['anchor'] ?? null;
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$elementId = veSanitizeHtmlId( $elementId );

	$inlineStyles = '';
	$color = veSanitizeCssColor( $color );
	if ( $color ) {
		$inlineStyles .= "color: {$color};";
	}
	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}
	$borderRadius = veSanitizeCssDimension( $borderRadius, '' );
	if ( $borderRadius ) {
		$inlineStyles .= "border-radius: {$borderRadius};";
	}

	$widthMap = [
		'25'  => '25%',
		'50'  => '50%',
		'75'  => '75%',
		'100' => '100%',
	];

	if ( isset( $widthMap[ $width ] ) ) {
		$inlineStyles .= "width: {$widthMap[ $width ]};";
	}

	$classes = "ve-block ve-block-button ve-button-{$size} ve-button-{$variant}";
	if ( $className ) {
		$classes .= " {$className}";
	}

	$nofollow  = $content['nofollow'] ?? false;
	$sponsored = $content['sponsored'] ?? false;

	$relParts = [];
	if ( '_blank' === $linkTarget ) {
		$relParts[] = 'noopener';
	}
	if ( $nofollow ) {
		$relParts[] = 'nofollow';
	}
	if ( $sponsored ) {
		$relParts[] = 'sponsored';
	}
	$relAttr = ! empty( $relParts ) ? implode( ' ', $relParts ) : null;
@endphp

<a
	class="{{ $classes }}"
	@if ( $url ) href="{{ $url }}" @endif
	@if ( '_blank' === $linkTarget ) target="_blank" @endif
	@if ( $relAttr ) rel="{{ $relAttr }}" @endif
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $icon && 'left' === $iconPosition )
		<span class="ve-button-icon ve-button-icon-left">{{ $icon }}</span>
	@endif
	<span class="ve-button-text">{{ $text }}</span>
	@if ( $icon && 'right' === $iconPosition )
		<span class="ve-button-icon ve-button-icon-right">{{ $icon }}</span>
	@endif
</a>
