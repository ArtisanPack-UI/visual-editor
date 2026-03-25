@php
	$size         = $content['size'] ?? 'md';
	$borderRadius = $content['borderRadius'] ?? '50%';
	$padding      = $styles['padding'] ?? null;
	$margin       = $styles['margin'] ?? null;

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

	$classes = 've-block ve-block-comment-author-avatar ve-block-editing ve-block-dynamic-preview';
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div style="width: {{ $avatarDim }}; height: {{ $avatarDim }}; border-radius: {{ $safeBorderRadius }}; background-color: #cbd5e1; flex-shrink: 0;"></div>
</div>
