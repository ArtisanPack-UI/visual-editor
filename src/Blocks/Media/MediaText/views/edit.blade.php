@php
	$mediaType       = $content['mediaType'] ?? 'image';
	$mediaUrl        = $content['mediaUrl'] ?? '';
	$mediaAlt        = $content['mediaAlt'] ?? '';
	$focalPoint      = $content['focalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
	$mediaPosition   = $styles['mediaPosition'] ?? 'left';
	$mediaWidth      = $styles['mediaWidth'] ?? 50;
	$verticalAlign   = $styles['verticalAlignment'] ?? 'top';
	$imageFill       = $styles['imageFill'] ?? false;
	$gridGap         = $styles['gridGap'] ?? '0';
	$contentPadding  = $styles['contentPadding'] ?? '1rem';
	$contentBgColor  = $styles['contentBackgroundColor'] ?? null;
	$isStackedOnMobile = $styles['isStackedOnMobile'] ?? true;
	$mediaBorderRadius = $styles['mediaBorderRadius'] ?? '0';

	$blockId      = $blockId ?? 'media-text-' . uniqid();
	$mediaContext = $blockId . ':media-text-url';

	$focalX = is_array( $focalPoint ) ? ( $focalPoint['x'] ?? 0.5 ) : 0.5;
	$focalY = is_array( $focalPoint ) ? ( $focalPoint['y'] ?? 0.5 ) : 0.5;
	$objectPosition = round( $focalX * 100 ) . '% ' . round( $focalY * 100 ) . '%';

	$mediaWidth   = max( 25, min( 75, (int) $mediaWidth ) );
	$contentWidth = 100 - $mediaWidth;

	$alignMap = [
		'top'    => 'flex-start',
		'center' => 'center',
		'bottom' => 'flex-end',
	];
	$alignItemsValue = $alignMap[ $verticalAlign ] ?? 'flex-start';

	$gridCols = 'right' === $mediaPosition
		? "{$contentWidth}% {$mediaWidth}%"
		: "{$mediaWidth}% {$contentWidth}%";

	$classes = 've-block ve-block-media-text ve-block-editing';

	$mediaSideStyles = 'right' === $mediaPosition ? 'order: 1;' : 'order: 0;';
	if ( $imageFill && 'image' === $mediaType ) {
		$mediaSideStyles .= ' position: relative; min-height: 250px;';
	}

	$contentSideStyles = "padding: {$contentPadding};";
	$contentSideStyles .= 'right' === $mediaPosition ? ' order: 0;' : ' order: 1;';
	if ( $contentBgColor ) {
		$contentSideStyles .= " background-color: {$contentBgColor};";
	}
@endphp

<div
	class="{{ $classes }}"
	style="display: grid; grid-template-columns: {{ $gridCols }}; align-items: {{ $alignItemsValue }}; gap: {{ $gridGap }}; min-height: 200px;"
	x-data="{
		mode: {{ $mediaUrl ? Js::from( 'media' ) : Js::from( 'placeholder' ) }},
		mediaContext: {{ Js::from( $mediaContext ) }},

		openMediaPicker() {
			Livewire.dispatch( 'open-ve-media-picker', { context: this.mediaContext } );
		},
	}"
	x-on:ve-media-selected.window="
		if ( $event.detail.context === mediaContext && $event.detail.media?.length ) {
			const url = $event.detail.media[0].url ?? $event.detail.media[0].path ?? '';
			if ( url ) {
				$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'mediaUrl', value: url } );
				mode = 'media';
			}
		}
	"
>
	{{-- Media side --}}
	<div
		class="ve-media-text__media"
		style="{{ $mediaSideStyles }}"
	>
		<template x-if="mode === 'placeholder'">
			<x-ve-block-placeholder
				icon="photo"
				:block-name="__( 'visual-editor::ve.block_media-text_name' )"
				:description="__( 'visual-editor::ve.media_text_placeholder_desc' )"
			>
				<button type="button" class="btn btn-sm btn-primary" x-on:click="openMediaPicker()">
					{{ __( 'visual-editor::ve.placeholder_upload' ) }}
				</button>
				<button type="button" class="btn btn-sm btn-outline" x-on:click="openMediaPicker()">
					{{ __( 'visual-editor::ve.placeholder_media_library' ) }}
				</button>
			</x-ve-block-placeholder>
		</template>

		<template x-if="mode === 'media'">
			<div style="width: 100%; height: 100%;">
				@if ( 'image' === $mediaType && $mediaUrl )
					@if ( $imageFill )
						<img
							src="{{ $mediaUrl }}"
							alt="{{ $mediaAlt }}"
							style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $objectPosition }};"
						/>
					@else
						<img
							src="{{ $mediaUrl }}"
							alt="{{ $mediaAlt }}"
							style="width: 100%; height: auto; display: block;"
						/>
					@endif
				@elseif ( 'video' === $mediaType && $mediaUrl )
					<video
						src="{{ $mediaUrl }}"
						autoplay
						muted
						loop
						playsinline
						style="width: 100%; height: 100%; object-fit: cover; display: block;"
						aria-hidden="true"
					></video>
				@endif
			</div>
		</template>
	</div>

	{{-- Content side --}}
	<div
		class="ve-media-text__content"
		style="{{ $contentSideStyles }}"
	>
		<div class="ve-inner-blocks" data-inner-blocks>
			@foreach ( ( $innerBlocks ?? [] ) as $innerBlock )
				{!! $innerBlock !!}
			@endforeach
		</div>
	</div>
</div>
