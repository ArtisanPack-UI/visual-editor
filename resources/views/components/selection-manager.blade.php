{{--
 * Selection Manager Component
 *
 * Manages block selection state and clipboard operations.
 * Initializes an Alpine.js store for selection tracking.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data
	x-init="
		if ( ! Alpine.store( 'selection' ) ) {
			Alpine.store( 'selection', {
				selected: [],
				focused: null,
				clipboard: [],
				clipboardAction: null,
				multiSelect: {{ Js::from( $multiSelect ) }},
				selectionClass: {{ Js::from( $selectionClass ) }},
				multiSelectionClass: {{ Js::from( $multiSelectionClass ) }},
				cutClass: {{ Js::from( $cutClass ) }},

				select( blockId, addToSelection ) {
					if ( addToSelection && this.multiSelect ) {
						if ( ! this.selected.includes( blockId ) ) {
							this.selected.push( blockId );
						}
					} else {
						this.selected = [ blockId ];
					}
					this.focused = blockId;
					this._announce();
					this._dispatch();
				},

				deselect( blockId ) {
					this.selected = this.selected.filter( ( id ) => id !== blockId );
					if ( this.focused === blockId ) {
						this.focused = this.selected.length > 0 ? this.selected[ this.selected.length - 1 ] : null;
					}
					this._dispatch();
				},

				toggleSelect( blockId ) {
					if ( this.selected.includes( blockId ) ) {
						this.deselect( blockId );
					} else {
						this.select( blockId, true );
					}
				},

				selectAll( blockIds ) {
					if ( ! this.multiSelect ) return;
					this.selected = [ ...blockIds ];
					this._announce();
					this._dispatch();
				},

				clearSelection() {
					this.selected = [];
					this.focused = null;
					this._dispatch();
				},

				isSelected( blockId ) {
					return this.selected.includes( blockId );
				},

				getSelectionClass( blockId ) {
					if ( ! this.isSelected( blockId ) ) return '';
					if ( this.clipboardAction === 'cut' && this.clipboard.some( ( b ) => b.id === blockId ) ) {
						return this.cutClass;
					}
					return this.selected.length > 1 ? this.multiSelectionClass : this.selectionClass;
				},

				@if ( $enableClipboard )
					copy() {
						if ( this.selected.length === 0 ) return;
						this.clipboard = this.selected.map( ( id ) => ( { id } ) );
						this.clipboardAction = 'copy';
						this._announceClipboard( 'copied' );
					},

					cut() {
						if ( this.selected.length === 0 ) return;
						this.clipboard = this.selected.map( ( id ) => ( { id } ) );
						this.clipboardAction = 'cut';
						this._announceClipboard( 'cut' );
						this._dispatch();
					},

					paste() {
						if ( this.clipboard.length === 0 ) return;
						const event = new CustomEvent( 've-clipboard-paste', {
							bubbles: true,
							detail: {
								blocks: [ ...this.clipboard ],
								action: this.clipboardAction,
								targetId: this.focused,
							}
						} );
						document.dispatchEvent( event );
						this._announceClipboard( 'pasted' );

						if ( this.clipboardAction === 'cut' ) {
							this.clipboard = [];
							this.clipboardAction = null;
						}
					},

					duplicate() {
						if ( this.selected.length === 0 ) return;
						this.clipboard = this.selected.map( ( id ) => ( { id } ) );
						this.clipboardAction = 'copy';
						this.paste();
						this._announceClipboard( 'duplicated' );
					},
				@endif

				_announce() {
					if ( ! Alpine.store( 'announcer' ) ) return;
					const count = this.selected.length;
					if ( count === 0 ) return;
					const msg = count === 1
						? '{{ __( 'Block selected' ) }}'
						: count + ' {{ __( 'blocks selected' ) }}';
					Alpine.store( 'announcer' ).announce( msg );
				},

				_announceClipboard( action ) {
					if ( ! Alpine.store( 'announcer' ) ) return;
					const count = this.clipboard.length;
					const msgs = {
						copied: count === 1 ? '{{ __( 'Block copied' ) }}' : count + ' {{ __( 'blocks copied' ) }}',
						cut: count === 1 ? '{{ __( 'Block cut' ) }}' : count + ' {{ __( 'blocks cut' ) }}',
						pasted: count === 1 ? '{{ __( 'Block pasted' ) }}' : count + ' {{ __( 'blocks pasted' ) }}',
						duplicated: count === 1 ? '{{ __( 'Block duplicated' ) }}' : count + ' {{ __( 'blocks duplicated' ) }}',
					};
					Alpine.store( 'announcer' ).announce( msgs[ action ] || '' );
				},

				_dispatch() {
					document.dispatchEvent( new CustomEvent( 've-selection-change', {
						bubbles: true,
						detail: {
							selected: [ ...this.selected ],
							focused: this.focused,
						}
					} ) );
				}
			} );

			@if ( $enableClipboard )
				{{-- Register keyboard shortcuts if the shortcuts store exists --}}
				document.addEventListener( 'alpine:initialized', () => {
					if ( Alpine.store( 'shortcuts' ) ) {
						const sel = Alpine.store( 'selection' );
						Alpine.store( 'shortcuts' ).register( 'selection/copy', {
							keys: 'mod+c',
							description: '{{ __( 'Copy block' ) }}',
							category: 'selection',
							context: 'block',
							callback: () => sel.copy(),
						} );
						Alpine.store( 'shortcuts' ).register( 'selection/cut', {
							keys: 'mod+x',
							description: '{{ __( 'Cut block' ) }}',
							category: 'selection',
							context: 'block',
							callback: () => sel.cut(),
						} );
						Alpine.store( 'shortcuts' ).register( 'selection/paste', {
							keys: 'mod+v',
							description: '{{ __( 'Paste block' ) }}',
							category: 'selection',
							context: 'block',
							callback: () => sel.paste(),
						} );
						Alpine.store( 'shortcuts' ).register( 'selection/duplicate', {
							keys: 'mod+d',
							description: '{{ __( 'Duplicate block' ) }}',
							category: 'selection',
							context: 'block',
							callback: () => sel.duplicate(),
						} );
						Alpine.store( 'shortcuts' ).register( 'selection/deselect', {
							keys: 'escape',
							description: '{{ __( 'Deselect' ) }}',
							category: 'selection',
							context: 'block',
							callback: () => sel.clearSelection(),
						} );
					}
				} );
			@endif
		}
	"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	{{ $slot }}
</div>
