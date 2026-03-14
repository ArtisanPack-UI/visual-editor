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

	$aspectMap = [
		'16:9' => '56.25%',
		'4:3'  => '75%',
		'1:1'  => '100%',
	];
	$paddingTop = $aspectMap[ $aspectRatio ] ?? '56.25%';

	$hasEmbed    = ! empty( $html ) && 'oembed' === $source;
	$hasFallback = ! empty( $title ) && 'opengraph' === $source;
	$isEmpty     = empty( $url );
@endphp

<div
	class="ve-block ve-block-embed ve-block-editing"
	x-data="{
		embedUrl: '',
		loading: false,
		error: false,
		getBlockId() {
			return Alpine.store( 'selection' )?.focused;
		},
		async resolveEmbed() {
			const url = this.embedUrl.trim();
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
							html:         json.data.html || '',
							title:        json.data.title || '',
							description:  json.data.description || '',
							thumbnailUrl: json.data.thumbnailUrl || json.data.thumbnail_url || '',
							providerName: json.data.provider_name || json.data.providerName || '',
							providerUrl:  json.data.provider_url || json.data.providerUrl || '',
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
		<div class="ve-embed-placeholder flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 px-6 py-10">
			<svg class="w-10 h-10 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
			</svg>
			<p class="text-sm text-base-content/60" x-show="! error">{{ __( 'visual-editor::ve.embed_placeholder' ) }}</p>
			<p class="text-sm text-warning" x-show="error" x-cloak>{{ __( 'visual-editor::ve.embed_resolve_failed' ) }}</p>
			<div class="ve-embed-url-input flex w-full max-w-md gap-2">
				<input
					type="url"
					class="input input-bordered input-sm flex-1"
					placeholder="{{ __( 'visual-editor::ve.embed_url_placeholder' ) }}"
					aria-label="{{ __( 'visual-editor::ve.embed_url' ) }}"
					x-model="embedUrl"
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
		<figure class="ve-embed-figure">
			<div
				class="ve-embed-responsive-wrapper"
				@if ( $responsive )
					style="position: relative; padding-top: {{ $paddingTop }}; overflow: hidden;"
				@endif
			>
				<iframe
					srcdoc="{{ e( $html ) }}"
					sandbox="allow-scripts allow-popups"
					class="ve-embed-iframe"
					title="{{ $title ?: __( 'visual-editor::ve.embed_iframe_title' ) }}"
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
				<figcaption
					class="ve-embed-caption text-center text-sm text-base-content/60 mt-2"
					contenteditable="true"
					data-placeholder="{{ __( 'visual-editor::ve.embed_caption_placeholder' ) }}"
				>{{ $caption }}</figcaption>
			@endif
		</figure>
	@elseif ( $hasFallback )
		<div class="ve-embed-fallback-card rounded-lg border border-base-300 bg-base-100 overflow-hidden">
			@if ( $thumbnailUrl )
				<div class="ve-embed-thumbnail aspect-video bg-base-200 overflow-hidden">
					<img src="{{ $thumbnailUrl }}" alt="{{ $title }}" class="w-full h-full object-cover" loading="lazy" />
				</div>
			@endif
			<div class="p-4">
				<h4 class="font-semibold text-sm">{{ $title }}</h4>
				@if ( $description )
					<p class="text-xs text-base-content/60 mt-1 line-clamp-2">{{ $description }}</p>
				@endif
				<p class="text-xs text-base-content/40 mt-2 truncate">{{ $url }}</p>
			</div>
		</div>
	@else
		<div class="ve-embed-error flex flex-col items-center justify-center gap-3 rounded-lg border border-warning/30 bg-warning/5 px-6 py-10">
			<svg class="w-8 h-8 text-warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
			</svg>
			<p class="text-sm text-base-content/60">{{ __( 'visual-editor::ve.embed_resolve_failed' ) }}</p>
			<p class="text-xs text-base-content/40 truncate max-w-md">{{ $url }}</p>
		</div>
	@endif
</div>
