@php
	$className   = $content['className'] ?? '';
	$innerBlocks = $innerBlocks ?? [];

	$classes = 've-block ve-block-accordion-section';
	if ( $className ) {
		$classes .= " {$className}";
	}

	$bgColor      = veSanitizeCssColor( $styles['backgroundColor'] ?? null );
	$inlineStyles = '';
	if ( $bgColor ) {
		$inlineStyles .= "background-color: {$bgColor};";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
