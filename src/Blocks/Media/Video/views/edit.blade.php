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
		<div class="ve-block-video__preview" style="position: relative;">
			<video
				src="{{ $url }}"
				@if ( $poster ) poster="{{ $poster }}" @endif
				controls
				muted
				style="width: 100%; display: block;"
			></video>
			<div style="position: absolute; inset: 0; cursor: default;" aria-hidden="true"></div>
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
		x-on:blur="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'caption', value: $el.innerText } )"
	>{{ $caption }}</figcaption>
</figure>
