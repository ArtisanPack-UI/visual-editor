@php
	$isLink    = $content['isLink'] ?? true;
	$imgWidth  = $styles['width'] ?? '';
	$imgHeight = $styles['height'] ?? '';
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;
	$border    = $styles['border'] ?? [];

	$logoUrl = veGetSiteLogoUrl();
	$logoAlt = veGetSiteLogoAlt();

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
		$imgStyle .= 'width: ' . ( is_numeric( $imgWidth ) ? $imgWidth . 'px' : $imgWidth ) . ';';
	}
	if ( $imgHeight ) {
		$imgStyle .= ' height: ' . ( is_numeric( $imgHeight ) ? $imgHeight . 'px' : $imgHeight ) . ';';
	}
@endphp

<div
	class="ve-block ve-block-site-logo ve-block-editing ve-block-dynamic-preview"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@if ( $logoUrl )
		<img src="{{ $logoUrl }}" alt="{{ $logoAlt }}" @if ( $imgStyle ) style="{{ $imgStyle }}" @endif />
	@else
		<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; background-color: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 8px; color: #6b7280; min-height: 80px;">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 2rem; height: 2rem; margin-bottom: 0.5rem;">
				<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
			</svg>
			<span style="font-size: 0.875rem;">{{ __( 'visual-editor::ve.site_logo_empty' ) }}</span>
		</div>
	@endif
</div>
