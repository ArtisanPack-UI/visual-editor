@php
	$width         = $styles['width'] ?? '';
	$verticalAlign = $styles['verticalAlignment'] ?? 'top';
	$innerBlocks   = $innerBlocks ?? [];

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$alignValue = $alignMap[ $verticalAlign ] ?? 'flex-start';

	$inlineStyles = "display: flex; flex-direction: column; justify-content: {$alignValue};";
	if ( $width ) {
		$inlineStyles .= " flex-basis: {$width}; width: {$width};";
	} else {
		$inlineStyles .= " flex: 1;";
	}
@endphp

<div
	class="ve-block ve-block-column"
	style="{{ $inlineStyles }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
