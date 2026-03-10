@php
	$columnSpanData = $styles['columnSpan'] ?? [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ];
	if ( is_array( $columnSpanData ) ) {
		$columnSpan = ( 'responsive' === ( $columnSpanData['mode'] ?? 'global' ) )
			? ( $columnSpanData['desktop'] ?? 1 )
			: ( $columnSpanData['global'] ?? $columnSpanData['desktop'] ?? 1 );
	} else {
		$columnSpan = $columnSpanData;
	}

	$rowSpanData = $styles['rowSpan'] ?? [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ];
	if ( is_array( $rowSpanData ) ) {
		$rowSpan = ( 'responsive' === ( $rowSpanData['mode'] ?? 'global' ) )
			? ( $rowSpanData['desktop'] ?? 1 )
			: ( $rowSpanData['global'] ?? $rowSpanData['desktop'] ?? 1 );
	} else {
		$rowSpan = $rowSpanData;
	}

	$verticalAlignment    = $styles['verticalAlignment'] ?? 'stretch';
	$allowedAlignSelf     = [ 'auto', 'flex-start', 'flex-end', 'center', 'baseline', 'stretch' ];
	$safeVerticalAlignment = in_array( $verticalAlignment, $allowedAlignSelf, true ) ? $verticalAlignment : 'stretch';
	$innerBlocks          = $innerBlocks ?? [];

	$inlineStyles = '';
	if ( $columnSpan > 1 ) {
		$inlineStyles .= "grid-column: span {$columnSpan};";
	}
	if ( $rowSpan > 1 ) {
		$inlineStyles .= " grid-row: span {$rowSpan};";
	}
	if ( 'stretch' !== $safeVerticalAlignment ) {
		$inlineStyles .= " align-self: {$safeVerticalAlignment};";
	}
@endphp

<div
	class="ve-block ve-block-grid-item ve-block-editing"
	@if ( $inlineStyles ) style="{{ trim( $inlineStyles ) }}" @endif
	data-placeholder="{{ __( 'visual-editor::ve.block_grid_item_placeholder' ) }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
