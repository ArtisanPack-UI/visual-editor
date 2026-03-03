@php
	$url      = $content['url'] ?? '';
	$caption  = $content['caption'] ?? '';
	$poster   = $content['poster'] ?? '';
	$autoplay   = $content['autoplay'] ?? false;
	$loop       = $content['loop'] ?? false;
	$muted      = $content['muted'] ?? false;
	$controls   = $content['controls'] ?? true;
	$preload    = $content['preload'] ?? 'metadata';
	$playInline = $content['playInline'] ?? false;
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$isYouTube = str_contains( $url, 'youtube.com' ) || str_contains( $url, 'youtu.be' );
	$isVimeo   = str_contains( $url, 'vimeo.com' );

	$classes = 've-block ve-block-video';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<figure
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $isYouTube || $isVimeo )
		<div class="ve-block-video__embed" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">
			<iframe
				src="{{ $url }}"
				style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
				frameborder="0"
				allowfullscreen
			></iframe>
		</div>
	@elseif ( $url )
		<video
			src="{{ $url }}"
			@if ( $poster ) poster="{{ $poster }}" @endif
			@if ( $autoplay ) autoplay @endif
			@if ( $loop ) loop @endif
			@if ( $muted ) muted @endif
			@if ( $controls ) controls @endif
			preload="{{ $preload }}"
			@if ( $playInline ) playsinline @endif
			style="width: 100%;"
		></video>
	@endif
	@if ( $caption )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
