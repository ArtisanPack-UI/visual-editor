{{--
 * Block Toolbar Component
 *
 * A floating toolbar that appears above the currently selected block,
 * with block-specific controls and common actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		visible: false,
		anchorEl: null,
		toolbarStyle: { position: 'absolute', top: '0', left: '0' },

		get focusedBlockId() {
			return Alpine.store( 'selection' ) ? Alpine.store( 'selection' ).focused : null;
		},

		init() {
			this.$watch(
				() => this.focusedBlockId,
				( blockId ) => {
					if ( blockId ) {
						this.$nextTick( () => this._anchorToBlock( blockId ) );
					} else {
						this.visible = false;
						this._removePositionListeners();
					}
				}
			);

			// Re-position when sidebars open/close so the toolbar tracks the block.
			this.$watch(
				() => Alpine.store( 'editor' ) ? [ Alpine.store( 'editor' ).showInserter, Alpine.store( 'editor' ).showSidebar ] : [],
				() => {
					if ( this.visible && this.anchorEl ) {
						// Delay to allow the sidebar transition and flex reflow to settle.
						setTimeout( () => {
							if ( this.visible && this.anchorEl && this.$refs.toolbar ) {
								this._position();
							}
						}, 220 );
					}
				}
			);
		},

		_anchorToBlock( blockId ) {
			const el = document.querySelector( '[data-block-id=' + blockId + ']' );
			if ( ! el ) {
				this.visible = false;
				this._removePositionListeners();
				return;
			}

			this.anchorEl = el;
			this.visible  = true;
			this._position();
			this._addPositionListeners();

			// Re-position after the enter transition completes so the toolbar
			// dimensions are final and it doesn't overlap the block.
			setTimeout( () => {
				if ( this.visible && this.anchorEl && this.$refs.toolbar ) {
					this._position();
				}
			}, 160 );
		},

		_positionHandler: null,
		_resizeObserver: null,

		_addPositionListeners() {
			this._removePositionListeners();
			let rafId = null;
			this._positionHandler = () => {
				if ( rafId ) return;
				rafId = requestAnimationFrame( () => {
					rafId = null;
					if ( this.visible && this.anchorEl && this.$refs.toolbar ) {
						this._position();
					}
				} );
			};
			window.addEventListener( 'scroll', this._positionHandler, true );
			window.addEventListener( 'resize', this._positionHandler );

			// Watch for layout changes (e.g. sidebar open/close) via ResizeObserver.
			// The offset parent (canvas with position:relative) sits inside a flex
			// wrapper that actually changes size when a sidebar toggles, so observe
			// both the offset parent and its parent to catch all reflows.
			this._resizeObserver = new ResizeObserver( this._positionHandler );
			if ( this.$el.offsetParent ) {
				this._resizeObserver.observe( this.$el.offsetParent );
				if ( this.$el.offsetParent.parentElement ) {
					this._resizeObserver.observe( this.$el.offsetParent.parentElement );
				}
			}
		},

		_removePositionListeners() {
			if ( this._positionHandler ) {
				window.removeEventListener( 'scroll', this._positionHandler, true );
				window.removeEventListener( 'resize', this._positionHandler );
				this._positionHandler = null;
			}
			if ( this._resizeObserver ) {
				this._resizeObserver.disconnect();
				this._resizeObserver = null;
			}
		},

		_position() {
			if ( ! this.anchorEl || ! this.$refs.toolbar ) return;

			const blockRect   = this.anchorEl.getBoundingClientRect();
			const toolbarRect = this.$refs.toolbar.getBoundingClientRect();
			const parentRect  = this.$el.offsetParent
				? this.$el.offsetParent.getBoundingClientRect()
				: { top: 0, left: 0 };

			let top  = blockRect.top - parentRect.top - toolbarRect.height - 4;
			let left = blockRect.left - parentRect.left;

			@if ( 'bottom' === $placement )
				top = blockRect.bottom - parentRect.top + 4;
			@endif

			{{-- Keep in viewport --}}
			if ( top < 0 ) {
				top = blockRect.bottom - parentRect.top + 4;
			}

			this.toolbarStyle = {
				position: 'absolute',
				top: top + 'px',
				left: left + 'px',
				zIndex: 40,
			};
		},
	}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<div
		x-ref="toolbar"
		x-show="visible"
		x-transition:enter="transition ease-out duration-150"
		x-transition:enter-start="opacity-0 scale-95"
		x-transition:enter-end="opacity-100 scale-100"
		x-transition:leave="transition ease-in duration-100"
		x-transition:leave-start="opacity-100 scale-100"
		x-transition:leave-end="opacity-0 scale-95"
		:style="toolbarStyle"
		class="flex items-center gap-0.5 px-1.5 py-1 rounded-lg border border-base-300 bg-base-100 shadow-md"
		role="toolbar"
		aria-label="{{ $label ?? __( 'visual-editor::ve.block_toolbar' ) }}"
		aria-orientation="horizontal"
	>
		{{-- Block type indicator with transform dropdown --}}
		@if ( $blockType )
			<div x-data="{ transformOpen: false }" class="relative flex items-center">
				<button
					type="button"
					class="text-xs font-medium text-base-content/60 px-1.5 hover:text-base-content transition-colors flex items-center gap-1"
					x-on:click="transformOpen = ! transformOpen"
					:aria-expanded="transformOpen"
					aria-label="{{ __( 'visual-editor::ve.transform_block' ) }}"
				>
					{{ $blockType }}
					<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
					</svg>
				</button>

				<div
					x-show="transformOpen"
					x-on:click.outside="transformOpen = false"
					x-transition
					class="absolute left-0 top-full mt-1 w-48 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50"
					role="menu"
					aria-label="{{ __( 'visual-editor::ve.transform_to' ) }}"
				>
					<template x-if="Alpine.store( 'editor' ) && focusedBlockId">
						<template x-for="targetType in Alpine.store( 'editor' ).getTransformsForBlock( Alpine.store( 'editor' ).getBlock( focusedBlockId )?.type || '' )" :key="targetType">
							<button
								type="button"
								class="w-full text-left px-3 py-1.5 text-sm hover:bg-base-200"
								x-on:click="
									Alpine.store( 'editor' ).transformBlock( focusedBlockId, targetType );
									transformOpen = false;
								"
								role="menuitem"
								x-text="targetType"
							></button>
						</template>
					</template>
				</div>
			</div>
			<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>
		@endif

		{{-- Block-specific controls slot --}}
		{{ $slot }}

		{{-- Move controls --}}
		@if ( $showMoveControls )
			<div class="flex items-center gap-0.5">
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square"
					x-on:click="if ( Alpine.store( 'editor' ) && focusedBlockId ) { Alpine.store( 'editor' ).moveBlockUp( focusedBlockId ); }"
					aria-label="{{ __( 'visual-editor::ve.move_up' ) }}"
				>
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
					</svg>
				</button>
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square"
					x-on:click="if ( Alpine.store( 'editor' ) && focusedBlockId ) { Alpine.store( 'editor' ).moveBlockDown( focusedBlockId ); }"
					aria-label="{{ __( 'visual-editor::ve.move_down' ) }}"
				>
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
					</svg>
				</button>
			</div>
		@endif

		{{-- More options dropdown --}}
		@if ( $showMoreOptions )
			<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>
			<div x-data="{ moreOpen: false }" class="relative">
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square"
					x-on:click="moreOpen = ! moreOpen"
					aria-label="{{ __( 'visual-editor::ve.more_options' ) }}"
					:aria-expanded="moreOpen"
				>
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
					</svg>
				</button>

				<div
					x-show="moreOpen"
					x-on:click.outside="moreOpen = false"
					x-transition
					class="absolute right-0 top-full mt-1 w-48 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50"
					role="menu"
				>
					<button
						type="button"
						class="w-full text-left px-3 py-1.5 text-sm hover:bg-base-200"
						x-on:click="
							if ( Alpine.store( 'editor' ) && focusedBlockId ) {
								Alpine.store( 'editor' ).duplicateBlock( focusedBlockId );
							}
							moreOpen = false;
						"
						role="menuitem"
					>
						{{ __( 'visual-editor::ve.duplicate_block' ) }}
					</button>
					<button
						type="button"
						class="w-full text-left px-3 py-1.5 text-sm text-error hover:bg-base-200"
						x-on:click="
							if ( Alpine.store( 'editor' ) && focusedBlockId ) {
								Alpine.store( 'editor' ).removeBlock( focusedBlockId );
							}
							moreOpen = false;
						"
						role="menuitem"
					>
						{{ __( 'visual-editor::ve.remove_block' ) }}
					</button>
				</div>
			</div>
		@endif
	</div>
</div>
