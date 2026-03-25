@php
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;
	$borderColor  = $styles['borderColor'] ?? null;
	$borderWidth  = $styles['borderWidth'] ?? null;
	$borderRadius = $styles['borderRadius'] ?? null;
	$htmlId        = $content['htmlId'] ?? null;
	$className     = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$comments = veGetContentComments( $context );

	$inlineStyles = '';

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

	$borderColor = veSanitizeCssColor( $borderColor );
	if ( $borderColor ) {
		$inlineStyles .= " border-color: {$borderColor};";
	}
	$borderWidth = veSanitizeCssDimension( $borderWidth );
	if ( $borderWidth ) {
		$inlineStyles .= " border-width: {$borderWidth}; border-style: solid;";
	}
	$borderRadius = veSanitizeCssDimension( $borderRadius );
	if ( $borderRadius ) {
		$inlineStyles .= " border-radius: {$borderRadius};";
	}

	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-comment-template';
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@foreach ( $comments as $comment )
		<div class="ve-comment-item">
			@foreach ( $innerBlocks as $innerBlock )
				{!! $innerBlock !!}
			@endforeach
			@if ( ! empty( $comment['children'] ) )
				<div class="ve-comment-replies" style="margin-left: 2rem;">
					@foreach ( $comment['children'] as $reply )
						<div class="ve-comment-item">
							@foreach ( $innerBlocks as $innerBlock )
								{!! $innerBlock !!}
							@endforeach
						</div>
					@endforeach
				</div>
			@endif
		</div>
	@endforeach
</div>
