{{--
 * Editor State Component
 *
 * Initializes the central Alpine.js editor store that manages
 * block tree state, undo/redo history, editor mode, and save status.
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
		const _existingStore = Alpine.store( 'editor' );
		if ( ! _existingStore ) {
			Alpine.store( 'editor', {
				blocks: {{ Js::from( $initialBlocks ) }},

				history: {
					past: [],
					future: [],
				},
				maxHistorySize: {{ Js::from( $maxHistorySize ) }},

				mode: {{ Js::from( $mode ) }},
				showSidebar: {{ Js::from( $showSidebar ) }},
				showInserter: {{ Js::from( $showInserter ) }},
				devicePreview: {{ Js::from( $devicePreview ) }},
				saveStatus: {{ Js::from( $saveStatus ) }},
				lastSavedAt: null,
				autosave: {{ Js::from( $autosave ) }},
				autosaveInterval: {{ Js::from( $autosaveInterval ) }},
				_autosaveTimer: null,

				documentStatus: {{ Js::from( $documentStatus ) }},
				scheduledDate: {{ Js::from( $scheduledDate ) }},
				patterns: {{ Js::from( $patterns ) }},
				showPatternModal: false,
				leftSidebarTab: 'blocks',

				{{-- ── Initialization ─────────────────────────────────── --}}

				init() {
					if ( this.autosave ) {
						this._startAutosave();
					}
				},

				{{-- ── Block CRUD ─────────────────────────────────────── --}}

				addBlock( block, index = null ) {
					this._pushHistory();
					const newBlock = {
						id: block.id || this._generateId(),
						type: block.type || 'paragraph',
						attributes: block.attributes || {},
						innerBlocks: block.innerBlocks || [],
					};

					if ( null === index || index >= this.blocks.length ) {
						this.blocks.push( newBlock );
					} else {
						this.blocks.splice( index, 0, newBlock );
					}

					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_added' ) ) }} );
					this._dispatchChange();

					return newBlock;
				},

				removeBlock( blockId ) {
					const index = this.getBlockIndex( blockId );
					if ( -1 === index ) return;

					this._pushHistory();
					this.blocks.splice( index, 1 );
					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_removed' ) ) }} );
					this._dispatchChange();
				},

				updateBlock( blockId, attributes ) {
					const block = this.getBlock( blockId );
					if ( ! block ) return;

					this._pushHistory();
					block.attributes = { ...block.attributes, ...attributes };
					this.markDirty();
					this._dispatchChange();
				},

				moveBlock( blockId, newIndex ) {
					const oldIndex = this.getBlockIndex( blockId );
					if ( -1 === oldIndex || oldIndex === newIndex ) return;

					this._pushHistory();
					const [ block ] = this.blocks.splice( oldIndex, 1 );
					this.blocks.splice( newIndex > oldIndex ? newIndex - 1 : newIndex, 0, block );
					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_moved' ) ) }} );
					this._dispatchChange();
				},

				moveBlockUp( blockId ) {
					const index = this.getBlockIndex( blockId );
					if ( index <= 0 ) return;
					this.moveBlock( blockId, index - 1 );
				},

				moveBlockDown( blockId ) {
					const index = this.getBlockIndex( blockId );
					if ( -1 === index || index >= this.blocks.length - 1 ) return;
					this.moveBlock( blockId, index + 2 );
				},

				duplicateBlock( blockId ) {
					const block = this.getBlock( blockId );
					if ( ! block ) return;

					const index = this.getBlockIndex( blockId );
					const clone = JSON.parse( JSON.stringify( block ) );
					clone.id    = this._generateId();

					return this.addBlock( clone, index + 1 );
				},

				getBlock( blockId ) {
					return this.blocks.find( ( b ) => b.id === blockId ) || null;
				},

				getBlockIndex( blockId ) {
					return this.blocks.findIndex( ( b ) => b.id === blockId );
				},

				{{-- ── Nested Block Operations ────────────────────────── --}}

				addInnerBlock( parentId, block, index = null ) {
					const parent = this.getBlock( parentId );
					if ( ! parent ) return;

					this._pushHistory();
					if ( ! parent.innerBlocks ) {
						parent.innerBlocks = [];
					}

					const newBlock = {
						id: block.id || this._generateId(),
						type: block.type || 'paragraph',
						attributes: block.attributes || {},
						innerBlocks: block.innerBlocks || [],
					};

					if ( null === index || index >= parent.innerBlocks.length ) {
						parent.innerBlocks.push( newBlock );
					} else {
						parent.innerBlocks.splice( index, 0, newBlock );
					}

					this.markDirty();
					this._dispatchChange();

					return newBlock;
				},

				removeInnerBlock( parentId, blockId ) {
					const parent = this.getBlock( parentId );
					if ( ! parent || ! parent.innerBlocks ) return;

					const index = parent.innerBlocks.findIndex( ( b ) => b.id === blockId );
					if ( -1 === index ) return;

					this._pushHistory();
					parent.innerBlocks.splice( index, 1 );
					this.markDirty();
					this._dispatchChange();
				},

				{{-- ── Batch Operations ───────────────────────────────── --}}

				batchUpdate( operations, options = {} ) {
					if ( ! operations || 0 === operations.length ) return;

					this._pushHistory();

					operations.forEach( ( op ) => {
						switch ( op.action ) {
							case 'add':
								this._addBlockSilent( op.block, op.index );
								break;
							case 'remove':
								this._removeBlockSilent( op.blockId );
								break;
							case 'update':
								this._updateBlockSilent( op.blockId, op.attributes );
								break;
							case 'move':
								this._moveBlockSilent( op.blockId, op.newIndex );
								break;
						}
					} );

					this.markDirty();
					if ( ! options.silent ) {
						this._announceAction( {{ Js::from( __( 'visual-editor::ve.blocks_reordered' ) ) }} );
					}
					this._dispatchChange();
				},

				_addBlockSilent( block, index = null ) {
					const newBlock = {
						id: block.id || this._generateId(),
						type: block.type || 'paragraph',
						attributes: block.attributes || {},
						innerBlocks: block.innerBlocks || [],
					};

					if ( null === index || index >= this.blocks.length ) {
						this.blocks.push( newBlock );
					} else {
						this.blocks.splice( index, 0, newBlock );
					}
				},

				_removeBlockSilent( blockId ) {
					const index = this.getBlockIndex( blockId );
					if ( -1 !== index ) {
						this.blocks.splice( index, 1 );
					}
				},

				_updateBlockSilent( blockId, attributes ) {
					const block = this.getBlock( blockId );
					if ( block ) {
						block.attributes = { ...block.attributes, ...attributes };
					}
				},

				_moveBlockSilent( blockId, newIndex ) {
					const oldIndex = this.getBlockIndex( blockId );
					if ( -1 === oldIndex || oldIndex === newIndex ) return;

					const [ block ] = this.blocks.splice( oldIndex, 1 );
					this.blocks.splice( newIndex > oldIndex ? newIndex - 1 : newIndex, 0, block );
				},

				{{-- ── Undo / Redo ────────────────────────────────────── --}}

				_pushHistory() {
					this.history.past.push( JSON.parse( JSON.stringify( this.blocks ) ) );
					this.history.future = [];

					if ( this.history.past.length > this.maxHistorySize ) {
						this.history.past.shift();
					}
				},

				undo() {
					if ( ! this.canUndo() ) {
						this._announceAction( {{ Js::from( __( 'visual-editor::ve.nothing_to_undo' ) ) }} );
						return;
					}

					this.history.future.push( JSON.parse( JSON.stringify( this.blocks ) ) );
					this.blocks = JSON.parse( JSON.stringify( this.history.past.pop() ) );
					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.action_undone' ) ) }} );
					this._dispatchChange();
				},

				redo() {
					if ( ! this.canRedo() ) {
						this._announceAction( {{ Js::from( __( 'visual-editor::ve.nothing_to_redo' ) ) }} );
						return;
					}

					this.history.past.push( JSON.parse( JSON.stringify( this.blocks ) ) );
					this.blocks = JSON.parse( JSON.stringify( this.history.future.pop() ) );
					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.action_redone' ) ) }} );
					this._dispatchChange();
				},

				canUndo() {
					return this.history.past.length > 0;
				},

				canRedo() {
					return this.history.future.length > 0;
				},

				{{-- ── Save Status ────────────────────────────────────── --}}

				markDirty() {
					this.saveStatus = 'unsaved';
				},

				markSaving() {
					this.saveStatus = 'saving';
				},

				markSaved() {
					this.saveStatus = 'saved';
					this.lastSavedAt = new Date();
				},

				markError() {
					this.saveStatus = 'error';
				},

				{{-- ── Utility ────────────────────────────────────────── --}}

				getBlockCount() {
					return this.blocks.length;
				},

				{{-- ── Document Status ────────────────────────────────── --}}

				setDocumentStatus( status ) {
					this.documentStatus = status;
					if ( 'scheduled' !== status ) {
						this.scheduledDate = null;
					}
					this.markDirty();
					this._dispatchChange();
				},

				setScheduledDate( date ) {
					this.scheduledDate = date;
					this.markDirty();
					this._dispatchChange();
				},

				{{-- ── Pattern Operations ────────────────────────────────── --}}

				insertPattern( pattern ) {
					if ( ! pattern || ! pattern.blocks || 0 === pattern.blocks.length ) return;

					const operations = pattern.blocks.map( ( block, index ) => ( {
						action: 'add',
						block: {
							...block,
							id: this._generateId(),
						},
						index: this.blocks.length + index,
					} ) );

					this.batchUpdate( operations, { silent: true } );
					this._announceAction(
						{{ Js::from( __( 'visual-editor::ve.pattern_inserted' ) ) }}.replace( ':pattern', pattern.name || 'Pattern' )
					);
				},

				getWordCount() {
					let count = 0;
					const countWords = ( blocks ) => {
						blocks.forEach( ( block ) => {
							const attrs = block.attributes || {};
							[ 'content', 'text', 'caption' ].forEach( ( key ) => {
								if ( attrs[ key ] && 'string' === typeof attrs[ key ] ) {
									const stripped = attrs[ key ].replace( /<[^>]*>/g, '' ).trim();
									if ( stripped.length > 0 ) {
										count += stripped.split( /\s+/ ).length;
									}
								}
							} );
							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								countWords( block.innerBlocks );
							}
						} );
					};
					countWords( this.blocks );
					return count;
				},

				_generateId() {
					return 'block-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).substring( 2, 8 );
				},

				{{-- ── Autosave ───────────────────────────────────────── --}}

				_startAutosave() {
					this._stopAutosave();
					this._autosaveTimer = setInterval( () => {
						if ( 'unsaved' === this.saveStatus ) {
							document.dispatchEvent( new CustomEvent( 've-autosave', {
								bubbles: true,
								detail: {
									blocks: JSON.parse( JSON.stringify( this.blocks ) ),
								},
							} ) );
						}
					}, this.autosaveInterval * 1000 );
				},

				_stopAutosave() {
					if ( this._autosaveTimer ) {
						clearInterval( this._autosaveTimer );
						this._autosaveTimer = null;
					}
				},

				{{-- ── Internal Helpers ───────────────────────────────── --}}

				_announceAction( message ) {
					if ( Alpine.store( 'announcer' ) ) {
						Alpine.store( 'announcer' ).announce( message );
					}
				},

				_dispatchChange() {
					document.dispatchEvent( new CustomEvent( 've-editor-change', {
						bubbles: true,
						detail: {
							blocks: JSON.parse( JSON.stringify( this.blocks ) ),
							blockCount: this.getBlockCount(),
							wordCount: this.getWordCount(),
							saveStatus: this.saveStatus,
							documentStatus: this.documentStatus,
							scheduledDate: this.scheduledDate,
						},
					} ) );
				},
			} );

			Alpine.store( 'editor' ).init();

			{{-- Register keyboard shortcuts if the shortcuts store exists --}}
			let shortcutsRegistered = false;
			const registerShortcuts = () => {
				if ( shortcutsRegistered ) return;
				if ( ! Alpine.store( 'shortcuts' ) ) return;
				shortcutsRegistered = true;
				const editor = Alpine.store( 'editor' );

				Alpine.store( 'shortcuts' ).register( 'editor/undo', {
					keys: 'mod+z',
					description: {{ Js::from( __( 'visual-editor::ve.undo_action' ) ) }},
					category: 'global',
					context: 'global',
					callback: () => editor.undo(),
				} );

				Alpine.store( 'shortcuts' ).register( 'editor/redo', {
					keys: 'mod+shift+z',
					description: {{ Js::from( __( 'visual-editor::ve.redo_action' ) ) }},
					category: 'global',
					context: 'global',
					callback: () => editor.redo(),
				} );
			};

			if ( Alpine.store( 'shortcuts' ) ) {
				registerShortcuts();
			} else {
				document.addEventListener( 'alpine:initialized', () => registerShortcuts(), { once: true } );
			}
		} else {
			const store = _existingStore;
			store.blocks          = {{ Js::from( $initialBlocks ) }};
			store.maxHistorySize  = {{ Js::from( $maxHistorySize ) }};
			store.mode            = {{ Js::from( $mode ) }};
			store.showSidebar     = {{ Js::from( $showSidebar ) }};
			store.showInserter    = {{ Js::from( $showInserter ) }};
			store.devicePreview   = {{ Js::from( $devicePreview ) }};
			store.saveStatus      = {{ Js::from( $saveStatus ) }};
			store.lastSavedAt     = null;
			store.autosave        = {{ Js::from( $autosave ) }};
			store.autosaveInterval = {{ Js::from( $autosaveInterval ) }};
			store.documentStatus   = {{ Js::from( $documentStatus ) }};
			store.scheduledDate    = {{ Js::from( $scheduledDate ) }};
			store.patterns         = {{ Js::from( $patterns ) }};

			store.history.past   = [];
			store.history.future = [];

			store._stopAutosave();
			if ( store.autosave ) {
				store._startAutosave();
			}
		}
	"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.editor.init')
	@endif
	{{ $slot }}
</div>
