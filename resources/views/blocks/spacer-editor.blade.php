@php
	$height = $content['height'] ?? '40';
	$unit   = $content['unit'] ?? 'px';
@endphp

<div
	class="ve-block ve-block-spacer ve-block-editing"
	style="height: {{ $height }}{{ $unit }}; position: relative;"
	aria-hidden="true"
>
	<span
		class="ve-spacer-label"
		style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 0.75rem; color: #999; pointer-events: none;"
	>{{ $height }}{{ $unit }}</span>
</div>
