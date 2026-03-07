{{--
 * Group Block Toolbar Controls
 *
 * Provides justification buttons (start, center, end, space-between)
 * and orientation toggle (horizontal/vertical) for the group block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Group\Views
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
		get flexDirection() { return this.block?.attributes?.flexDirection || 'column'; },
		get justifyContent() { return this.block?.attributes?.justifyContent || 'flex-start'; },
		get activeVariation() {
			return this.block?.attributes?._groupVariation || 'group';
		},
		get isRowOrStack() {
			return 'row' === this.activeVariation || 'stack' === this.activeVariation;
		},
		get isGroupVariation() {
			return 'group' === this.activeVariation;
		},
		setJustify( value ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, { justifyContent: value } );
		},
		setDirection( value ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId ) return;
			const attrs = { flexDirection: value };
			if ( this.isRowOrStack ) {
				attrs._groupVariation = 'row' === value ? 'row' : 'stack';
			}
			Alpine.store( 'editor' ).updateBlock( blockId, attrs );
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

	{{-- Justification buttons --}}
	<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.justify_content' ) }}">
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-start' ? 'bg-base-200' : ''" x-on:click="setJustify( 'flex-start' )" :aria-pressed="( justifyContent === 'flex-start' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_start' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<line x1="4" y1="4" x2="4" y2="20" />
				<rect x="8" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" />
				<rect x="14" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
			</svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'center' ? 'bg-base-200' : ''" x-on:click="setJustify( 'center' )" :aria-pressed="( justifyContent === 'center' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_center' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<line x1="12" y1="4" x2="12" y2="20" stroke-dasharray="2 2" />
				<rect x="5" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" />
				<rect x="15" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
			</svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-end' ? 'bg-base-200' : ''" x-on:click="setJustify( 'flex-end' )" :aria-pressed="( justifyContent === 'flex-end' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_end' ) }}">
			<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<line x1="20" y1="4" x2="20" y2="20" />
				<rect x="6" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
				<rect x="12" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" />
			</svg>
		</button>
		{{-- Space-between: only for Row/Stack --}}
		<template x-if="'row' === flexDirection || ( 'column' === flexDirection && block?.attributes?.flexWrap === 'nowrap' && block?.innerBlocks?.length > 0 )">
			<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'space-between' ? 'bg-base-200' : ''" x-on:click="setJustify( 'space-between' )" :aria-pressed="( justifyContent === 'space-between' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_space_between' ) }}">
				<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<line x1="4" y1="4" x2="4" y2="20" />
					<line x1="20" y1="4" x2="20" y2="20" />
					<rect x="7" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
					<rect x="13" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
				</svg>
			</button>
		</template>
	</div>

	{{-- Orientation toggle: only for Row/Stack (not default Group) --}}
	<template x-if="block?.innerBlocks?.length > 0">
		<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.orientation' ) }}">
			<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>
			<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'row' === flexDirection ? 'bg-base-200' : ''" x-on:click="setDirection( 'row' )" title="{{ __( 'visual-editor::ve.orientation_horizontal' ) }}" aria-label="{{ __( 'visual-editor::ve.orientation_horizontal' ) }}" :aria-pressed="( 'row' === flexDirection ).toString()">
				<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
				</svg>
			</button>
			<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'column' === flexDirection ? 'bg-base-200' : ''" x-on:click="setDirection( 'column' )" title="{{ __( 'visual-editor::ve.orientation_vertical' ) }}" aria-label="{{ __( 'visual-editor::ve.orientation_vertical' ) }}" :aria-pressed="( 'column' === flexDirection ).toString()">
				<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
				</svg>
			</button>
		</div>
	</template>
</div>
