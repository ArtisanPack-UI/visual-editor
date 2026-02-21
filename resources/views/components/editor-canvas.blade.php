{{--
 * Editor Canvas Component
 *
 * The central rendering area where blocks are displayed and edited.
 * Supports click-to-select, drag-and-drop reordering, arrow key
 * navigation, and insertion points between blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		focusedIndex: -1,

		get blocks() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).blocks : [];
		},

		get isEmpty() {
			return 0 === this.blocks.length;
		},

		@if ( $enableArrowNavigation )
			handleKeydown( event ) {
				if ( this.isEmpty ) return;

				const blockEls = [ ...this.$refs.blockList.querySelectorAll( '[data-block-id]' ) ];
				if ( 0 === blockEls.length ) return;

				if ( 'ArrowDown' === event.key || 'ArrowRight' === event.key ) {
					event.preventDefault();
					this.focusedIndex = Math.min( this.focusedIndex + 1, blockEls.length - 1 );
					this._focusBlock( blockEls[ this.focusedIndex ] );
				} else if ( 'ArrowUp' === event.key || 'ArrowLeft' === event.key ) {
					event.preventDefault();
					this.focusedIndex = Math.max( this.focusedIndex - 1, 0 );
					this._focusBlock( blockEls[ this.focusedIndex ] );
				} else if ( 'Escape' === event.key ) {
					event.preventDefault();
					this.focusedIndex = -1;
					if ( Alpine.store( 'selection' ) ) {
						Alpine.store( 'selection' ).clearSelection();
					}
				}
			},

			_focusBlock( el ) {
				if ( ! el ) return;
				el.focus();
				const blockId = el.getAttribute( 'data-block-id' );
				if ( blockId && Alpine.store( 'selection' ) ) {
					Alpine.store( 'selection' ).select( blockId, false );
				}

				if ( Alpine.store( 'announcer' ) ) {
					const index = this.focusedIndex + 1;
					const total = this.blocks.length;
					Alpine.store( 'announcer' ).announce(
						{{ Js::from( __( 'visual-editor::ve.block_position', [ 'position' => '__POS__', 'total' => '__TOTAL__' ] ) ) }}
							.replaceAll( '__POS__', index )
							.replaceAll( '__TOTAL__', total )
					);
				}
			},
		@endif

		handleBlockClick( event ) {
			const blockEl = event.target.closest( '[data-block-id]' );
			if ( ! blockEl ) return;

			const blockId = blockEl.getAttribute( 'data-block-id' );
			if ( blockId && Alpine.store( 'selection' ) ) {
				Alpine.store( 'selection' ).select( blockId, event.metaKey || event.ctrlKey );
			}
		},

		@if ( $enableDragReorder )
			handleDragStart( event ) {
				const blockEl = event.target.closest( '[data-block-id]' );
				if ( ! blockEl ) return;

				const blockId = blockEl.getAttribute( 'data-block-id' );
				if ( ! blockId || ! Alpine.store( 'editor' ) ) return;

				const block = Alpine.store( 'editor' ).getBlock( blockId );
				if ( block ) {
					event.dataTransfer.setData( 'application/ve-block', JSON.stringify( block ) );
					event.dataTransfer.setData( 've-block-reorder', blockId );
					event.dataTransfer.effectAllowed = 'move';
				}
			},

			handleDrop( event ) {
				const reorderId = event.dataTransfer.getData( 've-block-reorder' );
				if ( ! reorderId || ! Alpine.store( 'editor' ) ) return;

				event.preventDefault();
				const blockEl = event.target.closest( '[data-block-id]' );
				if ( ! blockEl ) return;

				const targetId    = blockEl.getAttribute( 'data-block-id' );
				const targetIndex = Alpine.store( 'editor' ).getBlockIndex( targetId );
				if ( -1 !== targetIndex ) {
					Alpine.store( 'editor' ).moveBlock( reorderId, targetIndex );
				}
			},
		@endif
	}"
	{{ $attributes->merge( [ 'class' => 'relative min-h-[200px] p-4' ] ) }}
	role="main"
	aria-label="{{ $label ?? __( 'visual-editor::ve.editor_canvas' ) }}"
	@if ( $enableArrowNavigation )
		x-on:keydown="handleKeydown( $event )"
	@endif
	x-on:click="handleBlockClick( $event )"
	tabindex="0"
>
	{{-- Block list container --}}
	<div
		x-ref="blockList"
		x-show="! isEmpty"
		@if ( $enableDragReorder )
			x-on:dragstart="handleDragStart( $event )"
			x-on:dragover.prevent
			x-on:drop="handleDrop( $event )"
		@endif
	>
		{{ $slot }}
	</div>

	{{-- Empty state --}}
	<template x-if="isEmpty">
		<x-ve-canvas-empty-state />
	</template>

	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.canvas.rendered')
	@endif
</div>
