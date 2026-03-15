@php
	$columnsData = $content['columns'] ?? [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];
	if ( is_array( $columnsData ) ) {
		$columns = ( 'responsive' === ( $columnsData['mode'] ?? 'global' ) )
			? ( $columnsData['desktop'] ?? 3 )
			: ( $columnsData['global'] ?? $columnsData['desktop'] ?? 3 );
	} else {
		$columns = $columnsData;
	}
	$allowedAlignJustify = [ 'stretch', 'start', 'center', 'end', 'baseline' ];
	$templateRows  = preg_match( '/^[a-zA-Z0-9\s\.\-%()\/ ]+$/', $content['templateRows'] ?? 'auto' ) ? ( $content['templateRows'] ?? 'auto' ) : 'auto';
	$gap           = $styles['gap'] ?? 'medium';
	$rowGap        = $styles['rowGap'] ?? '';
	$alignItems    = in_array( $styles['alignItems'] ?? 'stretch', $allowedAlignJustify, true ) ? ( $styles['alignItems'] ?? 'stretch' ) : 'stretch';
	$justifyItems  = in_array( $styles['justifyItems'] ?? 'stretch', $allowedAlignJustify, true ) ? ( $styles['justifyItems'] ?? 'stretch' ) : 'stretch';
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
