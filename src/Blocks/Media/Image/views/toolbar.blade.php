{{--
 * Image Block Toolbar Controls
 *
 * Provides Replace and Link toolbar controls for the image block.
 * Replace opens the media-library modal; Link toggles a link popover.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Image\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		linkOpen: false,
		linkUrl: '',
		linkTarget: '_self',
		_lastBlockId: null,

		init() {
			this.$watch( () => Alpine.store( 'selection' )?.focused, ( id ) => {
				if ( id !== this._lastBlockId ) {
					this.linkOpen   = false;
					this.linkUrl    = '';
					this.linkTarget = '_self';
					this._lastBlockId = id;
				}
			} );
		},

		get block() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
			return Alpine.store( 'editor' ).getBlock( blockId );
		},

		get hasLink() {
			return !! ( this.block?.attributes?.link );
		},

		replaceImage() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId ) return;
			Livewire.dispatch( 'open-ve-media-picker', { context: blockId + ':toolbar-replace' } );
		},

		toggleLink() {
			if ( this.linkOpen ) {
				this.linkOpen = false;
				return;
			}
			this.linkUrl    = this.block?.attributes?.link || '';
			this.linkTarget = this.block?.attributes?.linkTarget || '_self';
			this.linkOpen   = true;
		},

		applyLink() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
			Alpine.store( 'editor' ).updateBlock( blockId, {
				link: this.linkUrl,
				linkTarget: this.linkTarget,
			} );
			this.linkOpen = false;
		},

		removeLink() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
			Alpine.store( 'editor' ).updateBlock( blockId, { link: '', linkTarget: '_self' } );
			this.linkUrl    = '';
			this.linkTarget = '_self';
			this.linkOpen   = false;
		},
	}"
	x-on:ve-media-selected.window="
		const blockId = Alpine.store( 'selection' )?.focused;
		if ( blockId && $event.detail.context === blockId + ':toolbar-replace' && $event.detail.media?.length ) {
			const url = $event.detail.media[0].url ?? $event.detail.media[0].path ?? '';
			if ( url ) {
				Alpine.store( 'editor' ).updateBlock( blockId, { url: url } );
			}
		}
	"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	{{-- Replace button --}}
	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		x-on:click="replaceImage()"
		aria-label="{{ __( 'visual-editor::ve.replace_image' ) }}"
		title="{{ __( 'visual-editor::ve.replace_image' ) }}"
	>
		<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
		</svg>
	</button>

	{{-- Link button --}}
	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		:class="hasLink ? 'bg-base-200 text-base-content' : ''"
		x-on:click="toggleLink()"
		:aria-label="hasLink ? {{ Js::from( __( 'visual-editor::ve.edit_link' ) ) }} : {{ Js::from( __( 'visual-editor::ve.add_link' ) ) }}"
		:title="hasLink ? {{ Js::from( __( 'visual-editor::ve.edit_link' ) ) }} : {{ Js::from( __( 'visual-editor::ve.add_link' ) ) }}"
	>
		<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
		</svg>
	</button>

	{{-- Link popover --}}
	<div
		x-show="linkOpen"
		x-on:click.outside="linkOpen = false"
		x-transition
		class="absolute left-0 top-full mt-1 w-64 rounded-lg border border-base-300 bg-base-100 shadow-lg p-3 z-50"
	>
		<div class="space-y-2">
			<div>
				<label class="text-xs font-medium text-base-content/80 block mb-1" for="image-link-url">
					{{ __( 'visual-editor::ve.url' ) }}
				</label>
				<input
					id="image-link-url"
					type="url"
					class="input input-bordered input-sm w-full"
					placeholder="{{ __( 'visual-editor::ve.url_placeholder' ) }}"
					x-model="linkUrl"
				/>
			</div>
			<div>
				<label class="text-xs font-medium text-base-content/80 block mb-1" for="image-link-target">
					{{ __( 'visual-editor::ve.link_target' ) }}
				</label>
				<select
					id="image-link-target"
					class="select select-bordered select-sm w-full"
					x-model="linkTarget"
				>
					<option value="_self">{{ __( 'visual-editor::ve.same_window' ) }}</option>
					<option value="_blank">{{ __( 'visual-editor::ve.new_window' ) }}</option>
				</select>
			</div>
			<div class="flex gap-2">
				<button
					type="button"
					class="btn btn-sm btn-primary flex-1"
					x-on:click="applyLink()"
				>
					{{ __( 'visual-editor::ve.apply' ) }}
				</button>
				<template x-if="hasLink">
					<button
						type="button"
						class="btn btn-sm btn-ghost text-error flex-1"
						x-on:click="removeLink()"
					>
						{{ __( 'visual-editor::ve.remove_link' ) }}
					</button>
				</template>
				<button
					type="button"
					class="btn btn-sm btn-ghost flex-1"
					x-on:click="linkOpen = false"
				>
					{{ __( 'visual-editor::ve.cancel' ) }}
				</button>
			</div>
		</div>
	</div>
</div>
