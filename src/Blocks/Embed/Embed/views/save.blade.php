@php
	$url          = $content['url'] ?? '';
	$html         = $content['html'] ?? '';
	$caption      = $content['caption'] ?? '';
	$title        = $content['title'] ?? '';
	$description  = $content['description'] ?? '';
	$thumbnailUrl = $content['thumbnailUrl'] ?? '';
	$source       = $content['_source'] ?? '';
	$aspectRatio  = $styles['aspectRatio'] ?? '16:9';
	$responsive   = $styles['responsive'] ?? true;
	$anchor       = $content['anchor'] ?? null;
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$aspectMap = [
		'16:9' => '56.25%',
		'4:3'  => '75%',
		'1:1'  => '100%',
	];
	$paddingTop = $aspectMap[ $aspectRatio ] ?? '56.25%';

	$hasEmbed    = ! empty( $html ) && 'oembed' === $source;
	$hasFallback = ! empty( $title ) && 'opengraph' === $source;

	$classes = 've-block ve-block-embed';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $hasEmbed )
		<figure class="ve-embed-figure">
			<div
				class="ve-embed-responsive-wrapper"
				@if ( $responsive )
					style="position: relative; padding-top: {{ $paddingTop }}; overflow: hidden;"
				@endif
			>
				<iframe
					srcdoc="{{ e( $html ) }}"
					sandbox="allow-scripts allow-same-origin allow-popups"
					class="ve-embed-iframe"
					title="{{ $title ?: __( 'visual-editor::ve.embedded_content' ) }}"
					aria-label="{{ $title ? __( 'visual-editor::ve.embed_content_from', ['provider' => $title] ) : __( 'visual-editor::ve.embedded_content' ) }}"
					@if ( $responsive )
						style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
					@else
						style="width: 100%; height: 300px; border: 0;"
					@endif
					loading="lazy"
				></iframe>
			</div>
			@if ( $caption )
				<figcaption class="ve-embed-caption">{{ $caption }}</figcaption>
			@endif
		</figure>
	@elseif ( $hasFallback )
		<a href="{{ $url }}" class="ve-embed-fallback-card" target="_blank" rel="noopener noreferrer">
			@if ( $thumbnailUrl )
				<div class="ve-embed-thumbnail">
					<img src="{{ $thumbnailUrl }}" alt="{{ $title }}" loading="lazy" />
				</div>
			@endif
			<div class="ve-embed-fallback-body">
				<h4 class="ve-embed-fallback-title">{{ $title }}</h4>
				@if ( $description )
					<p class="ve-embed-fallback-description">{{ $description }}</p>
				@endif
				<span class="ve-embed-fallback-url">{{ $url }}</span>
			</div>
		</a>
	@endif
</div>
