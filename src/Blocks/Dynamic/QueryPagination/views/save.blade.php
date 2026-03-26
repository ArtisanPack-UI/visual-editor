@php
	$showNumbers   = $content['showNumbers'] ?? true;
	$showPrevNext  = $content['showPrevNext'] ?? true;
	$previousLabel = $content['previousLabel'] ?? '';
	$nextLabel     = $content['nextLabel'] ?? '';
	$midSize       = $content['midSize'] ?? 2;
	$textColor     = $styles['textColor'] ?? null;
	$bgColor       = $styles['backgroundColor'] ?? null;
	$fontSize      = $styles['fontSize'] ?? null;
	$padding       = $styles['padding'] ?? null;
	$margin        = $styles['margin'] ?? null;
	$htmlId        = $content['htmlId'] ?? null;
	$className     = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$pagination  = veGetQueryPagination( $context );
	$totalPages  = $pagination['totalPages'];
	$currentPage = $pagination['currentPage'];
	$previousUrl = $pagination['previousUrl'];
	$nextUrl     = $pagination['nextUrl'];

	$prevText = $previousLabel ?: __( 'visual-editor::ve.query_pagination_default_previous' );
	$nextText = $nextLabel ?: __( 'visual-editor::ve.query_pagination_default_next' );

	$safeMidSize = max( 1, min( 5, (int) $midSize ) );

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

	$classes = 've-block ve-block-query-pagination';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

@if ( $totalPages > 1 )
	<div
		class="{{ $classes }}"
		@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
		@if ( $elementId ) id="{{ $elementId }}" @endif
	>
		<nav aria-label="{{ __( 'visual-editor::ve.query_pagination_aria_label' ) }}" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
			@if ( $showPrevNext && $previousUrl )
				<a href="{{ $previousUrl }}">{{ $prevText }}</a>
			@endif
			@if ( $showNumbers )
				@for ( $i = 1; $i <= $totalPages; $i++ )
					@if ( $i === 1 || $i === $totalPages || abs( $i - $currentPage ) <= $safeMidSize )
						@if ( $i === $currentPage )
							<span aria-current="page" style="font-weight: bold;">{{ $i }}</span>
						@else
							<span>{{ $i }}</span>
						@endif
					@elseif ( abs( $i - $currentPage ) === $safeMidSize + 1 )
						<span>&hellip;</span>
					@endif
				@endfor
			@endif
			@if ( $showPrevNext && $nextUrl )
				<a href="{{ $nextUrl }}">{{ $nextText }}</a>
			@endif
		</nav>
	</div>
@endif
