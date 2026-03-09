{{--
 * List Block Toolbar Controls
 *
 * Provides unordered/ordered list type toggle buttons.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\ListBlock\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		get currentListType() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'unordered';
			const block = Alpine.store( 'editor' ).getBlock( blockId );
			return block?.attributes?.type ?? 'unordered';
		},

		toggleListType() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
			const newType = 'ordered' === this.currentListType ? 'unordered' : 'ordered';
			Alpine.store( 'editor' ).updateBlock( blockId, { type: newType } );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		:class="currentListType === 'unordered' ? 'bg-base-200 text-base-content' : ''"
		x-on:click="if ( 'unordered' !== currentListType ) toggleListType()"
		aria-label="{{ __( 'Unordered list' ) }}"
		title="{{ __( 'Unordered list' ) }}"
	>
		<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
		</svg>
	</button>
	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		:class="currentListType === 'ordered' ? 'bg-base-200 text-base-content' : ''"
		x-on:click="if ( 'ordered' !== currentListType ) toggleListType()"
		aria-label="{{ __( 'Ordered list' ) }}"
		title="{{ __( 'Ordered list' ) }}"
	>
		<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
			<text x="2" y="8" font-size="7" font-family="system-ui, sans-serif" font-weight="600">1.</text>
			<line x1="10" y1="6" x2="22" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
			<text x="2" y="15" font-size="7" font-family="system-ui, sans-serif" font-weight="600">2.</text>
			<line x1="10" y1="13" x2="22" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
			<text x="2" y="22" font-size="7" font-family="system-ui, sans-serif" font-weight="600">3.</text>
			<line x1="10" y1="20" x2="22" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
		</svg>
	</button>
</div>
