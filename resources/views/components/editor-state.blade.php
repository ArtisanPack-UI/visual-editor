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
				blockTransforms: {{ Js::from( $blockTransforms ) }},
				blockVariations: {{ Js::from( $blockVariations ) }},
				showPatternModal: false,
				leftSidebarTab: 'blocks',

				{{-- ── Initialization ─────────────────────────────────── --}}

				init() {
					this._migrateGroupVariations( this.blocks );

					if ( this.autosave ) {
						this._startAutosave();
					}

					this._registerLivewireBridge();
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
					if ( -1 !== index ) {
						if ( index <= 0 ) return;
						this.moveBlock( blockId, index - 1 );
						return;
					}

					// Inner block: move within parent's innerBlocks.
					const parent = this.getParentBlock( blockId );
					if ( ! parent || ! parent.innerBlocks ) return;
					const innerIndex = parent.innerBlocks.findIndex( ( b ) => b.id === blockId );
					if ( innerIndex <= 0 ) return;
					this.moveInnerBlock( parent.id, blockId, innerIndex - 1 );
				},

				moveBlockDown( blockId ) {
					const index = this.getBlockIndex( blockId );
					if ( -1 !== index ) {
						if ( index >= this.blocks.length - 1 ) return;
						this.moveBlock( blockId, index + 2 );
						return;
					}

					// Inner block: move within parent's innerBlocks.
					const parent = this.getParentBlock( blockId );
					if ( ! parent || ! parent.innerBlocks ) return;
					const innerIndex = parent.innerBlocks.findIndex( ( b ) => b.id === blockId );
					if ( -1 === innerIndex || innerIndex >= parent.innerBlocks.length - 1 ) return;
					this.moveInnerBlock( parent.id, blockId, innerIndex + 2 );
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
					const findBlock = ( blocks ) => {
						for ( const block of blocks ) {
							if ( block.id === blockId ) return block;
							if ( block.innerBlocks?.length ) {
								const found = findBlock( block.innerBlocks );
								if ( found ) return found;
							}
						}
						return null;
					};
					return findBlock( this.blocks );
				},

				getBlockIndex( blockId ) {
					return this.blocks.findIndex( ( b ) => b.id === blockId );
				},

				getParentBlock( blockId ) {
					const findParent = ( blocks, parent ) => {
						for ( const block of blocks ) {
							if ( block.id === blockId ) return parent;
							if ( block.innerBlocks?.length ) {
								const found = findParent( block.innerBlocks, block );
								if ( found ) return found;
							}
						}
						return null;
					};
					return findParent( this.blocks, null );
				},

				addInnerBlockAfter( afterBlockId, block = {} ) {
					const parent = this.getParentBlock( afterBlockId );
					if ( ! parent ) return null;

					const index = parent.innerBlocks.findIndex( ( b ) => b.id === afterBlockId );
					if ( -1 === index ) return null;

					return this.addInnerBlock( parent.id, block, index + 1 );
				},

				{{-- ── Nested Block Operations ────────────────────────── --}}

				addInnerBlock( parentId, block, index = null ) {
					const parent = this.getBlock( parentId );
					if ( ! parent ) return;

					this._pushHistory();
					const current = parent.innerBlocks || [];

					const newBlock = {
						id: block.id || this._generateId(),
						type: block.type || 'paragraph',
						attributes: block.attributes || {},
						innerBlocks: block.innerBlocks || [],
					};

					const updated = [ ...current ];
					if ( null === index || index >= updated.length ) {
						updated.push( newBlock );
					} else {
						updated.splice( index, 0, newBlock );
					}

					// Replace array reference to ensure Alpine reactivity.
					parent.innerBlocks = updated;

					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_added' ) ) }} );
					this._dispatchChange();

					return newBlock;
				},

				moveInnerBlock( parentId, blockId, newIndex ) {
					const parent = this.getBlock( parentId );
					if ( ! parent || ! parent.innerBlocks ) return;

					const oldIndex = parent.innerBlocks.findIndex( ( b ) => b.id === blockId );
					if ( -1 === oldIndex || oldIndex === newIndex ) return;

					this._pushHistory();
					const updated   = [ ...parent.innerBlocks ];
					const [ block ] = updated.splice( oldIndex, 1 );
					updated.splice( newIndex > oldIndex ? newIndex - 1 : newIndex, 0, block );
					parent.innerBlocks = updated;

					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_moved' ) ) }} );
					this._dispatchChange();
				},

				removeInnerBlock( parentId, blockId ) {
					const parent = this.getBlock( parentId );
					if ( ! parent || ! parent.innerBlocks ) return;

					const index = parent.innerBlocks.findIndex( ( b ) => b.id === blockId );
					if ( -1 === index ) return;

					this._pushHistory();

					// Replace array reference to ensure Alpine reactivity.
					parent.innerBlocks = parent.innerBlocks.filter( ( b ) => b.id !== blockId );

					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_removed' ) ) }} );
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
						{{ Js::from( __( 'visual-editor::ve.pattern_inserted', [ 'pattern' => '__PATTERN__' ] ) ) }}.replace( '__PATTERN__', () => pattern.name || 'Pattern' )
					);
				},

				{{-- ── Block Transforms ────────────────────────────────── --}}

				transformBlock( blockId, targetType ) {
					const block = this.getBlock( blockId );
					if ( ! block ) return;

					const transforms = this.blockTransforms[ block.type ] || {};
					const mapping    = transforms[ targetType ];
					if ( ! mapping ) return;

					this._pushHistory();

					const newAttributes = {};
					Object.keys( mapping ).forEach( ( targetField ) => {
						const sourceField = mapping[ targetField ];
						if ( block.attributes && undefined !== block.attributes[ sourceField ] ) {
							newAttributes[ targetField ] = block.attributes[ sourceField ];
						}
					} );

					block.type       = targetType;
					block.attributes = newAttributes;

					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_transformed' ) ) }} );
					this._dispatchChange();
				},

				getTransformsForBlock( blockType ) {
					const transforms = this.blockTransforms[ blockType ];
					if ( ! transforms ) return [];
					return Object.keys( transforms );
				},

				{{-- ── Block After / Replace ──────────────────────────── --}}

				addBlockAfter( afterBlockId, block = {} ) {
					const index = this.getBlockIndex( afterBlockId );
					if ( -1 === index ) return null;

					return this.addBlock(
						{ type: block.type || 'paragraph', attributes: block.attributes || {}, innerBlocks: block.innerBlocks || [] },
						index + 1,
					);
				},

				replaceBlock( blockId, newBlockData ) {
					const newBlock = {
						id: newBlockData.id || this._generateId(),
						type: newBlockData.type || 'paragraph',
						attributes: newBlockData.attributes || {},
						innerBlocks: newBlockData.innerBlocks || [],
					};

					const topIndex = this.getBlockIndex( blockId );
					if ( -1 !== topIndex ) {
						this._pushHistory();
						this.blocks.splice( topIndex, 1, newBlock );
						this.markDirty();
						this._dispatchChange();
						return newBlock;
					}

					// Inner block replacement.
					const parent = this.getParentBlock( blockId );
					if ( ! parent ) return null;

					const innerIdx = parent.innerBlocks.findIndex( ( b ) => b.id === blockId );
					if ( -1 === innerIdx ) return null;

					this._pushHistory();
					const updated = [ ...parent.innerBlocks ];
					updated.splice( innerIdx, 1, newBlock );
					parent.innerBlocks = updated;
					this.markDirty();
					this._dispatchChange();
					return newBlock;
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

				/**
				 * Backfill _groupVariation on legacy group blocks that lack it.
				 * Infers the correct variation from flexDirection so that blocks
				 * created before _groupVariation became the single source of
				 * truth are normalized on load.
				 */
				_migrateGroupVariations( blocks ) {
					for ( const block of blocks ) {
						if ( 'group' === block.type && ! block.attributes?._groupVariation ) {
							if ( ! block.attributes ) {
								block.attributes = {};
							}
							block.attributes._groupVariation = 'row' === block.attributes.flexDirection ? 'row' : 'group';
						}
						if ( block.innerBlocks?.length ) {
							this._migrateGroupVariations( block.innerBlocks );
						}
					}
				},

				{{-- ── Autosave ───────────────────────────────────────── --}}

				_startAutosave() {
					this._stopAutosave();
					if ( ! this.autosaveInterval || this.autosaveInterval <= 0 ) return;
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

				{{-- ── Livewire Bridge ───────────────────────────── --}}

				_registerLivewireBridge() {
					document.addEventListener( 've-document-saved', ( e ) => {
						this.markSaved();
						this._announceAction( {{ Js::from( __( 'visual-editor::ve.document_saved' ) ) }} );
					} );

					document.addEventListener( 've-document-error', ( e ) => {
						this.markError();
						this._announceAction( {{ Js::from( __( 'visual-editor::ve.document_save_error' ) ) }} );
					} );

					document.addEventListener( 've-draft-restored', ( e ) => {
						if ( e.detail && e.detail.blocks ) {
							this._pushHistory();
							this.blocks = e.detail.blocks;
							this.markDirty();
							this._announceAction( {{ Js::from( __( 'visual-editor::ve.draft_restored' ) ) }} );
						}
					} );

					document.addEventListener( 've-pattern-loaded', ( e ) => {
						if ( e.detail && e.detail.blocks ) {
							this.insertPattern( { name: e.detail.name, blocks: e.detail.blocks } );
						}
					} );

					document.addEventListener( 've-revision-restored', ( e ) => {
						if ( e.detail && e.detail.blocks ) {
							this._pushHistory();
							this.blocks = e.detail.blocks;
							this.markDirty();
							this._announceAction( {{ Js::from( __( 'visual-editor::ve.revision_restored' ) ) }} );
						}
					} );

					if ( ! window.__veMediaSelectedRegistered ) {
					window.__veMediaSelectedRegistered = true;
					window.addEventListener( 've-media-selected', ( e ) => {
						if ( e.detail && e.detail.media && e.detail.media.length ) {
							this._announceAction( {{ Js::from( __( 'visual-editor::ve.media_selected' ) ) }} );
						}

						// The media-picker Livewire component bridges
						// Livewire 'media-selected' → window 've-media-selected'.
						// This handler routes the event to the correct block field.
						if ( ! e.detail || ! e.detail.context || ! e.detail.media?.length ) return;

						const parts = e.detail.context.split( ':' );
						if ( parts.length < 2 ) return;
						const blockId   = parts[0];
						const fieldSuffix = parts.slice( 1 ).join( ':' );

						// Map known context suffixes to block fields.
						const fieldMap = {
							'image-url': 'url',
							'video-url': 'url',
							'audio-url': 'url',
							'toolbar-replace': 'url',
						};

						// Gallery-add context: create inner image blocks instead of updating a field.
						if ( 'gallery-add' === fieldSuffix && blockId ) {
							e.detail.media.forEach( ( m ) => {
								this.addInnerBlock( blockId, {
									type: 'image',
									attributes: {
										url: m.url ?? m.path ?? '',
										alt: m.alt ?? '',
									},
								} );
							} );
							return;
						}

						// File blocks: also populate filename and fileSize from the media item.
						if ( 'file-url' === fieldSuffix && blockId ) {
							const m   = e.detail.media[0];
							const url = m.url ?? m.path ?? '';
							if ( url ) {
								const attrs = { url: url };

								// Derive filename from media item or URL.
								if ( m.file_name ) {
									attrs.filename = m.file_name;
								} else if ( m.title ) {
									attrs.filename = m.title;
								} else {
									attrs.filename = url.split( '/' ).pop().split( '?' )[0] || '';
								}

								// Human-readable file size from bytes.
								if ( m.file_size ) {
									const bytes = parseInt( m.file_size, 10 );
									if ( bytes >= 1048576 ) {
										attrs.fileSize = ( bytes / 1048576 ).toFixed( 1 ) + ' MB';
									} else if ( bytes >= 1024 ) {
										attrs.fileSize = ( bytes / 1024 ).toFixed( 0 ) + ' KB';
									} else {
										attrs.fileSize = bytes + ' B';
									}
								}

								this.updateBlock( blockId, attrs );
							}
							return;
						}

						const field = fieldMap[ fieldSuffix ];
						if ( field && blockId ) {
							const url = e.detail.media[0].url ?? e.detail.media[0].path ?? '';
							if ( url ) {
								this.updateBlock( blockId, { [field]: url } );
							}
						}
					} );
					} // end guard

					document.addEventListener( 've-field-change', ( e ) => {
						if ( ! e.detail ) return;
						let blockId = e.detail.blockId;
						const field = e.detail.field;
						const value = e.detail.value;

						if ( ! field ) return;

						// Resolve 'dynamic' blockId to the currently focused block.
						if ( 'dynamic' === blockId || ! blockId ) {
							blockId = Alpine.store( 'selection' )?.focused ?? null;
						}

						if ( blockId ) {
							this.updateBlock( blockId, { [field]: value } );
						}
					} );
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
			store._migrateGroupVariations( store.blocks );
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
			store.blockTransforms  = {{ Js::from( $blockTransforms ) }};
			store.blockVariations  = {{ Js::from( $blockVariations ) }};
			store.showPatternModal = false;
			store.leftSidebarTab   = 'blocks';

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
