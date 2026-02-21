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
		const _existingStore = Alpine.store( 'selection' );
		if ( ! _existingStore ) {
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
					this._announce();
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
					const ids = blockIds || [];
					this.selected = [ ...ids ];
					this.focused = ids.length > 0 ? ids[ 0 ] : null;
					this._announce();
					this._dispatch();
				},

				clearSelection() {
					this.selected = [];
					this.focused = null;
					if ( Alpine.store( 'announcer' ) ) {
						Alpine.store( 'announcer' ).announce( {!! Js::from( __( 'visual-editor::ve.selection_cleared' ) ) !!} );
					}
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

					paste( silent = false ) {
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
						if ( ! silent ) {
							this._announceClipboard( 'pasted' );
						}

						if ( this.clipboardAction === 'cut' ) {
							this.clipboard = [];
							this.clipboardAction = null;
							this._dispatch();
						}
					},

					duplicate() {
						if ( this.selected.length === 0 ) return;
						const prevClipboard = this.clipboard;
						const prevAction    = this.clipboardAction;
						this.clipboard = this.selected.map( ( id ) => ( { id } ) );
						this.clipboardAction = 'copy';
						this.paste( true );
						this._announceClipboard( 'duplicated' );
						this.clipboard       = prevClipboard;
						this.clipboardAction = prevAction;
					},
				@endif

				_announce() {
					if ( ! Alpine.store( 'announcer' ) ) return;
					const count = this.selected.length;
					if ( count === 0 ) return;
					const msg = count === 1
						? {!! Js::from( trans_choice( 'visual-editor::ve.blocks_selected', 1 ) ) !!}
						: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_selected', 2, [ 'count' => '__COUNT__' ] ) ) !!}.replaceAll( '__COUNT__', count );
					Alpine.store( 'announcer' ).announce( msg );
				},

				_announceClipboard( action ) {
					if ( ! Alpine.store( 'announcer' ) ) return;
					const count = this.clipboard.length;
					const singular = {
						copied: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_copied', 1 ) ) !!},
						cut: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_cut', 1 ) ) !!},
						pasted: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_pasted', 1 ) ) !!},
						duplicated: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_duplicated', 1 ) ) !!},
					};
					const plural = {
						copied: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_copied', 2, [ 'count' => '__COUNT__' ] ) ) !!},
						cut: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_cut', 2, [ 'count' => '__COUNT__' ] ) ) !!},
						pasted: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_pasted', 2, [ 'count' => '__COUNT__' ] ) ) !!},
						duplicated: {!! Js::from( trans_choice( 'visual-editor::ve.blocks_duplicated', 2, [ 'count' => '__COUNT__' ] ) ) !!},
					};
					const msg = 1 === count
						? ( singular[ action ] || '' )
						: ( plural[ action ] || '' ).replaceAll( '__COUNT__', count );
					Alpine.store( 'announcer' ).announce( msg );
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

			{{-- Register keyboard shortcuts if the shortcuts store exists --}}
			let shortcutsRegistered = false;
			const registerShortcuts = () => {
				if ( shortcutsRegistered ) return;
				if ( ! Alpine.store( 'shortcuts' ) ) return;
				shortcutsRegistered = true;
				const sel = Alpine.store( 'selection' );

				@if ( $enableClipboard )
					Alpine.store( 'shortcuts' ).register( 'selection/copy', {
						keys: 'mod+c',
						description: {!! Js::from( __( 'visual-editor::ve.copy_block' ) ) !!},
						category: 'selection',
						context: 'block',
						callback: () => sel.copy(),
					} );
					Alpine.store( 'shortcuts' ).register( 'selection/cut', {
						keys: 'mod+x',
						description: {!! Js::from( __( 'visual-editor::ve.cut_block' ) ) !!},
						category: 'selection',
						context: 'block',
						callback: () => sel.cut(),
					} );
					Alpine.store( 'shortcuts' ).register( 'selection/paste', {
						keys: 'mod+v',
						description: {!! Js::from( __( 'visual-editor::ve.paste_block' ) ) !!},
						category: 'selection',
						context: 'block',
						callback: () => sel.paste(),
					} );
					Alpine.store( 'shortcuts' ).register( 'selection/duplicate', {
						keys: 'mod+d',
						description: {!! Js::from( __( 'visual-editor::ve.duplicate_block' ) ) !!},
						category: 'selection',
						context: 'block',
						callback: () => sel.duplicate(),
					} );
				@endif

				Alpine.store( 'shortcuts' ).register( 'selection/deselect', {
					keys: 'escape',
					description: {!! Js::from( __( 'visual-editor::ve.deselect' ) ) !!},
					category: 'selection',
					context: 'block',
					callback: () => sel.clearSelection(),
				} );
			};

			{{-- Try immediately, and also listen for alpine:initialized (fires after all components are initialized) in case shortcuts store is created later --}}
			if ( Alpine.store( 'shortcuts' ) ) {
				registerShortcuts();
			} else {
				document.addEventListener( 'alpine:initialized', () => registerShortcuts(), { once: true } );
			}
		} else {
			const store = _existingStore;
			store.multiSelect = {{ Js::from( $multiSelect ) }};
			store.selectionClass = {{ Js::from( $selectionClass ) }};
			store.multiSelectionClass = {{ Js::from( $multiSelectionClass ) }};
			store.cutClass = {{ Js::from( $cutClass ) }};

			@if ( $enableClipboard )
				if ( typeof store.copy !== 'function' ) {
					console.warn( 'SelectionManager: clipboard methods not registered on existing store. Ensure enableClipboard is consistent across instances.' );
				}
			@endif
		}
	"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	{{ $slot }}
</div>
