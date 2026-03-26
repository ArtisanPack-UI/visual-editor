@php
	$layout    = $content['layout'] ?? 'list';
	$columns   = $content['columns'] ?? 3;
	$padding   = $styles['padding'] ?? null;
	$margin    = $styles['margin'] ?? null;

	$safeLayout  = in_array( $layout, [ 'list', 'grid' ], true ) ? $layout : 'list';
	$safeColumns = max( 1, min( 6, (int) $columns ) );

	$inlineStyles = '';

	if ( 'grid' === $safeLayout ) {
		$inlineStyles .= " display: grid; grid-template-columns: repeat({$safeColumns}, 1fr); gap: 1.5rem;";
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

	$classes = 've-block ve-block-post-template ve-block-post-template--' . $safeLayout . ' ve-block-editing ve-block-dynamic-preview';
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div style="opacity: 0.7; font-style: italic; padding: 0.5rem; border: 1px dashed currentColor; border-radius: 0.25rem;">
		{{ __( 'visual-editor::ve.post_template_placeholder' ) }}
	</div>
	@foreach ( $innerBlocks as $innerBlock )
		<div class="ve-post-template-item">
			{!! $innerBlock !!}
		</div>
	@endforeach
</div>
