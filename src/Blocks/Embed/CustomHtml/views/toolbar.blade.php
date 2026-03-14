{{--
 * Custom HTML Block Toolbar Controls
 *
 * Provides a toggle button to switch between edit and preview modes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\CustomHtml\Views
 *
 * @since      1.0.0
 --}}

<div
	x-data="{
		get block() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
			return Alpine.store( 'editor' ).getBlock( blockId );
		},
		get isPreview() { return this.block?.attributes?.preview || false; },
		togglePreview() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) {
				Alpine.store( 'editor' ).updateBlock( blockId, { preview: ! this.isPreview } );
			}
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		:class="isPreview ? 'bg-base-200' : ''"
		x-on:click="togglePreview()"
		:aria-pressed="isPreview.toString()"
		:title="isPreview ? '{{ __( 'visual-editor::ve.custom_html_edit_mode' ) }}' : '{{ __( 'visual-editor::ve.custom_html_preview' ) }}'"
		:aria-label="isPreview ? '{{ __( 'visual-editor::ve.custom_html_edit_mode' ) }}' : '{{ __( 'visual-editor::ve.custom_html_preview' ) }}'"
	>
		<template x-if="! isPreview">
			<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
				<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
			</svg>
		</template>
		<template x-if="isPreview">
			<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
			</svg>
		</template>
	</button>
</div>
