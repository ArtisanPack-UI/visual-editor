{{--
 * Tabs Block Toolbar Controls
 *
 * Provides tab position toggle and add-tab control
 * for the Tabs container block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Tabs\Views
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
		get tabPosition() { return this.block?.attributes?.tabPosition || 'top'; },
		setPosition( value ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId && Alpine.store( 'editor' ) ) Alpine.store( 'editor' ).updateBlock( blockId, { tabPosition: value } );
		},
		addTab() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId && Alpine.store( 'editor' ) ) Alpine.store( 'editor' ).addInnerBlock( blockId, { type: 'tab-panel', attributes: { label: '' } } );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.tabs_position' ) }}">
		<button type="button" class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors" :class="tabPosition === 'top' ? 'bg-base-200' : ''" x-on:click="setPosition( 'top' )" title="{{ __( 'visual-editor::ve.top' ) }}" aria-label="{{ __( 'visual-editor::ve.top' ) }}" :aria-pressed="( tabPosition === 'top' ).toString()">
			<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
		</button>
		<button type="button" class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors" :class="tabPosition === 'left' ? 'bg-base-200' : ''" x-on:click="setPosition( 'left' )" title="{{ __( 'visual-editor::ve.left' ) }}" aria-label="{{ __( 'visual-editor::ve.left' ) }}" :aria-pressed="( tabPosition === 'left' ).toString()">
			<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
		</button>
	</div>

	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button type="button" class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors" x-on:click="addTab()" title="{{ __( 'visual-editor::ve.tabs_add_tab' ) }}" aria-label="{{ __( 'visual-editor::ve.tabs_add_tab' ) }}">
		<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11V12.5Z"/></svg>
	</button>
</div>
