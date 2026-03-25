@php
	$showAvatar = $content['showAvatar'] ?? true;
	$avatarSize = $content['avatarSize'] ?? 'md';
	$showBio    = $content['showBio'] ?? true;
	$byline     = $content['byline'] ?? 'by';
	$isLink     = $content['isLink'] ?? false;
	$textColor  = $styles['textColor'] ?? null;
	$bgColor    = $styles['backgroundColor'] ?? null;
	$fontSize   = $styles['fontSize'] ?? null;
	$padding    = $styles['padding'] ?? null;
	$margin     = $styles['margin'] ?? null;
	$htmlId     = $content['htmlId'] ?? null;
	$className  = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId );

	$authorName      = veGetContentAuthorName( $context );
	$authorBio       = veGetContentAuthorBio( $context );
	$authorAvatarUrl = veGetContentAuthorAvatarUrl( $context );
	$authorUrl       = veGetContentAuthorUrl( $context );

	$avatarSizes = [ 'sm' => '2rem', 'md' => '3rem', 'lg' => '4rem' ];
	$avatarDim   = $avatarSizes[ $avatarSize ] ?? '3rem';

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

	$classes = 've-block ve-block-post-author';
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
	<div style="display: flex; align-items: flex-start; gap: 0.75rem;">
		@if ( $showAvatar && $authorAvatarUrl )
			<img
				src="{{ $authorAvatarUrl }}"
				alt="{{ $authorName ?: __( 'visual-editor::ve.post_author_avatar_alt' ) }}"
				style="width: {{ $avatarDim }}; height: {{ $avatarDim }}; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
			/>
		@endif
		<div>
			@if ( $byline )
				<span>{{ $byline }}</span>
			@endif
			@if ( $isLink && $authorUrl )
				<a href="{{ $authorUrl }}">{{ $authorName }}</a>
			@else
				<strong>{{ $authorName }}</strong>
			@endif
			@if ( $showBio && $authorBio )
				<p>{{ $authorBio }}</p>
			@endif
		</div>
	</div>
</div>
