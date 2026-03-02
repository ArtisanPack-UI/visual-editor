@php
	$url     = $content['url'] ?? '';
	$caption = $content['caption'] ?? '';
	$poster  = $content['poster'] ?? '';

	$classes  = 've-block ve-block-video ve-block-editing';
	$blockId  = $blockId ?? 'video-' . uniqid();
	$mediaContext = $blockId . ':video-url';
@endphp

<figure
	class="{{ $classes }}"
	x-data="{
		mode: {{ $url ? Js::from( 'video' ) : Js::from( 'placeholder' ) }},
		urlInput: '',
		mediaContext: {{ Js::from( $mediaContext ) }},

		openMediaPicker() {
			Livewire.dispatch( 'open-ve-media-picker', { context: this.mediaContext } );
		},

		showUrlInput() {
			this.urlInput = '';
			this.mode = 'url-input';
			this.$nextTick( () => {
				const input = this.$refs.urlField;
				if ( input ) { input.focus(); }
			} );
		},

		applyUrl() {
			if ( this.urlInput.trim() ) {
				$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'url', value: this.urlInput.trim() } );
				this.mode = 'video';
			}
		},

		cancelUrl() {
			this.urlInput = '';
			this.mode = 'placeholder';
		},
	}"
	x-on:ve-media-selected.window="
		if ( $event.detail.context === mediaContext && $event.detail.media?.length ) {
			const url = $event.detail.media[0].url ?? $event.detail.media[0].path ?? '';
			if ( url ) {
				$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'url', value: url } );
				mode = 'video';
			}
		}
	"
>
	<template x-if="mode === 'video'">
		<div class="ve-block-video__preview" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000;">
			<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff;">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48">
					<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
				</svg>
			</div>
		</div>
	</template>

	<template x-if="mode === 'placeholder'">
		<x-ve-block-placeholder
			icon="video"
			:block-name="__( 'visual-editor::ve.block_video_name' )"
			:description="__( 'visual-editor::ve.video_placeholder_desc' )"
		>
			<button type="button" class="btn btn-sm btn-primary" x-on:click="openMediaPicker()">
				{{ __( 'visual-editor::ve.placeholder_upload' ) }}
			</button>
			<button type="button" class="btn btn-sm btn-outline" x-on:click="openMediaPicker()">
				{{ __( 'visual-editor::ve.placeholder_media_library' ) }}
			</button>
			<button type="button" class="btn btn-sm btn-ghost" x-on:click="showUrlInput()">
				{{ __( 'visual-editor::ve.placeholder_insert_url' ) }}
			</button>
		</x-ve-block-placeholder>
	</template>

	<template x-if="mode === 'url-input'">
		<div class="ve-block-video__url-input p-4 border border-base-300 rounded-lg bg-base-200/50">
			<label class="text-sm font-medium text-base-content/80 block mb-2">
				{{ __( 'visual-editor::ve.video_url' ) }}
			</label>
			<div class="flex gap-2">
				<input
					x-ref="urlField"
					type="url"
					class="input input-bordered input-sm flex-1"
					placeholder="{{ __( 'visual-editor::ve.url_placeholder' ) }}"
					x-model="urlInput"
					x-on:keydown.enter.prevent="applyUrl()"
					x-on:keydown.escape.prevent="cancelUrl()"
				/>
				<button type="button" class="btn btn-sm btn-primary" x-on:click="applyUrl()">
					{{ __( 'visual-editor::ve.apply' ) }}
				</button>
				<button type="button" class="btn btn-sm btn-ghost" x-on:click="cancelUrl()">
					{{ __( 'visual-editor::ve.cancel' ) }}
				</button>
			</div>
		</div>
	</template>

	<figcaption
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.caption_placeholder' ) }}"
	>{!! $caption !!}</figcaption>
</figure>
