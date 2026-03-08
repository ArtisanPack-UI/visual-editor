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
		slashCommandOpen: false,

		get blocks() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).blocks : [];
		},

		get isEmpty() {
			return 0 === this.blocks.length;
		},

		@if ( $enableArrowNavigation )
			/**
			 * Walk up the DOM from the given element to find a contenteditable ancestor
			 * that is still inside this canvas.
			 */
			_getEditableAncestor( el ) {
				while ( el && el !== this.$el ) {
					if ( el.isContentEditable ) return el;
					el = el.parentElement;
				}
				return null;
			},

			/**
			 * Check whether the collapsed cursor is at the very start of the editable's text.
			 */
			_isCursorAtContentStart( el ) {
				const sel = window.getSelection();
				if ( ! sel.rangeCount ) return false;
				const range = sel.getRangeAt( 0 );
				if ( ! range.collapsed ) return false;
				const pre = document.createRange();
				pre.selectNodeContents( el );
				pre.setEnd( range.startContainer, range.startOffset );
				return 0 === pre.toString().length;
			},

			/**
			 * Check whether the collapsed cursor is at the very end of the editable's text.
			 */
			_isCursorAtContentEnd( el ) {
				const sel = window.getSelection();
				if ( ! sel.rangeCount ) return false;
				const range = sel.getRangeAt( 0 );
				if ( ! range.collapsed ) return false;
				const post = document.createRange();
				post.selectNodeContents( el );
				post.setStart( range.endContainer, range.endOffset );
				return 0 === post.toString().length;
			},

			/**
			 * Check whether the cursor sits on the first visual line of the editable
			 * by comparing the cursor rect's top with the element's first character rect.
			 */
			_isCursorOnFirstLine( el ) {
				if ( this._isCursorAtContentStart( el ) ) return true;
				const sel = window.getSelection();
				if ( ! sel.rangeCount ) return true;
				const cursorRange = sel.getRangeAt( 0 ).cloneRange();
				cursorRange.collapse( true );
				const cursorRect = cursorRange.getBoundingClientRect();
				if ( ! cursorRect.height ) return true;

				const startRange = document.createRange();
				startRange.selectNodeContents( el );
				startRange.collapse( true );
				const startRect = startRange.getBoundingClientRect();
				if ( ! startRect.height ) return true;

				return Math.abs( cursorRect.top - startRect.top ) < 2;
			},

			/**
			 * Check whether the cursor sits on the last visual line of the editable
			 * by comparing the cursor rect's bottom with the element's last character rect.
			 */
			_isCursorOnLastLine( el ) {
				if ( this._isCursorAtContentEnd( el ) ) return true;
				const sel = window.getSelection();
				if ( ! sel.rangeCount ) return true;
				const cursorRange = sel.getRangeAt( 0 ).cloneRange();
				cursorRange.collapse( false );
				const cursorRect = cursorRange.getBoundingClientRect();
				if ( ! cursorRect.height ) return true;

				const endRange = document.createRange();
				endRange.selectNodeContents( el );
				endRange.collapse( false );
				const endRect = endRange.getBoundingClientRect();
				if ( ! endRect.height ) return true;

				return Math.abs( cursorRect.bottom - endRect.bottom ) < 2;
			},

			/**
			 * Get the current cursor's X coordinate from its bounding rect.
			 */
			_getCursorX() {
				const sel = window.getSelection();
				if ( ! sel.rangeCount ) return null;
				const range = sel.getRangeAt( 0 ).cloneRange();
				range.collapse( true );
				const rect = range.getBoundingClientRect();
				if ( rect.height ) return rect.x;

				// The caret rect can be zero immediately after a cross-block jump
				// because the browser hasn't painted yet. Walk up to the nearest
				// contenteditable and use its left edge as a reasonable fallback.
				let node = range.startContainer;
				while ( node ) {
					if ( 1 === node.nodeType && node.isContentEditable ) {
						return node.getBoundingClientRect().left;
					}
					node = node.parentElement;
				}
				return null;
			},

			handleKeydown( event ) {
				if ( this.isEmpty ) return;

				const activeEl = document.activeElement;

				// For standard form controls, let the browser handle everything.
				if ( activeEl ) {
					const tag = activeEl.tagName.toLowerCase();
					if ( 'input' === tag || 'textarea' === tag || 'select' === tag ) {
						return;
					}
				}

				const blockEls = [ ...this.$refs.blockList.querySelectorAll( '[data-block-id]' ) ];
				if ( 0 === blockEls.length ) return;

				const editableEl = this._getEditableAncestor( activeEl );

				// ── Inside a contenteditable: boundary-aware navigation ──
				if ( editableEl ) {
					const blockEl      = editableEl.closest( '[data-block-id]' );
					const currentIndex = blockEl ? blockEls.indexOf( blockEl ) : -1;

					if ( 'ArrowUp' === event.key && this._isCursorOnFirstLine( editableEl ) ) {
						if ( currentIndex > 0 ) {
							const savedX = this._getCursorX();
							event.preventDefault();
							this.focusedIndex = currentIndex - 1;
							this._focusBlockEditable( blockEls[ this.focusedIndex ], 'end', savedX );
						}
						return;
					}

					if ( 'ArrowDown' === event.key && this._isCursorOnLastLine( editableEl ) ) {
						if ( currentIndex < blockEls.length - 1 ) {
							const savedX = this._getCursorX();
							event.preventDefault();
							this.focusedIndex = currentIndex + 1;
							this._focusBlockEditable( blockEls[ this.focusedIndex ], 'start', savedX );
						}
						return;
					}

					if ( 'ArrowLeft' === event.key && this._isCursorAtContentStart( editableEl ) ) {
						if ( currentIndex > 0 ) {
							event.preventDefault();
							this.focusedIndex = currentIndex - 1;
							this._focusBlockEditable( blockEls[ this.focusedIndex ], 'end' );
						}
						return;
					}

					if ( 'ArrowRight' === event.key && this._isCursorAtContentEnd( editableEl ) ) {
						if ( currentIndex < blockEls.length - 1 ) {
							event.preventDefault();
							this.focusedIndex = currentIndex + 1;
							this._focusBlockEditable( blockEls[ this.focusedIndex ], 'start' );
						}
						return;
					}

					if ( 'Escape' === event.key ) {
						event.preventDefault();
						editableEl.blur();
						if ( blockEl ) blockEl.focus();
						return;
					}

					// Not at a boundary — let the browser move the cursor normally.
					return;
				}

				// ── Not inside editable content: block-level navigation ──
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

			/**
			 * Focus a block wrapper element (block-level navigation mode).
			 */
			_focusBlock( el ) {
				if ( ! el ) return;
				el.focus();
				this._selectAndAnnounce( el );
			},

			/**
			 * Focus the contenteditable inside a block and place the cursor.
			 *
			 * When savedX is provided (ArrowUp / ArrowDown), the cursor is placed
			 * at the character position on the target line closest to that X
			 * coordinate — matching Google Docs behaviour. Falls back to
			 * start / end placement when the hit-test is unavailable.
			 */
			_focusBlockEditable( blockEl, position, savedX ) {
				if ( ! blockEl ) return;
				const editable = blockEl.querySelector( '[contenteditable]' );
				if ( ! editable ) {
					blockEl.focus();
					this._selectAndAnnounce( blockEl );
					return;
				}

				editable.focus();
				const sel = window.getSelection();

				// Place cursor at the target line edge (start or end of content).
				const placementRange = document.createRange();
				if ( editable.childNodes.length ) {
					placementRange.selectNodeContents( editable );
				} else {
					placementRange.setStart( editable, 0 );
					placementRange.setEnd( editable, 0 );
				}
				placementRange.collapse( 'start' === position );
				sel.removeAllRanges();
				sel.addRange( placementRange );

				// If we have a saved X, try to hit-test the closest character.
				if ( null !== savedX && undefined !== savedX && document.caretRangeFromPoint ) {
					const elRect = editable.getBoundingClientRect();

					// Clamp X inside the editable's content area so the hit-test
					// can't land on padding, margins or a sibling element.
					const clampedX = Math.max( elRect.left + 1, Math.min( savedX, elRect.right - 1 ) );

					// Determine the Y midpoint of the target line. The caret rect
					// is usually zero right after focus(), so fall back to the
					// element's edge.
					let targetY;
					const caretRect = sel.getRangeAt( 0 ).getBoundingClientRect();
					if ( caretRect.height ) {
						targetY = caretRect.top + ( caretRect.height / 2 );
					} else if ( 'start' === position ) {
						targetY = elRect.top + Math.min( 10, elRect.height / 2 );
					} else {
						targetY = elRect.bottom - Math.min( 10, elRect.height / 2 );
					}

					const hitRange = document.caretRangeFromPoint( clampedX, targetY );
					if ( hitRange && editable.contains( hitRange.startContainer ) ) {
						sel.removeAllRanges();
						sel.addRange( hitRange );
					}
				}

				this._selectAndAnnounce( blockEl );
			},

			/**
			 * Select a block in the store and announce its position.
			 */
			_selectAndAnnounce( el ) {
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
	{{ $attributes->merge( [ 'class' => 'relative min-h-[200px] p-4', 'style' => '--ve-canvas-padding: 1rem;' ] ) }}
	role="region"
	aria-label="{{ $label ?? __( 'visual-editor::ve.editor_canvas' ) }}"
	@if ( $enableArrowNavigation )
		x-on:keydown="handleKeydown( $event )"
	@endif
	x-on:click="handleBlockClick( $event )"
	x-on:input="
		const target = $event.target;
		if ( target.hasAttribute( 'data-ve-slash-command' ) ) {
			const text = target.textContent || '';
			if ( text.startsWith( '/' ) ) {
				slashCommandOpen = true;
				const blockEl = target.closest( '[data-block-id]' );
				const rect    = target.getBoundingClientRect();
				document.dispatchEvent( new CustomEvent( 've-slash-command-open', {
					bubbles: true,
					detail: {
						blockId: blockEl ? blockEl.getAttribute( 'data-block-id' ) : null,
						query: text.substring( 1 ),
						rect: { top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right },
					},
				} ) );
			} else {
				slashCommandOpen = false;
				document.dispatchEvent( new CustomEvent( 've-slash-command-close', { bubbles: true } ) );
			}
		}
	"
	x-on:ve-slash-command-select.window="
		slashCommandOpen = false;
		if ( $event.detail && $event.detail.blockId && $event.detail.blockType && Alpine.store( 'editor' ) ) {
			const newBlock = Alpine.store( 'editor' ).replaceBlock( $event.detail.blockId, { type: $event.detail.blockType } );
			if ( newBlock ) {
				$nextTick( () => {
					const el = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
					if ( el ) { el.focus(); }
					if ( Alpine.store( 'selection' ) ) {
						Alpine.store( 'selection' ).select( newBlock.id, false );
					}
				} );
			}
		}
	"
	x-on:ve-slash-command-close.window="slashCommandOpen = false"
	x-on:keydown.enter="
		if ( ! slashCommandOpen && ! $event.shiftKey && $event.target.hasAttribute( 'data-ve-enter-new-block' ) ) {
			$event.preventDefault();
			const innerBlocksContainer = $event.target.closest( '[data-ve-inner-blocks]' );
			const blockEl = $event.target.closest( '[data-block-id]' );
			if ( ! blockEl || ! Alpine.store( 'editor' ) ) return;

			const blockId = blockEl.getAttribute( 'data-block-id' );

			if ( innerBlocksContainer ) {
				const parentId = innerBlocksContainer.getAttribute( 'data-parent-id' ) || blockId;
				const innerBlockEl = $event.target.closest( '[data-inner-block-id]' );

				// Sync current inner block content to store before structural
				// change so the history snapshot includes the latest text.
				if ( innerBlockEl ) {
					const curId      = innerBlockEl.getAttribute( 'data-inner-block-id' );
					const curBlock   = Alpine.store( 'editor' ).getBlock( curId );
					const contentEl  = innerBlockEl.querySelector( '[contenteditable]' ) || innerBlockEl;
					if ( curBlock ) {
						curBlock.attributes = { ...curBlock.attributes, text: contentEl.innerHTML };
					}
				}

				let newBlock;
				if ( innerBlockEl ) {
					const innerBlockId = innerBlockEl.getAttribute( 'data-inner-block-id' );
					newBlock = Alpine.store( 'editor' ).addInnerBlockAfter( innerBlockId );
				} else {
					newBlock = Alpine.store( 'editor' ).addInnerBlock( parentId, { type: Alpine.store( 'editor' ).defaultBlockType } );
				}

				if ( newBlock ) {
					$nextTick( () => {
						const wrapper = document.querySelector( '[data-inner-block-id=\'' + newBlock.id + '\']' );
						if ( wrapper ) {
							const editable = wrapper.querySelector( '[contenteditable]' ) || wrapper;
							editable.focus();
						}
					} );
				}
			} else {
				const newBlock = Alpine.store( 'editor' ).addBlockAfter( blockId );
				if ( newBlock ) {
					$nextTick( () => {
						const newEl = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
						if ( newEl ) { newEl.focus(); }
						if ( Alpine.store( 'selection' ) ) {
							Alpine.store( 'selection' ).select( newBlock.id, false );
						}
					} );
				}
			}
		}
	"
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
