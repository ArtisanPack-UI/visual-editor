@php
	$url       = $content['url'] ?? '';
	$caption   = $content['caption'] ?? '';
	$autoplay  = $content['autoplay'] ?? false;
	$loop      = $content['loop'] ?? false;
	$preload   = $content['preload'] ?? '';
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$classes = 've-block ve-block-audio';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<figure
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $url )
		<audio
			controls
			src="{{ $url }}"
			@if ( $preload ) preload="{{ $preload }}" @endif
			@if ( $autoplay ) autoplay @endif
			@if ( $loop ) loop @endif
		></audio>
	@endif
	@if ( $caption )
		<figcaption class="ve-audio-caption">{!! $caption !!}</figcaption>
	@endif
</figure>
