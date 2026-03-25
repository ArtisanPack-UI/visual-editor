@php
	$type         = $content['type'] ?? 'previous';
	$label        = $content['label'] ?? '';
	$showTitle    = $content['showTitle'] ?? true;
	$arrow        = $content['arrow'] ?? 'none';
	$textColor    = $styles['textColor'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$fontSize     = $styles['fontSize'] ?? null;
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;
	$borderRadius = $styles['borderRadius'] ?? null;

	$isPrevious   = 'previous' === $type;

	$defaultLabel = $isPrevious
		? __( 'visual-editor::ve.post_nav_link_label_previous' )
		: __( 'visual-editor::ve.post_nav_link_label_next' );
	$displayLabel = $label ?: $defaultLabel;
	$sampleTitle  = __( 'visual-editor::ve.post_nav_link_sample_title' );

	$arrowBefore = '';
	$arrowAfter  = '';
	if ( 'arrow' === $arrow ) {
		if ( $isPrevious ) {
			$arrowBefore = __( 'visual-editor::ve.post_nav_link_arrow_previous' ) . ' ';
		} else {
			$arrowAfter = ' ' . __( 'visual-editor::ve.post_nav_link_arrow_next' );
		}
	} elseif ( 'chevron' === $arrow ) {
		if ( $isPrevious ) {
			$arrowBefore = __( 'visual-editor::ve.post_nav_link_chevron_previous' ) . ' ';
		} else {
			$arrowAfter = ' ' . __( 'visual-editor::ve.post_nav_link_chevron_next' );
		}
	}

	$inlineStyles = '';
	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= " color: {$textColor};";
	}
	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	$safeBorderRadius = veSanitizeCssDimension( $borderRadius ?: '' );
	if ( $safeBorderRadius && '0' !== $safeBorderRadius ) {
		$inlineStyles .= " border-radius: {$safeBorderRadius};";
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

	$inlineStyles = ltrim( $inlineStyles );

	$safeFontSize = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );

	$directionClass = $isPrevious ? 'post-nav-previous' : 'post-nav-next';
	$classes        = "ve-block ve-block-post-navigation-link {$directionClass} ve-block-editing ve-block-dynamic-preview";
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<a
	href="#"
	data-ve-preview-link
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="pointer-events: none; cursor: default; text-decoration: inherit;{{ $inlineStyles }}" @else style="pointer-events: none; cursor: default; text-decoration: inherit;" @endif
>
	@if ( $arrowBefore )
		<span class="post-nav-arrow" aria-hidden="true">{{ $arrowBefore }}</span>
	@endif
	<span class="post-nav-label">{{ $displayLabel }}</span>
	@if ( $showTitle )
		<span class="post-nav-title">{{ $sampleTitle }}</span>
	@endif
	@if ( $arrowAfter )
		<span class="post-nav-arrow" aria-hidden="true">{{ $arrowAfter }}</span>
	@endif
</a>
