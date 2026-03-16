@php
	$className   = $content['className'] ?? '';
	$innerBlocks = $innerBlocks ?? [];

	$classes = 've-block ve-block-accordion-section ve-block-editing';
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
	data-placeholder="{{ __( 'visual-editor::ve.accordion_section_placeholder' ) }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
