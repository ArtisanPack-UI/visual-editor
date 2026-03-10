@php
	$height = $content['height'] ?? '40';
	$unit   = $content['unit'] ?? 'px';

	$classes = 've-block ve-block-spacer ve-block-editing';
@endphp

<div
	class="{{ $classes }}"
	style="height: {{ $height }}{{ $unit }};"
	aria-hidden="true"
>
	<span class="ve-spacer-label">{{ $height }}{{ $unit }}</span>
</div>
