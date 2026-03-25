@php
	$type         = $content['type'] ?? 'previous';
	$label        = $content['label'] ?? '';
	$showTitle    = $content['showTitle'] ?? true;
	$arrow        = $content['arrow'] ?? 'none';
	$taxonomy     = $content['taxonomy'] ?? '';
	$textColor    = $styles['textColor'] ?? null;
	$bgColor      = $styles['backgroundColor'] ?? null;
	$fontSize     = $styles['fontSize'] ?? null;
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;
	$borderRadius = $styles['borderRadius'] ?? null;
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$isPrevious   = 'previous' === $type;
	$navContext   = $taxonomy ? array_merge( $context, [ 'taxonomy' => $taxonomy ] ) : $context;
	$postUrl      = $isPrevious
		? veGetContentPreviousPostUrl( $navContext )
		: veGetContentNextPostUrl( $navContext );
	$postTitle    = $isPrevious
		? veGetContentPreviousPostTitle( $navContext )
		: veGetContentNextPostTitle( $navContext );

	$defaultLabel = $isPrevious
		? __( 'visual-editor::ve.post_nav_link_label_previous' )
		: __( 'visual-editor::ve.post_nav_link_label_next' );
	$displayLabel = $label ?: $defaultLabel;

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
		$inlineStyles .= "color: {$textColor};";
	}
	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
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

	$safeFontSize  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );
	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$directionClass = $isPrevious ? 'post-nav-previous' : 'post-nav-next';
	$classes        = "ve-block ve-block-post-navigation-link {$directionClass}";
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

@if ( $postUrl )
	<a
		href="{{ $postUrl }}"
		class="{{ $classes }}"
		@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
		@if ( $elementId ) id="{{ $elementId }}" @endif
	>
		@if ( $arrowBefore )
			<span class="post-nav-arrow" aria-hidden="true">{{ $arrowBefore }}</span>
		@endif
		<span class="post-nav-label">{{ $displayLabel }}</span>
		@if ( $showTitle && $postTitle )
			<span class="post-nav-title">{{ $postTitle }}</span>
		@endif
		@if ( $arrowAfter )
			<span class="post-nav-arrow" aria-hidden="true">{{ $arrowAfter }}</span>
		@endif
	</a>
@endif
