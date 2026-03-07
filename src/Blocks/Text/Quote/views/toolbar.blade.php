{{--
 * Quote Block Toolbar Controls
 *
 * Provides a citation toggle button for the quote block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Quote\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		get showCitation() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return false;
			const block = Alpine.store( 'editor' ).getBlock( blockId );
			return block?.attributes?.showCitation ?? false;
		},

		toggleCitation() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
			const block = Alpine.store( 'editor' ).getBlock( blockId );
			const current = block?.attributes?.showCitation ?? false;
			Alpine.store( 'editor' ).updateBlock( blockId, { showCitation: ! current } );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button
		type="button"
		class="btn btn-ghost btn-xs btn-square"
		:class="showCitation ? 'bg-base-200 text-base-content' : ''"
		x-on:click="toggleCitation()"
		:aria-label="showCitation ? '{{ __( 'visual-editor::ve.remove_citation' ) }}' : '{{ __( 'visual-editor::ve.add_citation' ) }}'"
		:title="showCitation ? '{{ __( 'visual-editor::ve.remove_citation' ) }}' : '{{ __( 'visual-editor::ve.add_citation' ) }}'"
	>
		<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
		</svg>
	</button>
</div>
