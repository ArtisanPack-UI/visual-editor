{{--
 * Accordion Block Toolbar Controls
 *
 * Provides add-section and toggle-all controls
 * for the Accordion container block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Accordion\Views
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
		addSection() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId && Alpine.store( 'editor' ) ) Alpine.store( 'editor' ).addInnerBlock( blockId, { type: 'accordion-section', attributes: { title: '' } } );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button type="button" class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors" x-on:click="addSection()" title="{{ __( 'visual-editor::ve.accordion_add_section' ) }}" aria-label="{{ __( 'visual-editor::ve.accordion_add_section' ) }}">
		<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11V12.5Z"/></svg>
	</button>
</div>
