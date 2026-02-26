@php
	$height    = $content['height'] ?? '40';
	$unit      = $content['unit'] ?? 'px';
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$classes = 've-block ve-block-spacer';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	style="height: {{ $height }}{{ $unit }};"
	@if ( $elementId ) id="{{ $elementId }}" @endif
	aria-hidden="true"
></div>
