@php
	$height = $content['height'] ?? '40';
	$unit   = $content['unit'] ?? 'px';
@endphp

<div
	class="ve-block ve-block-spacer"
	style="height: {{ $height }}{{ $unit }};"
	aria-hidden="true"
></div>
