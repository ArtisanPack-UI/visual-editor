@php
	$mediaType        = $content['mediaType'] ?? 'image';
	$mediaUrl         = $content['mediaUrl'] ?? '';
	$alt              = $content['alt'] ?? '';
	$focalPoint       = $content['focalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
	$hasParallax      = $content['hasParallax'] ?? false;
	$isRepeated       = $content['isRepeated'] ?? false;
	$overlayColor     = $styles['overlayColor'] ?? '#000000';
	$overlayOpacity   = $styles['overlayOpacity'] ?? 50;
	$minHeight        = $styles['minHeight'] ?? '430px';
	$contentAlignment = $styles['contentAlignment'] ?? 'center';
	$textColor        = $styles['textColor'] ?? null;

	$blockId      = $blockId ?? 'cover-' . uniqid();
	$mediaContext = $blockId . ':cover-media';

	$focalX = is_array( $focalPoint ) ? ( $focalPoint['x'] ?? 0.5 ) : 0.5;
	$focalY = is_array( $focalPoint ) ? ( $focalPoint['y'] ?? 0.5 ) : 0.5;
	$objectPosition = round( $focalX * 100 ) . '% ' . round( $focalY * 100 ) . '%';

	$overlayOpacity = max( 0, min( 100, (int) $overlayOpacity ) );

	$alignmentMap = [
		'top-left'      => [ 'flex-start', 'flex-start' ],
		'top-center'    => [ 'flex-start', 'center' ],
		'top-right'     => [ 'flex-start', 'flex-end' ],
		'center-left'   => [ 'center', 'flex-start' ],
		'center'        => [ 'center', 'center' ],
		'center-right'  => [ 'center', 'flex-end' ],
		'bottom-left'   => [ 'flex-end', 'flex-start' ],
		'bottom-center' => [ 'flex-end', 'center' ],
		'bottom-right'  => [ 'flex-end', 'flex-end' ],
	];
	$alignment      = $alignmentMap[ $contentAlignment ] ?? [ 'center', 'center' ];
	$justifyContent = $alignment[0];
	$alignItems     = $alignment[1];

	$hasMedia = ( 'color' !== $mediaType && '' !== $mediaUrl );
	$classes  = 've-block ve-block-cover ve-block-editing';
@endphp

<div
	class="{{ $classes }}"
	style="position: relative; display: flex; flex-direction: column; justify-content: {{ $justifyContent }}; align-items: {{ $alignItems }}; min-height: {{ $minHeight }}; overflow: hidden;@if ( $textColor ) color: {{ $textColor }};@endif"
	x-data="{
		mode: {{ $hasMedia || 'color' === $mediaType ? Js::from( 'cover' ) : Js::from( 'placeholder' ) }},
		focalX: {{ Js::from( $focalX ) }},
		focalY: {{ Js::from( $focalY ) }},
		showFocalPicker: false,
		mediaContext: {{ Js::from( $mediaContext ) }},

		openMediaPicker() {
			Livewire.dispatch( 'open-ve-media-picker', { context: this.mediaContext } );
		},

		updateFocalPoint( e ) {
			const rect = e.currentTarget.getBoundingClientRect();
			this.focalX = Math.max( 0, Math.min( 1, ( e.clientX - rect.left ) / rect.width ) );
			this.focalY = Math.max( 0, Math.min( 1, ( e.clientY - rect.top ) / rect.height ) );
			$dispatch( 've-field-change', {
				blockId: {{ Js::from( $blockId ) }},
				field: 'focalPoint',
				value: { x: this.focalX, y: this.focalY }
			} );
		},
	}"
	x-on:ve-media-selected.window="
		if ( $event.detail.context === mediaContext && $event.detail.media?.length ) {
			const url = $event.detail.media[0].url ?? $event.detail.media[0].path ?? '';
			if ( url ) {
				$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'mediaUrl', value: url } );
				mode = 'cover';
			}
		}
	"
>
	{{-- Placeholder mode --}}
	<template x-if="mode === 'placeholder'">
		<x-ve-block-placeholder
			icon="rectangle-stack"
			:block-name="__( 'visual-editor::ve.block_cover_name' )"
			:description="__( 'visual-editor::ve.cover_placeholder_desc' )"
		>
			<button type="button" class="btn btn-sm btn-primary" x-on:click="openMediaPicker()">
				{{ __( 'visual-editor::ve.placeholder_upload' ) }}
			</button>
			<button type="button" class="btn btn-sm btn-outline" x-on:click="openMediaPicker()">
				{{ __( 'visual-editor::ve.placeholder_media_library' ) }}
			</button>
			<button
				type="button"
				class="btn btn-sm btn-ghost"
				x-on:click="
					$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'mediaType', value: 'color' } );
					mode = 'cover';
				"
			>
				{{ __( 'visual-editor::ve.cover_use_color' ) }}
			</button>
		</x-ve-block-placeholder>
	</template>

	{{-- Cover mode --}}
	<template x-if="mode === 'cover'">
		<div style="position: absolute; inset: 0; width: 100%; height: 100%;">
			{{-- Background media --}}
			@if ( 'image' === $mediaType && $mediaUrl )
				<div style="position: relative; width: 100%; height: 100%;">
					<img
						src="{{ $mediaUrl }}"
						alt="{{ $alt }}"
						style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $objectPosition }};@if ( $hasParallax ) opacity: 0.9;@endif"
						@if ( ! $alt ) aria-hidden="true" @endif
					/>
					{{-- Focal point picker --}}
					<template x-if="showFocalPicker">
						<div
							style="position: absolute; inset: 0; cursor: crosshair; z-index: 5;"
							x-on:click="updateFocalPoint( $event )"
						>
							<div
								style="position: absolute; width: 20px; height: 20px; border: 2px solid white; border-radius: 50%; box-shadow: 0 0 4px rgba(0,0,0,0.5); transform: translate(-50%, -50%); pointer-events: none;"
								x-bind:style="{ left: ( focalX * 100 ) + '%', top: ( focalY * 100 ) + '%' }"
							></div>
						</div>
					</template>
				</div>
			@elseif ( 'video' === $mediaType && $mediaUrl )
				<video
					src="{{ $mediaUrl }}"
					autoplay
					muted
					loop
					playsinline
					style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $objectPosition }};"
					aria-hidden="true"
				></video>
			@endif

			{{-- Overlay --}}
			<div
				style="position: absolute; inset: 0; background-color: {{ $overlayColor }}; opacity: {{ $overlayOpacity / 100 }};"
				aria-hidden="true"
			></div>
		</div>
	</template>

	{{-- Inner blocks content area --}}
	<template x-if="mode === 'cover'">
		<div
			class="ve-block-cover__content"
			style="position: relative; z-index: 1; padding: 2rem; width: 100%;"
		>
			<div class="ve-inner-blocks" data-inner-blocks>
				@foreach ( ( $innerBlocks ?? [] ) as $innerBlock )
					{!! $innerBlock !!}
				@endforeach
			</div>
		</div>
	</template>
</div>
