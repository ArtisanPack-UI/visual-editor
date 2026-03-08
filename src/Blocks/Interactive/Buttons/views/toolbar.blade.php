{{--
 * Buttons Block Toolbar Controls
 *
 * Provides justification buttons (left, center, right, space-between)
 * and an add-button control for the buttons container block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Buttons\Views
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
		get justification() { return this.block?.attributes?.justification || 'left'; },
		setJustification( value ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, { justification: value } );
		},
		addButton() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).addInnerBlock( blockId, { type: 'button' } );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.justify_content' ) }}">
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justification === 'left' ? 'bg-base-200' : ''" x-on:click="setJustification( 'left' )" title="{{ __( 'visual-editor::ve.justify_start' ) }}" aria-label="{{ __( 'visual-editor::ve.justify_start' ) }}" :aria-pressed="( justification === 'left' ).toString()">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 9v6h11V9H9zM4 20h1.5V4H4v16z"/></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justification === 'center' ? 'bg-base-200' : ''" x-on:click="setJustification( 'center' )" title="{{ __( 'visual-editor::ve.justify_center' ) }}" aria-label="{{ __( 'visual-editor::ve.justify_center' ) }}" :aria-pressed="( justification === 'center' ).toString()">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 9h-7.2V4h-1.6v5H4v6h7.2v5h1.6v-5H20z"/></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justification === 'right' ? 'bg-base-200' : ''" x-on:click="setJustification( 'right' )" title="{{ __( 'visual-editor::ve.justify_end' ) }}" aria-label="{{ __( 'visual-editor::ve.justify_end' ) }}" :aria-pressed="( justification === 'right' ).toString()">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 15h11V9H4v6zM18.5 4v16H20V4h-1.5z"/></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justification === 'space-between' ? 'bg-base-200' : ''" x-on:click="setJustification( 'space-between' )" title="{{ __( 'visual-editor::ve.justify_space_between' ) }}" aria-label="{{ __( 'visual-editor::ve.justify_space_between' ) }}" :aria-pressed="( justification === 'space-between' ).toString()">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 15h6V9H9v6zM4 20h1.5V4H4v16zM18.5 4v16H20V4h-1.5z"/></svg>
		</button>
	</div>

	<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button type="button" class="btn btn-ghost btn-xs btn-square" x-on:click="addButton()" title="{{ __( 'visual-editor::ve.add_block' ) }}" aria-label="{{ __( 'visual-editor::ve.add_block' ) }}">
		<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11V12.5Z"/></svg>
	</button>
</div>
