@php
	$columnsData = $content['columns'] ?? [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];
	if ( is_array( $columnsData ) ) {
		$columns = ( 'responsive' === ( $columnsData['mode'] ?? 'global' ) )
			? ( $columnsData['desktop'] ?? 3 )
			: ( $columnsData['global'] ?? $columnsData['desktop'] ?? 3 );
	} else {
		$columns = $columnsData;
	}
	$templateRows  = $content['templateRows'] ?? 'auto';
	$gap           = $styles['gap'] ?? 'medium';
	$rowGap        = $styles['rowGap'] ?? '';
	$alignItems    = $styles['alignItems'] ?? 'stretch';
	$justifyItems  = $styles['justifyItems'] ?? 'stretch';
	$innerBlocks   = $innerBlocks ?? [];

	$gapMap = [
		'none'   => '0',
		'small'  => '0.5rem',
		'medium' => '1rem',
		'large'  => '2rem',
	];

	$gapValue    = $gapMap[ $gap ] ?? '1rem';
	$rowGapValue = $rowGap ? ( $gapMap[ $rowGap ] ?? $gapValue ) : $gapValue;

	$inlineStyles = "display: grid; grid-template-columns: repeat({$columns}, 1fr); grid-template-rows: {$templateRows};";
	$inlineStyles .= " column-gap: {$gapValue}; row-gap: {$rowGapValue};";
	$inlineStyles .= " align-items: {$alignItems}; justify-items: {$justifyItems};";
@endphp

<div
	class="ve-block ve-block-grid ve-block-editing"
	style="{{ $inlineStyles }}"
	data-columns="{{ $columns }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
