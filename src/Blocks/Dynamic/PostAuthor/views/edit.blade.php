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

	$sampleName = __( 'visual-editor::ve.post_author_sample_name' );
	$sampleBio  = __( 'visual-editor::ve.post_author_sample_bio' );

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

	$safeFontSize = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fontSize );

	$classes = 've-block ve-block-post-author ve-block-editing ve-block-dynamic-preview';
	if ( $safeFontSize ) {
		$classes .= " text-{$safeFontSize}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div style="display: flex; align-items: flex-start; gap: 0.75rem;">
		@if ( $showAvatar )
			<div style="width: {{ $avatarDim }}; height: {{ $avatarDim }}; border-radius: 50%; background-color: #cbd5e1; flex-shrink: 0;"></div>
		@endif
		<div>
			@if ( $byline )
				<span style="opacity: 0.7;">{{ $byline }}</span>
			@endif
			@if ( $isLink )
				<a href="#" data-ve-preview-link style="pointer-events: none; cursor: default; color: inherit; text-decoration: inherit;">{{ $sampleName }}</a>
			@else
				<strong>{{ $sampleName }}</strong>
			@endif
			@if ( $showBio )
				<p style="margin-top: 0.25rem; opacity: 0.8;">{{ $sampleBio }}</p>
			@endif
		</div>
	</div>
</div>
