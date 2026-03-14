@php
	$url              = $content['url'] ?? '';
	$platform         = $content['platform'] ?? '';
	$html             = $content['html'] ?? '';
	$title            = $content['title'] ?? '';
	$description      = $content['description'] ?? '';
	$thumbnailUrl     = $content['thumbnailUrl'] ?? '';
	$source           = $content['_source'] ?? '';
	$hideConversation = $content['hideConversation'] ?? false;
	$hideMedia        = $content['hideMedia'] ?? false;
	$maxWidth         = $styles['maxWidth'] ?? '550px';
	$align            = $styles['align'] ?? 'center';

	$maxWidth = veSanitizeCssDimension( $maxWidth, '550px' );

	$hasEmbed    = ! empty( $html ) && 'oembed' === $source;
	$hasFallback = ! empty( $title ) && 'opengraph' === $source;
	$isEmpty     = empty( $url );

	$alignMap = [
		'left'   => 'items-start',
		'center' => 'items-center',
		'right'  => 'items-end',
	];
	$alignClass = $alignMap[ $align ] ?? 'items-center';

	$platformLabels = [
		'twitter'   => 'Twitter/X',
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'tiktok'    => 'TikTok',
		'linkedin'  => 'LinkedIn',
		'reddit'    => 'Reddit',
		'bluesky'   => 'Bluesky',
	];
	$platformLabel = $platformLabels[ $platform ] ?? '';
@endphp

<div
	class="ve-block ve-block-social-embed ve-block-editing flex flex-col {{ $alignClass }}"
	x-data="{
		socialUrl: '',
		loading: false,
		error: false,
		getBlockId() {
			return Alpine.store( 'selection' )?.focused;
		},
		async resolveEmbed() {
			const url = this.socialUrl.trim();
			if ( ! url ) return;

			this.loading = true;
			this.error   = false;

			try {
				const response = await fetch( '/api/visual-editor/embed/resolve', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
					},
					body: JSON.stringify( { url } ),
				} );

				const json = await response.json();

				if ( json.success && json.data ) {
					const blockId = this.getBlockId();
					if ( blockId ) {
						Alpine.store( 'editor' ).updateBlock( blockId, {
							url:          url,
							platform:     json.platform || '',
							html:         json.data.html || '',
							title:        json.data.title || '',
							description:  json.data.description || '',
							thumbnailUrl: json.data.thumbnailUrl || json.data.thumbnail_url || '',
							_source:      json.data._source || '',
						} );
					}
				} else {
					this.error = true;
				}
			} catch ( e ) {
				this.error = true;
			}

			this.loading = false;
		},
	}"
>
	@if ( $isEmpty )
		<div class="ve-social-placeholder flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 px-6 py-10 w-full">
			<svg class="w-10 h-10 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
			</svg>
			<p class="text-sm text-base-content/60" x-show="! error">{{ __( 'visual-editor::ve.social_placeholder' ) }}</p>
			<p class="text-sm text-warning" x-show="error" x-cloak>{{ __( 'visual-editor::ve.embed_resolve_failed' ) }}</p>
			<div class="ve-social-url-input flex w-full max-w-md gap-2">
				<input
					type="url"
					class="input input-bordered input-sm flex-1"
					placeholder="{{ __( 'visual-editor::ve.social_url_placeholder' ) }}"
					aria-label="{{ __( 'visual-editor::ve.social_embed_url' ) }}"
					x-model="socialUrl"
					x-on:keydown.enter.prevent="resolveEmbed()"
				/>
				<button
					type="button"
					class="btn btn-primary btn-sm"
					x-on:click="resolveEmbed()"
					:disabled="loading"
				>
					<span x-show="! loading">{{ __( 'visual-editor::ve.embed_resolve' ) }}</span>
					<span x-show="loading" x-cloak class="loading loading-spinner loading-xs"></span>
				</button>
			</div>
		</div>
	@elseif ( $hasEmbed )
		<div class="ve-social-embed-wrapper" style="max-width: {{ $maxWidth }}; width: 100%;">
			@if ( $platformLabel )
				<div class="ve-social-platform-badge inline-flex items-center gap-1 rounded-full bg-base-200 px-2 py-0.5 text-xs font-medium text-base-content/70 mb-2">
					{{ $platformLabel }}
				</div>
			@endif
			<iframe
				srcdoc="{{ e( $html ) }}"
				sandbox="allow-scripts allow-popups"
				class="ve-social-iframe"
				title="{{ __( 'visual-editor::ve.social_post_from', ['platform' => $platformLabel ?: __( 'visual-editor::ve.social_media' )] ) }}"
				aria-label="{{ __( 'visual-editor::ve.social_post_from', ['platform' => $platformLabel ?: __( 'visual-editor::ve.social_media' )] ) }}"
				style="width: 100%; border: 0; min-height: 200px;"
				loading="lazy"
			></iframe>
		</div>
	@elseif ( $hasFallback )
		<div class="ve-social-fallback-card rounded-lg border border-base-300 bg-base-100 overflow-hidden" style="max-width: {{ $maxWidth }}; width: 100%;">
			@if ( $platformLabel )
				<div class="flex items-center gap-2 px-4 py-2 border-b border-base-200 bg-base-50">
					<span class="text-xs font-medium text-base-content/70">{{ $platformLabel }}</span>
				</div>
			@endif
			@if ( $thumbnailUrl )
				<div class="ve-social-thumbnail aspect-video bg-base-200 overflow-hidden">
					<img src="{{ $thumbnailUrl }}" alt="{{ $title }}" class="w-full h-full object-cover" loading="lazy" />
				</div>
			@endif
			<div class="p-4">
				<h4 class="font-semibold text-sm">{{ $title }}</h4>
				@if ( $description )
					<p class="text-xs text-base-content/60 mt-1 line-clamp-3">{{ $description }}</p>
				@endif
				<p class="text-xs text-base-content/40 mt-2 truncate">{{ $url }}</p>
			</div>
		</div>
	@else
		<div class="ve-social-loading flex items-center justify-center gap-2 rounded-lg border border-base-300 bg-base-200/50 px-6 py-10 w-full">
			<span class="loading loading-spinner loading-sm"></span>
			<span class="text-sm text-base-content/60">{{ __( 'visual-editor::ve.embed_resolving' ) }}</span>
		</div>
	@endif
</div>
