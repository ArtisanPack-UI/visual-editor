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

@php
	use ArtisanPackUI\VisualEditor\View\Components\EditorState;

	$saveStatusConstants    = EditorState::saveStatusMap();
	$documentStatusConstants = EditorState::documentStatusMap();
@endphp

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
				_pendingDirty: false,
				autosave: {{ Js::from( $autosave ) }},
				autosaveInterval: {{ Js::from( $autosaveInterval ) }},
				_autosaveTimer: null,

				documentStatus: {{ Js::from( $documentStatus ) }},
				scheduledDate: {{ Js::from( $scheduledDate ) }},
				patterns: {{ Js::from( $patterns ) }},
				blockTransforms: {{ Js::from( $blockTransforms ) }},
				blockVariations: {{ Js::from( $blockVariations ) }},
				defaultBlockType: {{ Js::from( $defaultBlockType ) }},
				defaultInnerBlocksMap: {{ Js::from( $defaultInnerBlocksMap ) }},
				meta: {{ Js::from( $initialMeta ) }},
				globalStyles: {
					palette: {{ Js::from( $initialPalette ) }},
					typography: {{ Js::from( $initialTypography ) }},
					spacing: {{ Js::from( $initialSpacing ) }},
				},
				showPatternModal: false,
				leftSidebarTab: 'blocks',

				SAVE_STATUS: Object.freeze( {{ Js::from( $saveStatusConstants ) }} ),
				DOCUMENT_STATUS: Object.freeze( {{ Js::from( $documentStatusConstants ) }} ),

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
					const blockType   = block.type || this.defaultBlockType;
					let rawInner      = block.innerBlocks || [];
					if ( 0 === rawInner.length && this.defaultInnerBlocksMap[ blockType ] ) {
						rawInner = this.defaultInnerBlocksMap[ blockType ];
					}
					const newBlock = {
						id: block.id || this._generateId(),
						type: blockType,
						attributes: block.attributes || {},
						innerBlocks: rawInner.map( ( inner ) => ( {
							id: inner.id || this._generateId(),
							type: inner.type || this.defaultBlockType,
							attributes: inner.attributes || {},
							innerBlocks: inner.innerBlocks || [],
						} ) ),
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

				removeBlockAttributes( blockId, keys ) {
					const block = this.getBlock( blockId );
					if ( ! block || ! block.attributes ) return;

					this._pushHistory();
					const updated = { ...block.attributes };
					keys.forEach( ( key ) => { delete updated[ key ]; } );
					block.attributes = updated;
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
						type: block.type || this.defaultBlockType,
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
						type: block.type || this.defaultBlockType,
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
					this.history.past.push( {
						blocks: JSON.parse( JSON.stringify( this.blocks ) ),
						meta: JSON.parse( JSON.stringify( this.meta ) ),
						globalStyles: JSON.parse( JSON.stringify( this.globalStyles ) ),
						documentStatus: this.documentStatus,
						scheduledDate: this.scheduledDate,
					} );
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

					this.history.future.push( {
						blocks: JSON.parse( JSON.stringify( this.blocks ) ),
						meta: JSON.parse( JSON.stringify( this.meta ) ),
						globalStyles: JSON.parse( JSON.stringify( this.globalStyles ) ),
						documentStatus: this.documentStatus,
						scheduledDate: this.scheduledDate,
					} );
					const snapshot = this.history.past.pop();
					this.blocks = JSON.parse( JSON.stringify( snapshot.blocks ?? snapshot ) );
					if ( snapshot.meta ) {
						this.meta = JSON.parse( JSON.stringify( snapshot.meta ) );
					}
					if ( snapshot.globalStyles ) {
						this.globalStyles = JSON.parse( JSON.stringify( snapshot.globalStyles ) );
					}
					if ( undefined !== snapshot.documentStatus ) {
						this.documentStatus = snapshot.documentStatus;
					}
					if ( undefined !== snapshot.scheduledDate ) {
						this.scheduledDate = snapshot.scheduledDate;
					}
					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.action_undone' ) ) }} );
					this._dispatchChange();
				},

				redo() {
					if ( ! this.canRedo() ) {
						this._announceAction( {{ Js::from( __( 'visual-editor::ve.nothing_to_redo' ) ) }} );
						return;
					}

					this.history.past.push( {
						blocks: JSON.parse( JSON.stringify( this.blocks ) ),
						meta: JSON.parse( JSON.stringify( this.meta ) ),
						globalStyles: JSON.parse( JSON.stringify( this.globalStyles ) ),
						documentStatus: this.documentStatus,
						scheduledDate: this.scheduledDate,
					} );
					const snapshot = this.history.future.pop();
					this.blocks = JSON.parse( JSON.stringify( snapshot.blocks ?? snapshot ) );
					if ( snapshot.meta ) {
						this.meta = JSON.parse( JSON.stringify( snapshot.meta ) );
					}
					if ( snapshot.globalStyles ) {
						this.globalStyles = JSON.parse( JSON.stringify( snapshot.globalStyles ) );
					}
					if ( undefined !== snapshot.documentStatus ) {
						this.documentStatus = snapshot.documentStatus;
					}
					if ( undefined !== snapshot.scheduledDate ) {
						this.scheduledDate = snapshot.scheduledDate;
					}
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

				_saveTransitions: {
					saved: [ 'unsaved' ],
					unsaved: [ 'unsaved', 'saving' ],
					saving: [ 'saved', 'error' ],
					error: [ 'unsaved', 'saving' ],
				},

				_canTransitionTo( target ) {
					const allowed = this._saveTransitions[ this.saveStatus ];
					return allowed && allowed.includes( target );
				},

				markDirty() {
					if ( this.SAVE_STATUS.SAVING === this.saveStatus ) {
						this._pendingDirty = true;
						return true;
					}
					if ( ! this._canTransitionTo( this.SAVE_STATUS.UNSAVED ) ) return false;
					this.saveStatus = this.SAVE_STATUS.UNSAVED;
					return true;
				},

				markSaving() {
					if ( ! this._canTransitionTo( this.SAVE_STATUS.SAVING ) ) return false;
					this._pendingDirty = false;
					this.saveStatus = this.SAVE_STATUS.SAVING;
					return true;
				},

				markSaved() {
					if ( ! this._canTransitionTo( this.SAVE_STATUS.SAVED ) ) return false;
					this.saveStatus = this.SAVE_STATUS.SAVED;
					this.lastSavedAt = new Date();
					if ( this._pendingDirty ) {
						this._pendingDirty = false;
						this.saveStatus = this.SAVE_STATUS.UNSAVED;
					}
					return true;
				},

				markError() {
					if ( ! this._canTransitionTo( this.SAVE_STATUS.ERROR ) ) return false;
					this.saveStatus = this.SAVE_STATUS.ERROR;
					return true;
				},

				{{-- ── Utility ────────────────────────────────────────── --}}

				getBlockCount() {
					return this.blocks.length;
				},

				{{-- ── Sidebar / Inserter Toggles ────────────────────── --}}

				toggleSidebar() {
					this.showSidebar = ! this.showSidebar;
				},

				toggleInserter() {
					this.showInserter = ! this.showInserter;
				},

				openInserter() {
					this.showInserter = true;
				},

				closeInserter() {
					this.showInserter = false;
				},

				openLayers() {
					this.leftSidebarTab = 'layers';
					this.showInserter   = true;
				},

				{{-- ── Document Status ────────────────────────────────── --}}

				setDocumentStatus( status ) {
					this.documentStatus = status;
					if ( this.DOCUMENT_STATUS.SCHEDULED !== status ) {
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

				{{-- ── Meta ──────────────────────────────────────────────── --}}

				setMeta( key, value ) {
					this._pushHistory();
					this.meta[ key ] = value;
					this.markDirty();
					this._dispatchChange();
				},

				getMeta( key, defaultValue = null ) {
					return this.meta[ key ] ?? defaultValue;
				},

				setMetaBulk( data ) {
					this._pushHistory();
					Object.assign( this.meta, data );
					this.markDirty();
					this._dispatchChange();
				},

				{{-- ── Global Styles: Palette ─────────────────────────────── --}}

				setPalette( entries ) {
					this._pushHistory();
					this.globalStyles.palette = entries;
					this._syncGlobalCssVariables();
					this.markDirty();
					this._dispatchChange();
				},

				getPalette() {
					return this.globalStyles.palette;
				},

				getPaletteColor( slug ) {
					return this.globalStyles.palette.find( ( c ) => c.slug === slug ) || null;
				},

				resolvePaletteReference( value ) {
					if ( 'string' !== typeof value || ! value.startsWith( 'palette:' ) ) {
						return value;
					}
					const slug  = value.substring( 8 );
					const entry = this.getPaletteColor( slug );
					return entry ? entry.color : value;
				},

				{{-- ── Global Styles: Typography ─────────────────────────── --}}

				setTypography( data ) {
					this._pushHistory();
					this.globalStyles.typography = data;
					this._syncGlobalCssVariables();
					this.markDirty();
					this._dispatchChange();
				},

				getTypography() {
					return this.globalStyles.typography;
				},

				{{-- ── Global Styles: Spacing ────────────────────────────── --}}

				setSpacing( data ) {
					this._pushHistory();
					this.globalStyles.spacing = data;
					this._syncGlobalCssVariables();
					this.markDirty();
					this._dispatchChange();
				},

				getSpacing() {
					return this.globalStyles.spacing;
				},

				{{-- ── Reactive CSS Custom Properties ────────────────────── --}}

				_camelToKebab( str ) {
					return str.replace( /([a-z0-9])([A-Z])/g, '$1-$2' ).toLowerCase();
				},

				_syncGlobalCssVariables() {
					const root = document.documentElement;

					// Colors
					const palette = this.globalStyles.palette || [];
					palette.forEach( ( entry ) => {
						if ( entry.slug && entry.color ) {
							root.style.setProperty( '--ve-color-' + entry.slug, entry.color );
						}
					} );

					// Typography: font families
					const typography = this.globalStyles.typography || {};
					const families   = typography.fontFamilies || {};
					Object.keys( families ).forEach( ( slot ) => {
						if ( families[ slot ] ) {
							root.style.setProperty( '--ve-font-' + slot, families[ slot ] );
						}
					} );

					// Typography: elements (convert camelCase keys to kebab-case)
					const elements = typography.elements || {};
					Object.keys( elements ).forEach( ( element ) => {
						const styles = elements[ element ] || {};
						Object.keys( styles ).forEach( ( prop ) => {
							if ( styles[ prop ] ) {
								root.style.setProperty( '--ve-text-' + element + '-' + this._camelToKebab( prop ), styles[ prop ] );
							}
						} );
					} );

					// Spacing: scale
					const spacing = this.globalStyles.spacing || {};
					const scale   = spacing.scale || [];
					scale.forEach( ( step ) => {
						if ( step.slug && step.value ) {
							root.style.setProperty( '--ve-spacing-' + step.slug, step.value );
						}
					} );

					// Spacing: custom steps
					const customSteps = spacing.customSteps || [];
					customSteps.forEach( ( step ) => {
						if ( step.slug && step.value ) {
							root.style.setProperty( '--ve-spacing-' + step.slug, step.value );
						}
					} );

					// Spacing: block gap
					if ( spacing.blockGap ) {
						let gapValue   = spacing.blockGap;
						const allSteps = [ ...scale, ...customSteps ];
						const gapStep  = allSteps.find( ( s ) => s.slug === gapValue );
						if ( gapStep ) {
							gapValue = gapStep.value;
						}
						root.style.setProperty( '--ve-block-gap', gapValue );
					}
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
					if ( undefined === mapping ) return;

					this._pushHistory();

					// Resolve source text from the block. For blocks whose content
					// lives in the DOM (list, quote with inner blocks), read it
					// from the DOM element rather than from the attributes alone.
					const sourceText = this._resolveTransformText( block );

					const newAttributes = {};
					Object.keys( mapping ).forEach( ( targetField ) => {
						const sourceField = mapping[ targetField ];
						if ( block.attributes && undefined !== block.attributes[ sourceField ] ) {
							newAttributes[ targetField ] = block.attributes[ sourceField ];
						}
					} );

					// Handle list target: text becomes a list item.
					if ( 'list' === targetType && sourceText ) {
						newAttributes._transformedContent = '<li>' + sourceText + '</li>';
					}

					// Handle quote target: text becomes an inner paragraph block.
					if ( 'quote' === targetType && sourceText ) {
						block.innerBlocks = [ {
							id: this._generateId(),
							type: this.defaultBlockType,
							attributes: { text: sourceText },
							innerBlocks: [],
						} ];
					} else if ( 'quote' !== targetType ) {
						// When leaving a container block (e.g. quote → paragraph),
						// clear inner blocks so the new type doesn't inherit them.
						block.innerBlocks = [];
					}

					// Handle text source from list DOM or quote inner blocks into
					// the target text attribute when the mapping is empty.
					if ( sourceText && 0 === Object.keys( mapping ).length ) {
						newAttributes.text = sourceText;
					}

					block.type       = targetType;
					block.attributes = newAttributes;

					this.markDirty();
					this._announceAction( {{ Js::from( __( 'visual-editor::ve.block_transformed' ) ) }} );
					this._dispatchChange();
				},

				/**
				 * Resolve the text content from a block for transformation.
				 *
				 * For blocks whose content lives in the DOM during editing
				 * (list, quote with inner blocks), reads from the DOM element.
				 */
				_resolveTransformText( block ) {
					// Paragraph / heading: text lives in attributes.
					if ( block.attributes?.text ) {
						return block.attributes.text;
					}

					// List: content lives in the DOM as <li> elements.
					if ( 'list' === block.type ) {
						const listEl = document.querySelector( '[data-block-id=' + block.id + '] [contenteditable]' );
						if ( listEl ) {
							const items = listEl.querySelectorAll( 'li' );
							const texts = [];
							items.forEach( ( li ) => {
								const t = li.innerHTML.trim();
								if ( t ) {
									texts.push( t );
								}
							} );
							return texts.join( '<br>' );
						}
					}

					// Quote: text comes from inner blocks.
					if ( 'quote' === block.type && block.innerBlocks && block.innerBlocks.length > 0 ) {
						// Read from DOM if available (more current than store).
						const innerEl = document.querySelector( '[data-inner-block-id=' + block.innerBlocks[0].id + '] [contenteditable]' );
						if ( innerEl ) {
							return innerEl.innerHTML;
						}
						return block.innerBlocks[0].attributes?.text || '';
					}

					return '';
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
						{ type: block.type || this.defaultBlockType, attributes: block.attributes || {}, innerBlocks: block.innerBlocks || [] },
						index + 1,
					);
				},

				replaceBlock( blockId, newBlockData ) {
					const blockType = newBlockData.type || this.defaultBlockType;
					let rawInner    = newBlockData.innerBlocks || [];
					if ( 0 === rawInner.length && this.defaultInnerBlocksMap[ blockType ] ) {
						rawInner = this.defaultInnerBlocksMap[ blockType ];
					}
					const newBlock = {
						id: newBlockData.id || this._generateId(),
						type: blockType,
						attributes: newBlockData.attributes || {},
						innerBlocks: rawInner.map( ( inner ) => ( {
							id: inner.id || this._generateId(),
							type: inner.type || this.defaultBlockType,
							attributes: inner.attributes || {},
							innerBlocks: inner.innerBlocks || [],
						} ) ),
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
					if ( typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function' ) {
						return 'block-' + crypto.randomUUID();
					}
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
						if ( this.SAVE_STATUS.UNSAVED === this.saveStatus ) {
							window.dispatchEvent( new CustomEvent( 've-autosave', {
								detail: {
									blocks: JSON.parse( JSON.stringify( this.blocks ) ),
									documentStatus: this.documentStatus,
									scheduledDate: this.scheduledDate,
									meta: JSON.parse( JSON.stringify( this.meta ) ),
									globalStyles: JSON.parse( JSON.stringify( this.globalStyles ) ),
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
					if ( window.__veLivewireBridgeRegistered ) return;
					window.__veLivewireBridgeRegistered = true;

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

							if ( e.detail.meta ) {
								if ( 'documentStatus' in e.detail.meta ) {
									this.documentStatus = e.detail.meta.documentStatus;
								}
								if ( 'scheduledDate' in e.detail.meta ) {
									this.scheduledDate = e.detail.meta.scheduledDate;
								}

								const { documentStatus, scheduledDate, ...otherMeta } = e.detail.meta;
								this.meta = otherMeta;
							}

							this.markDirty();
							this._announceAction( {{ Js::from( __( 'visual-editor::ve.draft_restored' ) ) }} );
						}
					} );

					document.addEventListener( 've-palette-change', ( e ) => {
						if ( e.detail && e.detail.palette ) {
							this.setPalette( e.detail.palette );
						}
					} );

					document.addEventListener( 've-typography-change', ( e ) => {
						if ( e.detail ) {
							this.setTypography( {
								fontFamilies: e.detail.fontFamilies || {},
								elements: e.detail.elements || {},
							} );
						}
					} );

					document.addEventListener( 've-spacing-change', ( e ) => {
						if ( e.detail ) {
							this.setSpacing( {
								scale: e.detail.scale || [],
								blockGap: e.detail.blockGap || 'md',
								customSteps: e.detail.customSteps || [],
							} );
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
							'cover-media': 'mediaUrl',
							'media-text-url': 'mediaUrl',
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
							// Special handling for table header/footer toggles:
							// add or remove dedicated rows instead of converting existing ones.
							const block = this.getBlock( blockId );
							if ( block && 'table' === block.type && block.attributes?.rows ) {
								const newCell = () => ( { content: '', colSpan: 1, rowSpan: 1, alignment: 'left' } );
								const numCols = block.attributes.rows[0] ? block.attributes.rows[0].length : 2;

								if ( 'hasHeaderRow' === field ) {
									// Sync cell content from DOM before modifying.
									const tableEl = document.querySelector( '[data-block-id=\'' + CSS.escape( blockId ) + '\'] table' );
									const rows = JSON.parse( JSON.stringify( block.attributes.rows ) );
									if ( tableEl ) {
										tableEl.querySelectorAll( '[data-row][data-col]' ).forEach( ( cell ) => {
											const r = parseInt( cell.getAttribute( 'data-row' ) );
											const c = parseInt( cell.getAttribute( 'data-col' ) );
											if ( rows[ r ] && rows[ r ][ c ] ) { rows[ r ][ c ].content = cell.innerHTML; }
										} );
									}
									if ( value ) {
										// Add new header row at position 0.
										rows.unshift( Array.from( { length: numCols }, newCell ) );
									} else {
										// Remove the header row (first row).
										rows.shift();
									}
									this.updateBlock( blockId, { rows: rows, hasHeaderRow: value } );
									return;
								}

								if ( 'hasFooterRow' === field ) {
									const tableEl = document.querySelector( '[data-block-id=\'' + CSS.escape( blockId ) + '\'] table' );
									const rows = JSON.parse( JSON.stringify( block.attributes.rows ) );
									if ( tableEl ) {
										tableEl.querySelectorAll( '[data-row][data-col]' ).forEach( ( cell ) => {
											const r = parseInt( cell.getAttribute( 'data-row' ) );
											const c = parseInt( cell.getAttribute( 'data-col' ) );
											if ( rows[ r ] && rows[ r ][ c ] ) { rows[ r ][ c ].content = cell.innerHTML; }
										} );
									}
									if ( value ) {
										// Add new footer row at end.
										rows.push( Array.from( { length: numCols }, newCell ) );
									} else {
										// Remove the footer row (last row).
										rows.pop();
									}
									this.updateBlock( blockId, { rows: rows, hasFooterRow: value } );
									return;
								}
							}

							// Embed / Social Embed: changing the URL should
							// trigger a fresh oEmbed resolve so the preview updates.
							if ( 'url' === field && block && ( 'embed' === block.type || 'social-embed' === block.type ) ) {
								// Clear derived metadata immediately (handles empty URL too).
								this.updateBlock( blockId, { url: value || '', html: '', _source: '', title: '', description: '', thumbnailUrl: '', providerName: '', platform: '' } );
								if ( ! value ) return;

								const requestedUrl = value;
								const _store       = this;
								fetch( '/api/visual-editor/embed/resolve', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
									body: JSON.stringify( { url: requestedUrl } ),
								} )
								.then( ( r ) => r.json() )
								.then( ( j ) => {
									// Guard against stale responses: only apply if the
									// block still exists and its URL matches what we requested.
									const current = _store.getBlock( blockId );
									if ( ! current || current.attributes?.url !== requestedUrl ) return;
									if ( j.success && j.data ) {
										_store.updateBlock( blockId, {
											url:          requestedUrl,
											html:         j.data.html || '',
											title:        j.data.title || '',
											description:  j.data.description || '',
											thumbnailUrl: j.data.thumbnailUrl || j.data.thumbnail_url || '',
											providerName: j.data.provider_name || j.data.providerName || '',
											_source:      j.data._source || '',
											platform:     j.platform || '',
										} );
									}
								} )
								.catch( ( err ) => {
									console.warn( '[VisualEditor] Embed resolve failed:', err );
								} );
								return;
							}

							// Map Embed: changing the address should trigger
							// a geocode to update the coordinates.
							if ( 'address' === field && block && 'map-embed' === block.type && value ) {
								const requestedAddress = value;
								const _store           = this;
								this.updateBlock( blockId, { address: value } );
								fetch( '/api/visual-editor/geocode?q=' + encodeURIComponent( requestedAddress ), {
									headers: { 'Accept': 'application/json' },
								} )
								.then( ( r ) => r.json() )
								.then( ( j ) => {
									// Guard against stale responses.
									const current = _store.getBlock( blockId );
									if ( ! current || current.attributes?.address !== requestedAddress ) return;
									if ( j.success && j.results && j.results.length > 0 ) {
										_store.updateBlock( blockId, {
											address:   j.results[0].display_name || requestedAddress,
											latitude:  j.results[0].lat,
											longitude: j.results[0].lon,
										} );
									}
								} )
								.catch( ( err ) => {
									console.warn( '[VisualEditor] Geocode request failed:', err );
								} );
								return;
							}

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
							meta: JSON.parse( JSON.stringify( this.meta ) ),
							globalStyles: JSON.parse( JSON.stringify( this.globalStyles ) ),
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
			store._pendingDirty   = false;
			store.autosave        = {{ Js::from( $autosave ) }};
			store.autosaveInterval = {{ Js::from( $autosaveInterval ) }};
			store.documentStatus   = {{ Js::from( $documentStatus ) }};
			store.scheduledDate    = {{ Js::from( $scheduledDate ) }};
			store.patterns         = {{ Js::from( $patterns ) }};
			store.blockTransforms  = {{ Js::from( $blockTransforms ) }};
			store.blockVariations  = {{ Js::from( $blockVariations ) }};
			store.defaultBlockType = {{ Js::from( $defaultBlockType ) }};
			store.meta             = {{ Js::from( $initialMeta ) }};
			store.globalStyles     = { palette: {{ Js::from( $initialPalette ) }}, typography: {{ Js::from( $initialTypography ) }}, spacing: {{ Js::from( $initialSpacing ) }} };
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
