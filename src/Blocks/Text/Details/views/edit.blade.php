@php
	$summary         = $content['summary'] ?? '';
	$isOpen          = $content['isOpenByDefault'] ?? false;
	$allowedIcons         = [ 'chevron', 'plus-minus', 'none' ];
	$allowedIconPositions = [ 'left', 'right' ];
	$allowedBorderStyles  = [ 'default', 'card', 'minimal', 'borderless' ];
	$iconCandidate        = $styles['icon'] ?? 'chevron';
	$iconPosCandidate     = $styles['iconPosition'] ?? 'left';
	$borderStyleCandidate = $styles['borderStyle'] ?? 'default';
	$icon            = in_array( $iconCandidate, $allowedIcons, true ) ? $iconCandidate : 'chevron';
	$iconPosition    = in_array( $iconPosCandidate, $allowedIconPositions, true ) ? $iconPosCandidate : 'left';
	$borderStyle     = in_array( $borderStyleCandidate, $allowedBorderStyles, true ) ? $borderStyleCandidate : 'default';
	$summaryBgColor  = $styles['summaryBackgroundColor'] ?? null;
	$contentBgColor  = $styles['contentBackgroundColor'] ?? null;
	$textColor       = $styles['textColor'] ?? null;
	$bgColor         = $styles['backgroundColor'] ?? null;
	$border          = $styles['border'] ?? [];
	$innerBlocks     = $innerBlocks ?? [];

	$classes = 've-block ve-block-details ve-block-editing';
	$classes .= " ve-details-style-{$borderStyle}";
	$classes .= " ve-details-icon-{$icon}";
	$classes .= " ve-details-icon-{$iconPosition}";

	$inlineStyles = '';

	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}

	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
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

	$summaryStyles = '';
	$summaryBgColor = veSanitizeCssColor( $summaryBgColor );
	if ( $summaryBgColor ) {
		$summaryStyles = "background-color: {$summaryBgColor};";
	}

	$contentStyles = '';
	$contentBgColor = veSanitizeCssColor( $contentBgColor );
	if ( $contentBgColor ) {
		$contentStyles = "background-color: {$contentBgColor};";
	}
@endphp

<details
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $isOpen ) open @endif
>
	<summary
		class="ve-details-summary"
		@if ( $summaryStyles ) style="{{ $summaryStyles }}" @endif
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.block_details_summary_placeholder' ) }}"
	>{!! kses( $summary ) !!}</summary>
	<div
		class="ve-details-content"
		@if ( $contentStyles ) style="{{ $contentStyles }}" @endif
	>
		<x-ve-inner-blocks
			:inner-blocks="$innerBlocks"
			:parent-id="$context['blockId'] ?? null"
			:placeholder="__( 'visual-editor::ve.block_details_content_placeholder' )"
			orientation="vertical"
			:editing="true"
		/>
	</div>
</details>
