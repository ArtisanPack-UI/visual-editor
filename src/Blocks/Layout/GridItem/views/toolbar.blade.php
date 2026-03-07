{{--
 * Grid Item Block Toolbar Controls
 *
 * Provides vertical alignment buttons (start, center, end, stretch)
 * for individual grid item blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\GridItem\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		get block() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
			return Alpine.store( 'editor' ).getBlock( blockId );
		},
		get vAlign() { return this.block?.attributes?.verticalAlignment || 'stretch'; },
		updateAttr( attrs ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.vertical_alignment' ) }}">
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'start' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'start' } )" :aria-pressed="( vAlign === 'start' ).toString()" title="{{ __( 'visual-editor::ve.top' ) }}" aria-label="{{ __( 'visual-editor::ve.top' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><rect x="8" y="7" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'center' } )" :aria-pressed="( vAlign === 'center' ).toString()" title="{{ __( 'visual-editor::ve.center' ) }}" aria-label="{{ __( 'visual-editor::ve.center' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="12" x2="20" y2="12" stroke-dasharray="2 2" /><rect x="8" y="10" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'end' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'end' } )" :aria-pressed="( vAlign === 'end' ).toString()" title="{{ __( 'visual-editor::ve.bottom' ) }}" aria-label="{{ __( 'visual-editor::ve.bottom' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="13" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'stretch' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'stretch' } )" :aria-pressed="( vAlign === 'stretch' ).toString()" title="{{ __( 'visual-editor::ve.stretch' ) }}" aria-label="{{ __( 'visual-editor::ve.stretch' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="7" width="8" height="10" rx="1" fill="currentColor" stroke="none" /></svg>
		</button>
	</div>
</div>
