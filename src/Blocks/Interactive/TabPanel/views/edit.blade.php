@php
	$className   = $content['className'] ?? '';
	$innerBlocks = $innerBlocks ?? [];

	$classes = 've-block ve-block-tab-panel ve-block-editing';
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
	data-placeholder="{{ __( 'visual-editor::ve.tabs_tab_placeholder' ) }}"
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
