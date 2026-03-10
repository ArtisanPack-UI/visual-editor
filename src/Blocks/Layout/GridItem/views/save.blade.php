@php
	$itemId         = 've-grid-item-' . uniqid();
	$columnSpanData = $styles['columnSpan'] ?? [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ];
	if ( is_array( $columnSpanData ) ) {
		$colSpanMode    = $columnSpanData['mode'] ?? 'global';
		$globalColSpan  = $columnSpanData['global'] ?? $columnSpanData['desktop'] ?? 1;
		$desktopColSpan = ( 'responsive' === $colSpanMode ) ? ( $columnSpanData['desktop'] ?? 1 ) : $globalColSpan;
		$tabletColSpan  = ( 'responsive' === $colSpanMode ) ? ( $columnSpanData['tablet'] ?? 1 ) : $globalColSpan;
		$mobileColSpan  = ( 'responsive' === $colSpanMode ) ? ( $columnSpanData['mobile'] ?? 1 ) : $globalColSpan;
	} else {
		$desktopColSpan = $columnSpanData;
		$tabletColSpan  = $columnSpanData;
		$mobileColSpan  = $columnSpanData;
	}

	$rowSpanData = $styles['rowSpan'] ?? [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ];
	if ( is_array( $rowSpanData ) ) {
		$rowSpanMode    = $rowSpanData['mode'] ?? 'global';
		$globalRowSpan  = $rowSpanData['global'] ?? $rowSpanData['desktop'] ?? 1;
		$desktopRowSpan = ( 'responsive' === $rowSpanMode ) ? ( $rowSpanData['desktop'] ?? 1 ) : $globalRowSpan;
		$tabletRowSpan  = ( 'responsive' === $rowSpanMode ) ? ( $rowSpanData['tablet'] ?? 1 ) : $globalRowSpan;
		$mobileRowSpan  = ( 'responsive' === $rowSpanMode ) ? ( $rowSpanData['mobile'] ?? 1 ) : $globalRowSpan;
	} else {
		$desktopRowSpan = $rowSpanData;
		$tabletRowSpan  = $rowSpanData;
		$mobileRowSpan  = $rowSpanData;
	}

	$verticalAlignment    = $styles['verticalAlignment'] ?? 'stretch';
	$allowedAlignSelf     = [ 'auto', 'flex-start', 'flex-end', 'center', 'baseline', 'stretch' ];
	$verticalAlignment    = in_array( $verticalAlignment, $allowedAlignSelf, true ) ? $verticalAlignment : 'stretch';
	$innerBlocks          = $innerBlocks ?? [];

	$needsResponsiveStyles = ( $desktopColSpan !== $tabletColSpan || $desktopColSpan !== $mobileColSpan
		|| $desktopRowSpan !== $tabletRowSpan || $desktopRowSpan !== $mobileRowSpan );

	$inlineStyles = '';
	if ( ! $needsResponsiveStyles ) {
		if ( $desktopColSpan > 1 ) {
			$inlineStyles .= "grid-column: span {$desktopColSpan};";
		}
		if ( $desktopRowSpan > 1 ) {
			$inlineStyles .= " grid-row: span {$desktopRowSpan};";
		}
	}
	if ( 'stretch' !== $verticalAlignment ) {
		$inlineStyles .= " align-self: {$verticalAlignment};";
	}
@endphp

@if ( $needsResponsiveStyles )
<style>
	#{{ $itemId }} {
		@if ( $desktopColSpan > 1 ) grid-column: span {{ $desktopColSpan }}; @endif
		@if ( $desktopRowSpan > 1 ) grid-row: span {{ $desktopRowSpan }}; @endif
	}
	@@media (max-width: 1024px) {
		#{{ $itemId }} {
			@if ( $tabletColSpan > 1 ) grid-column: span {{ $tabletColSpan }}; @endif @if ( 1 === $tabletColSpan && $desktopColSpan > 1 ) grid-column: auto; @endif
			@if ( $tabletRowSpan > 1 ) grid-row: span {{ $tabletRowSpan }}; @endif @if ( 1 === $tabletRowSpan && $desktopRowSpan > 1 ) grid-row: auto; @endif
		}
	}
	@@media (max-width: 640px) {
		#{{ $itemId }} {
			@if ( $mobileColSpan > 1 ) grid-column: span {{ $mobileColSpan }}; @endif @if ( 1 === $mobileColSpan && ( $desktopColSpan > 1 || $tabletColSpan > 1 ) ) grid-column: auto; @endif
			@if ( $mobileRowSpan > 1 ) grid-row: span {{ $mobileRowSpan }}; @endif @if ( 1 === $mobileRowSpan && ( $desktopRowSpan > 1 || $tabletRowSpan > 1 ) ) grid-row: auto; @endif
		}
	}
</style>
@endif
<div
	class="ve-block ve-block-grid-item"
	@if ( $needsResponsiveStyles ) id="{{ $itemId }}" @endif
	@if ( trim( $inlineStyles ) ) style="{{ trim( $inlineStyles ) }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
