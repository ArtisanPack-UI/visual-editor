@php
	$url          = $content['url'] ?? '';
	$platform     = $content['platform'] ?? '';
	$html         = $content['html'] ?? '';
	$title        = $content['title'] ?? '';
	$description  = $content['description'] ?? '';
	$thumbnailUrl = $content['thumbnailUrl'] ?? '';
	$source       = $content['_source'] ?? '';
	$maxWidth     = $styles['maxWidth'] ?? '550px';
	$align        = $styles['align'] ?? 'center';
	$anchor       = $content['anchor'] ?? null;
	$htmlId       = $content['htmlId'] ?? null;
	$className    = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );
	$maxWidth  = veSanitizeCssDimension( $maxWidth, '550px' );

	$hasEmbed    = ! empty( $html ) && 'oembed' === $source;
	$hasFallback = ! empty( $title ) && 'opengraph' === $source;

	$alignMap = [
		'left'   => 'flex-start',
		'center' => 'center',
		'right'  => 'flex-end',
	];
	$alignValue = $alignMap[ $align ] ?? 'center';

	$classes = 've-block ve-block-social-embed';
	if ( $className ) {
		$classes .= " {$className}";
	}

	$platformLabels = [
		'twitter'   => 'Twitter/X',
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'tiktok'    => 'TikTok',
		'linkedin'  => 'LinkedIn',
		'reddit'    => 'Reddit',
		'bluesky'   => 'Bluesky',
	];
	$platformLabel = $platformLabels[ $platform ] ?? __( 'visual-editor::ve.social_media' );
@endphp

<div
	class="{{ $classes }}"
	style="display: flex; flex-direction: column; align-items: {{ $alignValue }};"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $hasEmbed )
		<iframe
			srcdoc="{{ e( $html ) }}"
			sandbox="allow-scripts allow-same-origin allow-popups"
			class="ve-social-iframe"
			title="{{ __( 'visual-editor::ve.social_post_from', ['platform' => $platformLabel] ) }}"
			aria-label="{{ __( 'visual-editor::ve.social_post_from', ['platform' => $platformLabel] ) }}"
			style="max-width: {{ $maxWidth }}; width: 100%; border: 0; min-height: 200px;"
			loading="lazy"
		></iframe>
	@elseif ( $hasFallback )
		<a href="{{ $url }}" class="ve-social-fallback-card" style="max-width: {{ $maxWidth }}; width: 100%;" target="_blank" rel="noopener noreferrer">
			@if ( $thumbnailUrl )
				<div class="ve-social-thumbnail">
					<img src="{{ $thumbnailUrl }}" alt="{{ $title }}" loading="lazy" />
				</div>
			@endif
			<div class="ve-social-fallback-body">
				<span class="ve-social-platform-label">{{ $platformLabel }}</span>
				<h4 class="ve-social-fallback-title">{{ $title }}</h4>
				@if ( $description )
					<p class="ve-social-fallback-description">{{ $description }}</p>
				@endif
			</div>
		</a>
	@endif
</div>
