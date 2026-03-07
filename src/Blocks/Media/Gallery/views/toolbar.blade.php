{{--
 * Gallery Block Toolbar Controls
 *
 * Provides an Add button to open the media picker for adding
 * new image inner blocks to the gallery.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Gallery\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		addImages() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId ) return;
			Livewire.dispatch( 'open-ve-media-picker', { context: blockId + ':gallery-add' } );
		},
	}"
	x-on:ve-media-selected.window="
		const blockId = Alpine.store( 'selection' )?.focused;
		if ( blockId && $event.detail.context === blockId + ':gallery-add' && $event.detail.media?.length ) {
			const store = Alpine.store( 'editor' );
			if ( ! store ) return;
			$event.detail.media.forEach( ( m ) => {
				store.addInnerBlock( blockId, {
					type: 'image',
					attributes: {
						url: m.url ?? m.path ?? '',
						alt: m.alt ?? '',
					},
				} );
			} );
		}
	"
	class="relative flex items-center"
>
	<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

	{{-- Add images button --}}
	<button
		type="button"
		class="btn btn-ghost btn-xs gap-1 px-1.5"
		x-on:click="addImages()"
		aria-label="{{ __( 'visual-editor::ve.gallery_add_images' ) }}"
		title="{{ __( 'visual-editor::ve.gallery_add_images' ) }}"
	>
		<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
		</svg>
		<span class="text-xs">{{ __( 'visual-editor::ve.add_images' ) }}</span>
	</button>
</div>
