@php
	$url       = $content['url'] ?? '';
	$caption   = $content['caption'] ?? '';
	$autoplay  = $styles['autoplay'] ?? false;
	$loop      = $styles['loop'] ?? false;
	$preload   = $styles['preload'] ?? 'metadata';
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
			preload="{{ $preload }}"
			@if ( $autoplay ) autoplay @endif
			@if ( $loop ) loop @endif
		>
			<source src="{{ $url }}">
		</audio>
	@endif
	@if ( $caption )
		<figcaption class="ve-audio-caption">{!! $caption !!}</figcaption>
	@endif
</figure>
