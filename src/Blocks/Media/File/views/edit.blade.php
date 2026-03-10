@php
	$url                = $content['url'] ?? '';
	$filename           = $content['filename'] ?? '';
	$fileSize           = $content['fileSize'] ?? '';
	$downloadButtonText = $content['downloadButtonText'] ?? __( 'visual-editor::ve.download' );
	$showDownloadButton = $styles['showDownloadButton'] ?? true;
	$displayPreview     = $styles['displayPreview'] ?? false;
	$previewHeight      = $styles['previewHeight'] ?? 600;

	$classes      = 've-block ve-block-file ve-block-editing';
	$blockId      = $blockId ?? 'file-' . uniqid();
	$mediaContext = $blockId . ':file-url';
@endphp

<div
	class="{{ $classes }}"
	x-data="{
		mode: {{ $url ? Js::from( 'file' ) : Js::from( 'placeholder' ) }},
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
				this.$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'url', value: this.urlInput.trim() } );
				this.mode = 'file';
			}
		},

		cancelUrl() {
			this.urlInput = '';
			this.mode = 'placeholder';
		},

		isPdf( url ) {
			return /\.pdf($|\?)/i.test( url || '' );
		},
	}"
	x-on:ve-media-selected.window="
		if ( $event.detail.context === mediaContext && $event.detail.media?.length ) {
			const url = $event.detail.media[0].url ?? $event.detail.media[0].path ?? '';
			if ( url ) {
				$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'url', value: url } );
				mode = 'file';
			}
		}
	"
>
	<template x-if="mode === 'placeholder'">
		<x-ve-block-placeholder
			icon="document"
			:block-name="__( 'visual-editor::ve.block_file_name' )"
			:description="__( 'visual-editor::ve.file_placeholder_desc' )"
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
		<div class="ve-block-file__url-input p-4 border border-base-300 rounded-lg bg-base-200/50">
			<label class="text-sm font-medium text-base-content/80 block mb-2">
				{{ __( 'visual-editor::ve.file_url' ) }}
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

	<template x-if="mode === 'file'">
		<div>
			@if ( $displayPreview )
				<div
					class="ve-block-file__preview mb-3"
					x-show="isPdf( {{ Js::from( $url ) }} )"
					x-cloak
				>
					<object
						data="{{ $url }}"
						type="application/pdf"
						style="width: 100%; height: {{ $previewHeight }}px;"
					>
						<p>{{ __( 'visual-editor::ve.download' ) }}: <a href="{{ $url }}">{{ $filename ?: $url }}</a></p>
					</object>
				</div>
			@endif

			<div class="ve-block-file__content">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
					<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
				</svg>
				<div class="ve-block-file__info">
					<span
						class="ve-block-file__name"
						contenteditable="true"
						data-placeholder="{{ __( 'visual-editor::ve.filename_placeholder' ) }}"
						x-on:blur="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'filename', value: $el.innerText } )"
					>{{ $filename }}</span>
					@if ( $fileSize )
						<span class="ve-block-file__size">{{ $fileSize }}</span>
					@endif
				</div>
				@if ( $showDownloadButton )
					<span
						class="ve-block-file__download"
						contenteditable="true"
						data-placeholder="{{ __( 'visual-editor::ve.download' ) }}"
						x-on:blur="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'downloadButtonText', value: $el.innerText } )"
					>{{ $downloadButtonText }}</span>
				@endif
			</div>
		</div>
	</template>
</div>
