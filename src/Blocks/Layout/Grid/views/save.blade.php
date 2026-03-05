@php
	$gridId      = 've-grid-' . uniqid();
	$columnsData = $content['columns'] ?? [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];
	if ( is_array( $columnsData ) ) {
		$colMode     = $columnsData['mode'] ?? 'global';
		$globalCols  = $columnsData['global'] ?? $columnsData['desktop'] ?? 3;
		$desktopCols = ( 'responsive' === $colMode ) ? ( $columnsData['desktop'] ?? 3 ) : $globalCols;
		$tabletCols  = ( 'responsive' === $colMode ) ? ( $columnsData['tablet'] ?? 2 ) : $globalCols;
		$mobileCols  = ( 'responsive' === $colMode ) ? ( $columnsData['mobile'] ?? 1 ) : $globalCols;
	} else {
		$desktopCols = $columnsData;
		$tabletCols  = $columnsData;
		$mobileCols  = $columnsData;
	}
	$templateRows  = $content['templateRows'] ?? 'auto';
	$gap           = $styles['gap'] ?? 'medium';
	$rowGap        = $styles['rowGap'] ?? '';
	$alignItems    = $styles['alignItems'] ?? 'stretch';
	$justifyItems  = $styles['justifyItems'] ?? 'stretch';
	$anchor        = $content['anchor'] ?? null;
	$htmlId        = $content['htmlId'] ?? null;
	$className     = $content['className'] ?? '';
	$innerBlocks   = $innerBlocks ?? [];

	$elementId = $htmlId ?: $anchor;
	$styleId   = $elementId ?: $gridId;

	$gapMap = [
		'none'   => '0',
		'small'  => '0.5rem',
		'medium' => '1rem',
		'large'  => '2rem',
	];

	$gapValue    = $gapMap[ $gap ] ?? '1rem';
	$rowGapValue = $rowGap ? ( $gapMap[ $rowGap ] ?? $gapValue ) : $gapValue;

	$classes = 've-block ve-block-grid';
	if ( $className ) {
		$classes .= " {$className}";
	}

	$baseStyles  = "display: grid; grid-template-rows: {$templateRows};";
	$baseStyles .= " column-gap: {$gapValue}; row-gap: {$rowGapValue};";
	$baseStyles .= " align-items: {$alignItems}; justify-items: {$justifyItems};";
@endphp

<style>
	#{{ $styleId }} { {{ $baseStyles }} grid-template-columns: repeat({{ $desktopCols }}, 1fr); }
	@@media (max-width: 1024px) { #{{ $styleId }} { grid-template-columns: repeat({{ $tabletCols }}, 1fr); } }
	@@media (max-width: 640px) { #{{ $styleId }} { grid-template-columns: repeat({{ $mobileCols }}, 1fr); } }
</style>
<div
	class="{{ $classes }}"
	id="{{ $styleId }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
