@php
	$isLink     = $content['isLink'] ?? true;
	$linkTarget = $content['linkTarget'] ?? '_self';
	$imgWidth   = $styles['width'] ?? '';
	$imgHeight  = $styles['height'] ?? '';
	$padding    = $styles['padding'] ?? null;
	$margin     = $styles['margin'] ?? null;
	$border     = $styles['border'] ?? [];
	$htmlId     = $content['htmlId'] ?? null;
	$className  = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$logoUrl = veGetSiteLogoUrl();
	$logoAlt = veGetSiteLogoAlt();
	$homeUrl = veGetSiteHomeUrl();

	$inlineStyles = '';
	if ( is_array( $padding ) ) {
		$top    = veSanitizeCssDimension( $padding['top'] ?? '0' );
		$right  = veSanitizeCssDimension( $padding['right'] ?? '0' );
		$bottom = veSanitizeCssDimension( $padding['bottom'] ?? '0' );
		$left   = veSanitizeCssDimension( $padding['left'] ?? '0' );
		$inlineStyles .= "padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = veSanitizeCssDimension( $margin['top'] ?? '0' );
		$bottom = veSanitizeCssDimension( $margin['bottom'] ?? '0' );
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth     = veSanitizeCssNumber( $border['width'] ?? '0' );
		$bWidthUnit = veSanitizeCssUnit( $border['widthUnit'] ?? 'px' );
		$bStyle     = veSanitizeBorderStyle( $border['style'] ?? 'solid' );
		$bColor     = veSanitizeCssColor( $border['color'] ?? 'currentColor', 'currentColor' );
		$inlineStyles .= " border: {$bWidth}{$bWidthUnit} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadius     = veSanitizeCssNumber( $bRadius );
			$bRadiusUnit = veSanitizeCssUnit( $border['radiusUnit'] ?? 'px' );
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	$imgStyle = '';
	if ( $imgWidth ) {
		$imgStyle .= 'width: ' . ( is_numeric( $imgWidth ) ? $imgWidth . 'px' : veSanitizeCssDimension( (string) $imgWidth, '' ) ) . ';';
	}
	if ( $imgHeight ) {
		$imgStyle .= ' height: ' . ( is_numeric( $imgHeight ) ? $imgHeight . 'px' : veSanitizeCssDimension( (string) $imgHeight, '' ) ) . ';';
	}

	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-site-logo';
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

@if ( $logoUrl )
	<div
		class="{{ $classes }}"
		@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
		@if ( $elementId ) id="{{ $elementId }}" @endif
	>
		@if ( $isLink )
			<a href="{{ $homeUrl }}"@if ( '_blank' === $linkTarget ) target="_blank" rel="noopener noreferrer"@endif>
				<img src="{{ $logoUrl }}" alt="{{ $logoAlt }}" @if ( $imgStyle ) style="{{ $imgStyle }}" @endif />
			</a>
		@else
			<img src="{{ $logoUrl }}" alt="{{ $logoAlt }}" @if ( $imgStyle ) style="{{ $imgStyle }}" @endif />
		@endif
	</div>
@endif
