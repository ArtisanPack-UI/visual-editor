{{--
 * Button Block Toolbar Controls
 *
 * Provides a Link toolbar control for the button block.
 * Opens a WordPress-style link popover to set URL, target, nofollow, and sponsored.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Button\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		linkPopoverOpen: false,
		linkPopoverUrl: '',
		linkPopoverNewTab: false,
		linkPopoverNofollow: false,
		linkPopoverSponsored: false,
		_lastBlockId: null,

		init() {
			this.$watch( () => Alpine.store( 'selection' )?.focused, ( id ) => {
				if ( id !== this._lastBlockId ) {
					this.linkPopoverOpen = false;
					this._lastBlockId    = id;
				}
			} );
		},

		get block() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
			return Alpine.store( 'editor' ).getBlock( blockId );
		},

		get hasLink() {
			return !! ( this.block?.attributes?.url );
		},

		toggleLink() {
			if ( this.linkPopoverOpen ) {
				this.linkPopoverOpen = false;
				return;
			}
			this.linkPopoverUrl       = this.block?.attributes?.url || '';
			this.linkPopoverNewTab    = '_blank' === ( this.block?.attributes?.linkTarget || '_self' );
			this.linkPopoverNofollow  = !! this.block?.attributes?.nofollow;
			this.linkPopoverSponsored = !! this.block?.attributes?.sponsored;
			this.linkPopoverOpen      = true;
		},

		applyLink( detail ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
			Alpine.store( 'editor' ).updateBlock( blockId, {
				url:        detail.url,
				linkTarget: detail.newTab ? '_blank' : '_self',
				nofollow:   detail.nofollow,
				sponsored:  detail.sponsored,
			} );
			this.linkPopoverOpen = false;
		},
	}"
	x-on:ve-button-link-apply.stop="applyLink( $event.detail )"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

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

	<x-ve-link-popover event-name="ve-button-link-apply" />
</div>
