@php
	$size         = $content['size'] ?? 'md';
	$borderRadius = $content['borderRadius'] ?? '50%';
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$avatarUrl  = veGetContentCommentAuthorAvatarUrl( $context );
	$authorName = veGetContentCommentAuthorName( $context );

	$avatarSizes = [ 'sm' => '2rem', 'md' => '3rem', 'lg' => '4rem' ];
	$avatarDim   = $avatarSizes[ $size ] ?? '3rem';

	$safeBorderRadius = veSanitizeCssDimension( $borderRadius );
	if ( ! $safeBorderRadius ) {
		$safeBorderRadius = '50%';
	}

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

	$safeClassName = preg_replace( '/[^a-zA-Z0-9_\- ]/', '', (string) $className );

	$classes = 've-block ve-block-comment-author-avatar';
	if ( $safeClassName ) {
		$classes .= " {$safeClassName}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $avatarUrl )
		<img
			src="{{ $avatarUrl }}"
			alt="{{ $authorName ?: __( 'visual-editor::ve.comment_author_avatar_alt' ) }}"
			style="width: {{ $avatarDim }}; height: {{ $avatarDim }}; border-radius: {{ $safeBorderRadius }}; object-fit: cover; flex-shrink: 0;"
		/>
	@endif
</div>
