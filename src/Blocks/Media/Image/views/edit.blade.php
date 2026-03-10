@php
	$url         = $content['url'] ?? '';
	$alt         = $content['alt'] ?? '';
	$caption     = $content['caption'] ?? '';
	$size        = $styles['size'] ?? 'large';
	$alignment   = $styles['alignment'] ?? 'center';
	$rounded     = $styles['rounded'] ?? false;
	$shadow      = $styles['shadow'] ?? false;
	$objectFit   = $styles['objectFit'] ?? 'cover';
	$aspectRatio = $styles['aspectRatio'] ?? 'original';
	$imgWidth    = $styles['width'] ?? '';
	$imgHeight   = $styles['height'] ?? '';

	$classes = "ve-block ve-block-image ve-block-image--{$size} ve-block-editing text-{$alignment}";
	if ( $rounded ) {
		$classes .= ' ve-rounded';
	}
	if ( $shadow ) {
		$classes .= ' ve-shadow';
	}

	$imgStyle = "object-fit: {$objectFit};";
	if ( 'original' !== $aspectRatio ) {
		$imgStyle .= " aspect-ratio: {$aspectRatio};";
	}
	if ( $imgWidth ) {
		$imgStyle .= ' width: ' . ( is_numeric( $imgWidth ) ? $imgWidth . 'px' : $imgWidth ) . ';';
	}
	if ( $imgHeight ) {
		$imgStyle .= ' height: ' . ( is_numeric( $imgHeight ) ? $imgHeight . 'px' : $imgHeight ) . ';';
	}

	$blockId = $blockId ?? 'image-' . uniqid();
	$mediaContext = $blockId . ':image-url';
@endphp

<figure
	class="{{ $classes }}"
	x-data="{
		mode: {{ $url ? Js::from( 'image' ) : Js::from( 'placeholder' ) }},
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
				this.mode = 'image';
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
				mode = 'image';
			}
		}
	"
>
	{{-- Placeholder mode --}}
	<template x-if="mode === 'placeholder'">
		<x-ve-block-placeholder
			icon="photo"
			:block-name="__( 'visual-editor::ve.block_image_name' )"
			:description="__( 'visual-editor::ve.image_placeholder_desc' )"
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

	{{-- URL input mode --}}
	<template x-if="mode === 'url-input'">
		<div class="ve-block-image__url-input p-4 border border-base-300 rounded-lg bg-base-200/50">
			<label class="text-sm font-medium text-base-content/80 block mb-2">
				{{ __( 'visual-editor::ve.image_url' ) }}
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

	{{-- Image mode --}}
	<template x-if="mode === 'image'">
		<div>
			<img src="{{ $url }}" alt="{{ $alt }}" style="{{ $imgStyle }}" />
			<figcaption
				contenteditable="true"
				data-placeholder="{{ __( 'visual-editor::ve.caption_placeholder' ) }}"
				x-on:blur="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'caption', value: $el.textContent.trim() } )"
			>{!! $caption !!}</figcaption>
		</div>
	</template>
</figure>
