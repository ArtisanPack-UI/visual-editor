@php
	$columnsData   = $content['columns'] ?? [ 'mode' => 'global', 'global' => 2 ];
	$columnCount   = is_array( $columnsData )
		? ( $columnsData['global'] ?? $columnsData['desktop'] ?? 2 )
		: ( (int) $columnsData ?: 2 );
	$layout        = $content['layout'] ?? 'equal';
	$gap           = $styles['gap'] ?? 'medium';
	$verticalAlign = $styles['verticalAlignment'] ?? 'top';
	$innerBlocks   = $innerBlocks ?? [];

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

	$gapValue   = $gapMap[ $gap ] ?? '1rem';
	$alignValue = $alignMap[ $verticalAlign ] ?? 'flex-start';

	$inlineStyles = "display: flex; gap: {$gapValue}; align-items: {$alignValue};";
@endphp

<div
	class="ve-block ve-block-columns ve-block-editing"
	style="{{ $inlineStyles }}"
	data-columns="{{ $columnCount }}"
	data-layout="{{ $layout }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
