@php
	$columnCount      = $content['columns'] ?? '2';
	$layout           = $content['layout'] ?? 'equal';
	$gap              = $styles['gap'] ?? 'medium';
	$verticalAlign    = $styles['verticalAlignment'] ?? 'top';
	$stackOnMobile    = $styles['stackOnMobile'] ?? true;
	$anchor           = $content['anchor'] ?? null;
	$className        = $content['className'] ?? '';
	$innerBlocks      = $innerBlocks ?? [];

	$gapMap = [
		'none'   => '0',
		'small'  => '0.5rem',
		'medium' => '1rem',
		'large'  => '2rem',
	];

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$gapValue  = $gapMap[ $gap ] ?? '1rem';
	$alignValue = $alignMap[ $verticalAlign ] ?? 'flex-start';

	$inlineStyles = "display: flex; gap: {$gapValue}; align-items: {$alignValue};";

	if ( $stackOnMobile ) {
		$inlineStyles .= " flex-wrap: wrap;";
	}

	$classes = "ve-block ve-block-columns";
	if ( $stackOnMobile ) {
		$classes .= " ve-columns-stack-mobile";
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	@if ( $anchor ) id="{{ $anchor }}" @endif
	data-columns="{{ $columnCount }}"
	data-layout="{{ $layout }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
