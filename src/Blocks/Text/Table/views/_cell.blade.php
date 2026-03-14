@php
	$tag       = $tag ?? 'td';
	$scope     = $scope ?? null;
	$alignment = $cell['alignment'] ?? 'left';
	$validAlign = in_array( $alignment, [ 'left', 'center', 'right', 'justify' ], true ) ? $alignment : 'left';
@endphp
<{{ $tag }}
	@if ( $scope ) scope="{{ $scope }}" @endif
	@if ( ( $cell['colSpan'] ?? 1 ) > 1 ) colspan="{{ $cell['colSpan'] }}" @endif
	@if ( ( $cell['rowSpan'] ?? 1 ) > 1 ) rowspan="{{ $cell['rowSpan'] }}" @endif
	@if ( 'left' !== $validAlign ) style="text-align: {{ $validAlign }};" @endif
>{!! kses( $cell['content'] ?? '' ) !!}</{{ $tag }}>
