{{--
 * Cover Block Toolbar Controls
 *
 * Provides a 3x3 content position grid selector, full height toggle,
 * and media replace button for the cover block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Cover\Views
 *
 * @since      1.0.0
 --}}

@php
	$positions = [
		'top-left', 'top-center', 'top-right',
		'center-left', 'center', 'center-right',
		'bottom-left', 'bottom-center', 'bottom-right',
	];
@endphp

<div
	x-data="{
		positionOpen: false,
		get block() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
			return Alpine.store( 'editor' ).getBlock( blockId );
		},
		get contentAlignment() { return this.block?.attributes?.contentAlignment || 'center'; },
		get isFullHeight() {
			const mh = this.block?.attributes?.minHeight || '';
			const mu = this.block?.attributes?.minHeightUnit || 'px';
			return mh === '100vh' || ( 'vh' === mu && 100 === parseFloat( mh ) );
		},
		setPosition( value ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) {
				Alpine.store( 'editor' ).updateBlock( blockId, { contentAlignment: value } );
			}
			this.positionOpen = false;
		},
		togglePositionDropdown() {
			this.positionOpen = ! this.positionOpen;
		},
		toggleFullHeight() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId ) return;
			if ( this.isFullHeight ) {
				Alpine.store( 'editor' ).updateBlock( blockId, { minHeight: '430px', minHeightUnit: 'px' } );
			} else {
				Alpine.store( 'editor' ).updateBlock( blockId, { minHeight: '100vh', minHeightUnit: 'vh' } );
			}
		},
		openMediaReplace() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) {
				Livewire.dispatch( 'open-ve-media-picker', { context: blockId + ':cover-media' } );
			}
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	{{-- Content position 3x3 grid --}}
	<div class="relative">
		<button
			type="button"
			class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
			x-on:click.stop="togglePositionDropdown()"
			title="{{ __( 'visual-editor::ve.cover_content_position' ) }}"
			aria-label="{{ __( 'visual-editor::ve.cover_content_position' ) }}"
			:aria-expanded="positionOpen.toString()"
		>
			<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
			</svg>
		</button>

		<div
			x-show="positionOpen"
			x-cloak
			x-transition
			x-on:click.outside="positionOpen = false"
			x-on:keydown.escape.prevent="positionOpen = false"
			class="absolute top-full left-0 mt-1 z-50 bg-base-100 border border-base-300 rounded-lg shadow-lg p-2"
			style="min-width: 100px;"
		>
			<div class="grid grid-cols-3 gap-1.5" role="grid" aria-label="{{ __( 'visual-editor::ve.cover_content_position' ) }}">
				@foreach ( $positions as $pos )
					<button
						type="button"
						class="w-7 h-7 rounded flex items-center justify-center transition-colors"
						:class="contentAlignment === {{ Js::from( $pos ) }} ? 'bg-primary' : 'bg-base-300 hover:bg-base-content/30'"
						x-on:click.stop="setPosition( {{ Js::from( $pos ) }} )"
						title="{{ $pos }}"
						:aria-pressed="( contentAlignment === {{ Js::from( $pos ) }} ).toString()"
					>
						<span
							class="w-2 h-2 rounded-full"
							:class="contentAlignment === {{ Js::from( $pos ) }} ? 'bg-primary-content' : 'bg-base-content/40'"
						></span>
					</button>
				@endforeach
			</div>
		</div>
	</div>

	{{-- Full height (100vh) toggle --}}
	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		:class="isFullHeight ? 'bg-base-200' : ''"
		x-on:click="toggleFullHeight()"
		title="{{ __( 'visual-editor::ve.cover_full_height' ) }}"
		aria-label="{{ __( 'visual-editor::ve.cover_full_height' ) }}"
		:aria-pressed="isFullHeight.toString()"
	>
		<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5M20.25 16.5V18A2.25 2.25 0 0 1 18 20.25h-1.5M3.75 16.5V18A2.25 2.25 0 0 0 6 20.25h1.5M12 7.5v9m0-9l-2.25 2.25M12 7.5l2.25 2.25M12 16.5l-2.25-2.25M12 16.5l2.25-2.25" />
		</svg>
	</button>

	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	{{-- Replace media --}}
	<button
		type="button"
		class="flex items-center justify-center rounded px-2 py-1.5 text-xs font-medium text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors"
		x-on:click="openMediaReplace()"
		title="{{ __( 'visual-editor::ve.replace_media' ) }}"
		aria-label="{{ __( 'visual-editor::ve.replace_media' ) }}"
	>
		{{ __( 'visual-editor::ve.replace_media' ) }}
	</button>
</div>
