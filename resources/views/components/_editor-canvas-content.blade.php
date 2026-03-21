				{{-- Dynamic canvas: renders blocks from the Alpine store reactively --}}
				<x-ve-editor-canvas :enable-drag-reorder="true">
					<div x-data="{
						preRendered: {{ Js::from( $renderedBlocks ) }},
						defaultTemplates: {{ Js::from( $defaultBlockTemplates ) }},
						blockNames: {{ Js::from( $transformableBlocks ) }},
						featuredImageUrl: {{ Js::from( $featuredImageUrl ?? '' ) }},
						draggingBlockId: null,
						blockAlignSupports: {{ Js::from( $blockAlignSupports ) }},
						inserterBlocks: {{ Js::from( $inserterBlocks ) }},
						inserterBlockIcons: {{ Js::from( $toolbarBlockIcons ) }},
						inserterPopover: { open: false, index: -1 },
						innerInserterPopover: { open: false, parentId: null, index: -1, top: 0, left: 0 },

						openInserterPopover( index ) {
							this.inserterPopover = { open: true, index: index };
							this.$nextTick( () => {
								const input = this.$el.querySelector( '.ve-inserter-popover-search' );
								if ( input ) input.focus();
							} );
						},

						insertFromPopover( blockType ) {
							if ( ! Alpine.store( 'editor' ) ) return;
							const blockDef     = this.inserterBlocks.find( ( b ) => b.name === blockType );
							const defaultInner = ( blockDef && blockDef.defaultInnerBlocks ) ? blockDef.defaultInnerBlocks : [];
							const newBlock = Alpine.store( 'editor' ).addBlock( { type: blockType, innerBlocks: defaultInner }, this.inserterPopover.index );
							this.inserterPopover.open = false;
							if ( newBlock ) {
								this.$nextTick( () => {
									const el = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
									if ( el ) { el.focus(); }
									if ( Alpine.store( 'selection' ) ) {
										Alpine.store( 'selection' ).select( newBlock.id, false );
									}
								} );
							}
						},

						insertInnerFromPopover( blockType ) {
							if ( ! Alpine.store( 'editor' ) ) return;
							const pop = this.innerInserterPopover;
							const newBlock = Alpine.store( 'editor' ).addInnerBlock( pop.parentId, { type: blockType }, pop.index );
							this.innerInserterPopover.open = false;
							if ( newBlock ) {
								this.$nextTick( () => {
									const newEl = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
									if ( ! newEl ) {
										const fallback = document.querySelector( '[data-inner-block-id=' + newBlock.id + ']' );
										if ( fallback ) { fallback.focus(); }
									} else {
										newEl.focus();
									}
									if ( Alpine.store( 'selection' ) ) {
										Alpine.store( 'selection' ).select( newBlock.id, false );
									}
								} );
							}
						},

						destroy() {
							if ( this._onDragStart ) {
								document.removeEventListener( 'dragstart', this._onDragStart );
							}
							if ( this._onDragEnd ) {
								document.removeEventListener( 'dragend', this._onDragEnd );
							}
						},

						init() {
							// Capture dragstart from the toolbar drag handle (which is
							// outside this x-data scope) via a document-level listener.
							// Ignore drags originating from the layer panel — it handles
							// its own drag-and-drop internally.
							this._onDragStart = ( e ) => {
								if ( e.target.closest( '.ve-layer-panel' ) ) {
									return;
								}

								// Skip inner drag handles — they have their own handler.
								if ( e.target.closest( '[data-ve-inner-drag-handle]' ) ) {
									return;
								}

								// Check if it came from a block wrapper or the toolbar drag handle.
								const blockEl = e.target.closest( '[data-block-id]' );
								let blockId   = blockEl ? blockEl.getAttribute( 'data-block-id' ) : null;

								// If no block wrapper found, this is the toolbar drag handle —
								// read the currently focused block from the selection store.
								if ( ! blockId && Alpine.store( 'selection' )?.focused ) {
									blockId = Alpine.store( 'selection' ).focused;
								}

								if ( blockId ) {
									this.draggingBlockId = blockId;
									e.dataTransfer.setData( 'text/plain', blockId );
									e.dataTransfer.effectAllowed = 'move';

									// Add opacity to the source block when dragging a
								// container child (column, grid-item, or any block with
								// an allowedParents constraint from metadata).
									const store       = Alpine.store( 'editor' );
									const draggedBlock = store ? store.getBlock( blockId ) : null;
									if ( draggedBlock ) {
										const meta = Alpine.store( 'blockRenderers' )?.getMeta( draggedBlock.type );
										if ( meta && meta.allowedParents ) {
											const dragEl = document.querySelector( '.ve-block-' + draggedBlock.type + '[data-block-id=\'' + blockId + '\']' );
											if ( dragEl ) {
												requestAnimationFrame( () => { dragEl.style.opacity = '0.5'; } );
											}
										}
									}
								}
							};

							this._onDragEnd = () => {
								this.draggingBlockId = null;
								// Clean up opacity and drop indicators for all container
								// child blocks generically (column, grid-item, etc.).
								document.querySelectorAll( '[data-block-id][data-parent-id]' ).forEach( ( el ) => {
									el.style.opacity = '';
									el.classList.remove( 've-col-drop-before', 've-col-drop-after', 've-grid-item-drop-before', 've-grid-item-drop-after', 've-child-drop-before', 've-child-drop-after' );
								} );
							};

							document.addEventListener( 'dragstart', this._onDragStart );
							document.addEventListener( 'dragend', this._onDragEnd );

							// Mark empty contenteditable elements so CSS placeholders
							// show when the block has no content and is not focused.
							const el = this.$el;

							// Inner block insert button handler — opens a popover for block type selection.
							el.addEventListener( 'click', ( e ) => {
								const insertBtn = e.target.closest( '[data-ve-inner-insert]' );
								if ( ! insertBtn ) return;

								const parentId = insertBtn.getAttribute( 'data-parent-id' );
								const index    = parseInt( insertBtn.getAttribute( 'data-insert-index' ), 10 );
								const rect     = insertBtn.getBoundingClientRect();
								const parentRect = this.$el.offsetParent
									? this.$el.offsetParent.getBoundingClientRect()
									: { top: 0, left: 0 };

								this.innerInserterPopover = {
									open: true,
									parentId: parentId,
									index: index,
									top: rect.bottom - parentRect.top + 4,
									left: rect.left - parentRect.left + ( rect.width / 2 ) - 112,
								};
							} );

							// Group variation picker: set the variation on a fresh group block.
							el.addEventListener( 'click', ( e ) => {
								const variationBtn = e.target.closest( '[data-ve-set-variation]' );
								if ( ! variationBtn ) return;

								const variation = variationBtn.getAttribute( 'data-ve-set-variation' );
								const blockEl   = variationBtn.closest( '[data-block-id]' );
								if ( ! blockEl ) return;

								const blockId = blockEl.getAttribute( 'data-block-id' );
								const store   = Alpine.store( 'editor' );
								if ( ! store ) return;

								const variationMap = {
									group: { flexDirection: 'column', flexWrap: 'nowrap', _groupVariation: 'group' },
									row:   { flexDirection: 'row', flexWrap: 'nowrap', justifyContent: 'flex-start', _groupVariation: 'row' },
									stack: { flexDirection: 'column', flexWrap: 'nowrap', _groupVariation: 'stack' },
								};

								const attrs = variationMap[ variation ] || variationMap.group;
								store.updateBlock( blockId, attrs );

								// Add an empty inner block so the placeholder appears.
								const newInner = {
									id: Alpine.store( 'editor' ).generateBlockId(),
									type: Alpine.store( 'editor' ).defaultBlockType,
									attributes: { text: '' },
									innerBlocks: [],
								};
								store.addInnerBlock( blockId, newInner, 0 );
							} );

							// Columns layout picker: create column child blocks from the selected layout.
							el.addEventListener( 'click', ( e ) => {
								const layoutBtn = e.target.closest( '[data-ve-set-columns-layout]' );
								if ( ! layoutBtn ) return;

								const layout  = layoutBtn.getAttribute( 'data-ve-set-columns-layout' );
								const blockEl = layoutBtn.closest( '[data-block-id]' );
								if ( ! blockEl ) return;

								const blockId = blockEl.getAttribute( 'data-block-id' );
								const store   = Alpine.store( 'editor' );
								if ( ! store ) return;

								const layoutMap = {
									'100':      [ '' ],
									'50-50':    [ '', '' ],
									'33-66':    [ '33.33%', '66.66%' ],
									'66-33':    [ '66.66%', '33.33%' ],
									'33-33-33': [ '', '', '' ],
									'25-50-25': [ '25%', '50%', '25%' ],
								};

								let widths;
								if ( 'skip' === layout ) {
									const block  = store.getBlock( blockId );
									const colVal = block?.attributes?.columns;
									const count  = typeof colVal === 'object' ? ( colVal.global ?? colVal.desktop ?? 2 ) : ( parseInt( colVal ) || 2 );
									widths = Array( count ).fill( '' );
								} else {
									widths = layoutMap[ layout ] || [ '50%', '50%' ];
								}

								// Update columns count to match the layout.
								const block     = store.getBlock( blockId );
								const curColVal = block?.attributes?.columns;
								const colObj    = typeof curColVal === 'object'
									? { ...curColVal, global: widths.length, desktop: widths.length }
									: { mode: 'global', global: widths.length, desktop: widths.length, tablet: Math.min( widths.length, 2 ), mobile: 1 };
								store.updateBlock( blockId, { columns: colObj } );

								// Create column child blocks with appropriate widths.
								// Each column starts with a paragraph inner block
								// so the user can type and use slash commands immediately
								// (same pattern as the group variation picker).
								widths.forEach( ( w, idx ) => {
									const colBlock = {
										id: Alpine.store( 'editor' ).generateBlockId() + '-col-' + idx,
										type: 'column',
										attributes: { width: w, verticalAlignment: 'top' },
										innerBlocks: [
											{
												id: Alpine.store( 'editor' ).generateBlockId() + '-col-' + idx + '-p',
												type: Alpine.store( 'editor' ).defaultBlockType,
												attributes: { text: '' },
												innerBlocks: [],
											},
										],
									};
									store.addInnerBlock( blockId, colBlock, idx );
								} );
							} );

							// Add column button: append a new column to a columns block.
							el.addEventListener( 'click', ( e ) => {
								const addBtn = e.target.closest( '[data-ve-add-column]' );
								if ( ! addBtn ) return;

								const parentId = addBtn.getAttribute( 'data-parent-id' );
								if ( ! parentId ) return;

								const store = Alpine.store( 'editor' );
								if ( ! store ) return;

								const block = store.getBlock( parentId );
								if ( ! block ) return;

								const newCol = {
									id: Alpine.store( 'editor' ).generateBlockId() + '-col-' + ( ( block.innerBlocks || [] ).length ),
									type: 'column',
									attributes: { width: '', verticalAlignment: 'top' },
									innerBlocks: [
										{
											id: Alpine.store( 'editor' ).generateBlockId() + '-col-p',
											type: Alpine.store( 'editor' ).defaultBlockType,
											attributes: { text: '' },
											innerBlocks: [],
										},
									],
								};

								store.addInnerBlock( parentId, newCol );
								const newCount   = ( block.innerBlocks || [] ).length;
								const curColData = block?.attributes?.columns;
								const updatedCol = typeof curColData === 'object'
									? { ...curColData, global: newCount, desktop: newCount }
									: { mode: 'global', global: newCount, desktop: newCount, tablet: Math.min( newCount, 2 ), mobile: 1 };
								store.updateBlock( parentId, { columns: updatedCol } );

								// Clear all sibling column widths so they redistribute equally via flex: 1.
								( block.innerBlocks || [] ).forEach( ( col ) => {
									if ( 'column' === col.type ) {
										store.updateBlock( col.id, { width: '' } );
									}
								} );
							} );

							// Grid layout picker: create grid-item child blocks from the selected layout.
							el.addEventListener( 'click', ( e ) => {
								const layoutBtn = e.target.closest( '[data-ve-set-grid-layout]' );
								if ( ! layoutBtn ) return;

								const layout  = layoutBtn.getAttribute( 'data-ve-set-grid-layout' );
								const blockEl = layoutBtn.closest( '[data-block-id]' );
								if ( ! blockEl ) return;

								const blockId = blockEl.getAttribute( 'data-block-id' );
								const store   = Alpine.store( 'editor' );
								if ( ! store ) return;

								// Map layout presets to { columns, count }.
								const layoutMap = {
									'2':   { columns: 2, count: 2 },
									'3':   { columns: 3, count: 3 },
									'4':   { columns: 4, count: 4 },
									'2x2': { columns: 2, count: 4 },
									'3x2': { columns: 3, count: 6 },
									'3x3': { columns: 3, count: 9 },
								};

								let preset;
								if ( 'custom' === layout ) {
									// Custom: use existing column count, create that many items.
									const block = store.getBlock( blockId );
									const cols  = parseInt( block?.attributes?.columns?.global || block?.attributes?.columns ) || 3;
									preset = { columns: cols, count: cols };
								} else {
									preset = layoutMap[ layout ] || { columns: 3, count: 3 };
								}

								// Update grid columns attribute.
								store.updateBlock( blockId, {
									columns: { mode: 'global', global: preset.columns, desktop: preset.columns, tablet: Math.min( preset.columns, 2 ), mobile: 1 },
								} );

								// Create grid-item child blocks, each with an empty paragraph.
								for ( let i = 0; i < preset.count; i++ ) {
									const itemBlock = {
										id: Alpine.store( 'editor' ).generateBlockId() + '-gi-' + i,
										type: 'grid-item',
										attributes: {
											columnSpan: { mode: 'global', global: 1, desktop: 1, tablet: 1, mobile: 1 },
											rowSpan: { mode: 'global', global: 1, desktop: 1, tablet: 1, mobile: 1 },
											verticalAlignment: 'stretch',
										},
										innerBlocks: [
											{
												id: Alpine.store( 'editor' ).generateBlockId() + '-gi-' + i + '-p',
												type: Alpine.store( 'editor' ).defaultBlockType,
												attributes: { text: '' },
												innerBlocks: [],
											},
										],
									};
									store.addInnerBlock( blockId, itemBlock, i );
								}
							} );

							// Add grid item button: append a new grid-item to a grid block.
							el.addEventListener( 'click', ( e ) => {
								const addBtn = e.target.closest( '[data-ve-add-grid-item]' );
								if ( ! addBtn ) return;

								const parentId = addBtn.getAttribute( 'data-parent-id' );
								if ( ! parentId ) return;

								const store = Alpine.store( 'editor' );
								if ( ! store ) return;

								const block = store.getBlock( parentId );
								if ( ! block ) return;

								const newItem = {
									id: Alpine.store( 'editor' ).generateBlockId() + '-gi-' + ( ( block.innerBlocks || [] ).length ),
									type: 'grid-item',
									attributes: {
										columnSpan: { mode: 'global', global: 1, desktop: 1, tablet: 1, mobile: 1 },
										rowSpan: { mode: 'global', global: 1, desktop: 1, tablet: 1, mobile: 1 },
										verticalAlignment: 'stretch',
									},
									innerBlocks: [
										{
											id: Alpine.store( 'editor' ).generateBlockId() + '-gi-p',
											type: Alpine.store( 'editor' ).defaultBlockType,
											attributes: { text: '' },
											innerBlocks: [],
										},
									],
								};

								store.addInnerBlock( parentId, newItem );
							} );

							// Add inner block button: append a new block of the specified type to a container.
							el.addEventListener( 'click', ( e ) => {
								const addBtn = e.target.closest( '[data-ve-add-inner-block]' );
								if ( ! addBtn ) return;

								const parentId  = addBtn.getAttribute( 'data-parent-id' );
								const blockType = addBtn.getAttribute( 'data-block-type' );
								if ( ! parentId || ! blockType ) return;

								const store = Alpine.store( 'editor' );
								if ( ! store ) return;

								const block = store.getBlock( parentId );
								if ( ! block ) return;

								const newBlock = {
									type: blockType,
									attributes: {},
									innerBlocks: [],
								};

								store.addInnerBlock( parentId, newBlock );
							} );

							// Generic panel switcher: show one panel, hide siblings.
							// Trigger: data-ve-show-panel, Container: data-ve-panel-group, Panels: data-ve-panel.
							el.addEventListener( 'click', ( e ) => {
								const trigger = e.target.closest( '[data-ve-show-panel]' );
								if ( ! trigger ) return;

								// Don't switch panels when clicking editable labels inside the trigger.
								if ( e.target.hasAttribute( 'contenteditable' ) ) return;

								const idx   = parseInt( trigger.getAttribute( 'data-ve-show-panel' ), 10 );
								const group = trigger.closest( '[data-ve-panel-group]' );
								if ( ! group ) return;

								// Toggle panels.
								group.querySelectorAll( '[data-ve-panel]' ).forEach( ( panel ) => {
									panel.style.display = parseInt( panel.getAttribute( 'data-ve-panel' ), 10 ) === idx ? '' : 'none';
								} );

								// Toggle active class on triggers.
								group.querySelectorAll( '[data-ve-show-panel]' ).forEach( ( btn ) => {
									btn.classList.toggle( 'tab-active', parseInt( btn.getAttribute( 'data-ve-show-panel' ), 10 ) === idx );
								} );
							} );

							// Generic section toggler: toggle content visibility.
							// Trigger: data-ve-toggle-section, Section: data-ve-section, Content: data-ve-section-content.
							el.addEventListener( 'click', ( e ) => {
								const trigger = e.target.closest( '[data-ve-toggle-section]' );
								if ( ! trigger ) return;

								// Don't toggle when clicking editable titles inside the trigger.
								if ( e.target.hasAttribute( 'contenteditable' ) ) return;

								const section = trigger.closest( '[data-ve-section]' );
								if ( ! section ) return;

								const content = section.querySelector( '[data-ve-section-content]' );
								if ( ! content ) return;

								const isHidden = 'none' === content.style.display;
								content.style.display = isHidden ? '' : 'none';
								trigger.setAttribute( 'aria-expanded', isHidden ? 'true' : 'false' );

								// Rotate icon if present.
								const icon = trigger.querySelector( '.ve-accordion-icon' );
								if ( icon ) {
									icon.style.transform = isHidden ? 'rotate(90deg)' : '';
								}
							} );

							// Embed / Social resolve button: fetch oEmbed via API.
							el.addEventListener( 'click', ( e ) => {
								const resolveBtn = e.target.closest( '[data-ve-resolve-embed]' );
								if ( ! resolveBtn ) return;

								const blockId      = resolveBtn.getAttribute( 'data-ve-resolve-embed' );
								const wrapper      = resolveBtn.closest( '.ve-block' );
								const input        = wrapper?.querySelector( '[data-ve-url-input]' );
								const requestedUrl = ( input?.value || '' ).trim();
								if ( ! requestedUrl ) return;

								resolveBtn.disabled = true;
								const spinner = resolveBtn.querySelector( '.ve-resolve-spinner' );
								const label   = resolveBtn.querySelector( '.ve-resolve-label' );
								if ( spinner ) spinner.style.display = '';
								if ( label ) label.style.display = 'none';

								fetch( '/api/visual-editor/embed/resolve', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
									body: JSON.stringify( { url: requestedUrl } ),
								} )
								.then( ( r ) => r.json() )
								.then( ( j ) => {
									// Guard against stale responses: bail if the block
									// no longer exists, its stored URL changed, or the
									// user typed a different URL into the input since
									// the request was fired.
									const current = Alpine.store( 'editor' )?.getBlock( blockId );
									if ( ! current ) return;
									if ( current.attributes?.url && current.attributes.url !== requestedUrl ) return;
									const latestInput = ( input?.value || '' ).trim();
									if ( latestInput && latestInput !== requestedUrl ) return;
									if ( j.success && j.data ) {
										Alpine.store( 'editor' ).updateBlock( blockId, {
											url:          requestedUrl,
											html:         j.data.html || '',
											title:        j.data.title || '',
											description:  j.data.description || '',
											thumbnailUrl: j.data.thumbnailUrl || j.data.thumbnail_url || '',
											providerName: j.data.provider_name || j.data.providerName || '',
											providerUrl:  j.data.provider_url || j.data.providerUrl || '',
											_source:      j.data._source || '',
											platform:     j.platform || '',
										} );
									} else {
										const msg  = wrapper?.querySelector( '.ve-resolve-error' );
										const hint = wrapper?.querySelector( '.ve-resolve-hint' );
										if ( msg ) msg.style.display = '';
										if ( hint ) hint.style.display = 'none';
									}
								} )
								.catch( () => {
									const msg  = wrapper?.querySelector( '.ve-resolve-error' );
									const hint = wrapper?.querySelector( '.ve-resolve-hint' );
									if ( msg ) msg.style.display = '';
									if ( hint ) hint.style.display = 'none';
								} )
								.finally( () => {
									resolveBtn.disabled = false;
									if ( spinner ) spinner.style.display = 'none';
									if ( label ) label.style.display = '';
								} );
							} );

							// Track map input values outside the DOM so they survive
							// x-html re-renders that replace the input elements.
							const _mapDraft = {};
							el.addEventListener( 'input', ( e ) => {
								const addrIn = e.target.closest( '[data-ve-map-address]' );
								if ( addrIn ) {
									const btn = addrIn.closest( '.ve-block' )?.querySelector( '[data-ve-map-search]' );
									if ( btn ) {
										const id = btn.getAttribute( 'data-ve-map-search' );
										if ( ! _mapDraft[ id ] ) _mapDraft[ id ] = {};
										_mapDraft[ id ].address = addrIn.value;
									}
									return;
								}
								const latIn = e.target.closest( '[data-ve-map-lat]' );
								if ( latIn ) {
									const btn = latIn.closest( '.ve-block' )?.querySelector( '[data-ve-map-set-coords]' );
									if ( btn ) {
										const id = btn.getAttribute( 'data-ve-map-set-coords' );
										if ( ! _mapDraft[ id ] ) _mapDraft[ id ] = {};
										_mapDraft[ id ].lat = latIn.value;
									}
									return;
								}
								const lngIn = e.target.closest( '[data-ve-map-lng]' );
								if ( lngIn ) {
									const btn = lngIn.closest( '.ve-block' )?.querySelector( '[data-ve-map-set-coords]' );
									if ( btn ) {
										const id = btn.getAttribute( 'data-ve-map-set-coords' );
										if ( ! _mapDraft[ id ] ) _mapDraft[ id ] = {};
										_mapDraft[ id ].lng = lngIn.value;
									}
								}
							} );

							// Map search button: geocode via Nominatim.
							el.addEventListener( 'click', ( e ) => {
								const mapBtn = e.target.closest( '[data-ve-map-search]' );
								if ( ! mapBtn ) return;

								const blockId = mapBtn.getAttribute( 'data-ve-map-search' );
								const wrapper = mapBtn.closest( '.ve-block' );
								const input   = wrapper?.querySelector( '[data-ve-map-address]' );

								// Read from DOM first, fall back to tracked draft value.
								const requestedAddress = ( input?.value || _mapDraft[ blockId ]?.address || '' ).trim();
								if ( ! requestedAddress ) return;

								// Clean up draft value for this block.
								delete _mapDraft[ blockId ];

								// Hide any previous error, show spinner.
								const errorEl = wrapper?.querySelector( '.ve-map-error' );
								if ( errorEl ) errorEl.style.display = 'none';

								mapBtn.disabled = true;
								const spinner = mapBtn.querySelector( '.ve-resolve-spinner' );
								const label   = mapBtn.querySelector( '.ve-resolve-label' );
								if ( spinner ) spinner.style.display = '';
								if ( label ) label.style.display = 'none';

								fetch( '/api/visual-editor/geocode?q=' + encodeURIComponent( requestedAddress ), {
									headers: { 'Accept': 'application/json' },
								} )
									.then( ( r ) => r.json() )
									.then( ( j ) => {
										if ( j.success && j.results && j.results.length > 0 && Alpine.store( 'editor' ) ) {
											// Skip if the user changed the address while the
											// request was in flight, or if the user manually
											// entered coordinates and the address is unchanged.
											const blk = Alpine.store( 'editor' ).getBlock( blockId );
											const currentAddress = ( input?.value || _mapDraft[ blockId ]?.address || '' ).trim();
											if ( currentAddress && currentAddress !== requestedAddress ) return;
											if ( blk && blk.attributes?.latitude && blk.attributes?.longitude && ( ! currentAddress || currentAddress === blk.attributes?.address ) ) return;
											Alpine.store( 'editor' ).updateBlock( blockId, {
												address:   j.results[0].display_name || requestedAddress,
												latitude:  j.results[0].lat,
												longitude: j.results[0].lon,
											} );
										} else if ( errorEl ) {
											errorEl.style.display = '';
										}
									} )
									.catch( () => {
										if ( errorEl ) errorEl.style.display = '';
									} )
									.finally( () => {
										mapBtn.disabled = false;
										if ( spinner ) spinner.style.display = 'none';
										if ( label ) label.style.display = '';
									} );
							} );

							// Map manual coordinates button.
							el.addEventListener( 'click', ( e ) => {
								const coordBtn = e.target.closest( '[data-ve-map-set-coords]' );
								if ( ! coordBtn ) return;

								const blockId = coordBtn.getAttribute( 'data-ve-map-set-coords' );
								const wrapper = coordBtn.closest( '.ve-block' );
								const latIn   = wrapper?.querySelector( '[data-ve-map-lat]' );
								const lngIn   = wrapper?.querySelector( '[data-ve-map-lng]' );

								// Read from DOM first, fall back to tracked draft values.
								// Validate as finite numbers within valid coordinate ranges.
								const latNum = parseFloat( ( latIn?.value || _mapDraft[ blockId ]?.lat || '' ).trim() );
								const lngNum = parseFloat( ( lngIn?.value || _mapDraft[ blockId ]?.lng || '' ).trim() );

								if ( Number.isFinite( latNum ) && Number.isFinite( lngNum )
									&& latNum >= -90 && latNum <= 90
									&& lngNum >= -180 && lngNum <= 180
									&& Alpine.store( 'editor' ) ) {
									delete _mapDraft[ blockId ];
									Alpine.store( 'editor' ).updateBlock( blockId, { latitude: String( latNum ), longitude: String( lngNum ) } );
								}
							} );

							// Inner block drag handle: start dragging an inner block.
							el.addEventListener( 'dragstart', ( e ) => {
								const handle = e.target.closest( '[data-ve-inner-drag-handle]' );
								if ( ! handle ) return;

								const dragId = handle.getAttribute( 'data-inner-drag-id' );
								if ( dragId ) {
									this.draggingBlockId = dragId;
									e.dataTransfer.setData( 'text/plain', dragId );
									e.dataTransfer.effectAllowed = 'move';

									const wrapper = handle.closest( '[data-inner-block-id]' );
									if ( wrapper ) {
										requestAnimationFrame( () => wrapper.classList.add( 'opacity-50' ) );
									}
								}
							} );

							el.addEventListener( 'dragend', ( e ) => {
								// Remove opacity from any inner block wrapper.
								el.querySelectorAll( '.ve-inner-block-wrapper.opacity-50' ).forEach( ( w ) => {
									w.classList.remove( 'opacity-50' );
								} );
								// Remove drop indicators and opacity from all container
								// child blocks (column, grid-item, and custom containers).
								el.querySelectorAll( '[data-block-id][data-parent-id]' ).forEach( ( child ) => {
									child.classList.remove( 've-col-drop-before', 've-col-drop-after', 've-grid-item-drop-before', 've-grid-item-drop-after', 've-child-drop-before', 've-child-drop-after' );
									child.style.opacity = '';
								} );
							} );

							// Drag-and-drop into / within inner blocks containers.
							// Uses block metadata to generalize the behavior for any
							// container block type (column, grid-item, or custom).
							el.addEventListener( 'dragover', ( e ) => {
								if ( ! this.draggingBlockId ) return;

								const store        = Alpine.store( 'editor' );
								const renderers    = Alpine.store( 'blockRenderers' );
								const draggedBlock = store ? store.getBlock( this.draggingBlockId ) : null;
								const draggedMeta  = draggedBlock && renderers ? renderers.getMeta( draggedBlock.type ) : null;

								// Container child reorder visual feedback.
								// If the dragged block has allowedParents metadata, look
								// for a sibling target of the same type under the same
								// parent type.
								if ( draggedMeta && draggedMeta.allowedParents ) {
									const targetChildEl = e.target.closest( '.ve-block-' + draggedBlock.type + '[data-block-id]' );
									if ( targetChildEl && targetChildEl.getAttribute( 'data-block-id' ) !== this.draggingBlockId ) {
										e.preventDefault();
										e.stopPropagation();
										// Clear previous indicators for this child type.
										el.querySelectorAll( '.ve-block-' + draggedBlock.type ).forEach( ( c ) => {
											c.classList.remove( 've-col-drop-before', 've-col-drop-after', 've-grid-item-drop-before', 've-grid-item-drop-after', 've-child-drop-before', 've-child-drop-after' );
										} );
										// Determine indicator classes based on block type.
										const dropCls = 'column' === draggedBlock.type
											? { before: 've-col-drop-before', after: 've-col-drop-after' }
											: ( 'grid-item' === draggedBlock.type
												? { before: 've-grid-item-drop-before', after: 've-grid-item-drop-after' }
												: { before: 've-child-drop-before', after: 've-child-drop-after' } );
										// Show left or right indicator based on cursor position.
										const rect = targetChildEl.getBoundingClientRect();
										const midX = rect.left + ( rect.width / 2 );
										targetChildEl.classList.add( e.clientX > midX ? dropCls.after : dropCls.before );
										return;
									}
								}

								// Highlight individual inner block targets for reorder.
								const innerBlockTarget = e.target.closest( '[data-inner-block-id]' );
								if ( innerBlockTarget && innerBlockTarget.getAttribute( 'data-inner-block-id' ) !== this.draggingBlockId ) {
									e.preventDefault();
									e.stopPropagation();
									innerBlockTarget.classList.add( 'border-t-2', 'border-primary' );
									return;
								}

								// Highlight container for drops from outside.
								const innerBlocksEl = e.target.closest( '[data-ve-inner-blocks]' );
								if ( innerBlocksEl ) {
									e.preventDefault();
									e.stopPropagation();
									innerBlocksEl.classList.add( 'ring-2', 'ring-info', 'ring-offset-1' );
								}
							} );

							el.addEventListener( 'dragleave', ( e ) => {
								// Generic container child indicator cleanup.
								const childEl = e.target.closest( '[data-block-id][data-parent-id]' );
								if ( childEl ) {
									childEl.classList.remove( 've-col-drop-before', 've-col-drop-after', 've-grid-item-drop-before', 've-grid-item-drop-after', 've-child-drop-before', 've-child-drop-after' );
								}
								const innerBlockTarget = e.target.closest( '[data-inner-block-id]' );
								if ( innerBlockTarget ) {
									innerBlockTarget.classList.remove( 'border-t-2', 'border-primary' );
								}
								const innerBlocksEl = e.target.closest( '[data-ve-inner-blocks]' );
								if ( innerBlocksEl ) {
									innerBlocksEl.classList.remove( 'ring-2', 'ring-info', 'ring-offset-1' );
								}
							} );

							el.addEventListener( 'drop', ( e ) => {
								if ( ! this.draggingBlockId ) return;

								const store     = Alpine.store( 'editor' );
								const renderers = Alpine.store( 'blockRenderers' );

								// Clean up drop indicators and opacity for all
								// container child blocks generically.
								el.querySelectorAll( '[data-block-id][data-parent-id]' ).forEach( ( child ) => {
									child.classList.remove( 've-col-drop-before', 've-col-drop-after', 've-grid-item-drop-before', 've-grid-item-drop-after', 've-child-drop-before', 've-child-drop-after' );
									child.style.opacity = '';
								} );

								// Generic container child reorder / cross-parent move.
								// Uses block metadata (allowedParents) to determine if
								// the dragged block is a container child that should use
								// sibling-reorder logic instead of inner-block logic.
								const draggedBlock  = store.getBlock( this.draggingBlockId );
								const draggedParent = store.getParentBlock( this.draggingBlockId );
								const draggedMeta   = draggedBlock && renderers ? renderers.getMeta( draggedBlock.type ) : null;

								if ( draggedMeta && draggedMeta.allowedParents && draggedParent ) {
									const targetChildEl = e.target.closest( '.ve-block-' + draggedBlock.type + '[data-block-id]' );
									if ( targetChildEl ) {
										const targetChildId = targetChildEl.getAttribute( 'data-block-id' );
										if ( targetChildId !== this.draggingBlockId ) {
											const targetChildParent = store.getParentBlock( targetChildId );
											if ( targetChildParent && draggedMeta.allowedParents.includes( targetChildParent.type ) ) {
												e.preventDefault();
												e.stopPropagation();

												// Determine insert position from cursor.
												const rect      = targetChildEl.getBoundingClientRect();
												const midX      = rect.left + ( rect.width / 2 );
												const targetIdx = targetChildParent.innerBlocks.findIndex( ( b ) => b.id === targetChildId );
												const dropIdx   = e.clientX > midX ? targetIdx + 1 : targetIdx;

												if ( targetChildParent.id === draggedParent.id ) {
													// Same parent: reorder within the container.
													store.moveInnerBlock( draggedParent.id, this.draggingBlockId, dropIdx );
												} else {
													// Different parent: move to another container.
													const blockData = JSON.parse( JSON.stringify( draggedBlock ) );
													store.removeInnerBlock( draggedParent.id, this.draggingBlockId );

													// Update the source container count if it tracks columns.
													if ( 'columns' === draggedParent.type ) {
														const srcColData = draggedParent.attributes?.columns;
														const srcCount   = draggedParent.innerBlocks.length;
														const srcUpdated = typeof srcColData === 'object'
															? { ...srcColData, global: srcCount, desktop: srcCount }
															: { mode: 'global', global: srcCount, desktop: srcCount, tablet: Math.min( srcCount, 2 ), mobile: 1 };
														store.updateBlock( draggedParent.id, { columns: srcUpdated } );
													}

													store.addInnerBlock( targetChildParent.id, {
														type: blockData.type,
														attributes: blockData.attributes,
														innerBlocks: blockData.innerBlocks || [],
													}, dropIdx );

													// Update the target container count if it tracks columns.
													if ( 'columns' === targetChildParent.type ) {
														const tgtColData = targetChildParent.attributes?.columns;
														const tgtCount   = targetChildParent.innerBlocks.length;
														const tgtUpdated = typeof tgtColData === 'object'
															? { ...tgtColData, global: tgtCount, desktop: tgtCount }
															: { mode: 'global', global: tgtCount, desktop: tgtCount, tablet: Math.min( tgtCount, 2 ), mobile: 1 };
														store.updateBlock( targetChildParent.id, { columns: tgtUpdated } );
													}
												}

												this.draggingBlockId = null;
												return;
											}
										}
									}
								}

								// Drop onto a specific inner block (reorder).
								const innerBlockTarget = e.target.closest( '[data-inner-block-id]' );
								if ( innerBlockTarget ) {
									const targetId  = innerBlockTarget.getAttribute( 'data-inner-block-id' );
									const parentId  = innerBlockTarget.getAttribute( 'data-parent-id' );
									innerBlockTarget.classList.remove( 'border-t-2', 'border-primary' );

									if ( targetId === this.draggingBlockId || ! parentId ) return;

									e.preventDefault();
									e.stopPropagation();

									const dragParent = store.getParentBlock( this.draggingBlockId );

									// Same parent: reorder within the inner blocks array.
									if ( dragParent && dragParent.id === parentId ) {
										const targetIdx = dragParent.innerBlocks.findIndex( ( b ) => b.id === targetId );
										if ( -1 !== targetIdx ) {
											store.moveInnerBlock( parentId, this.draggingBlockId, targetIdx );
										}
									} else {
										// Different parent or top-level: move into inner blocks.
										const block     = store.getBlock( this.draggingBlockId );
										if ( ! block ) return;
										const blockData = JSON.parse( JSON.stringify( block ) );

										if ( dragParent ) {
											store.removeInnerBlock( dragParent.id, this.draggingBlockId );
										} else {
											store.removeBlock( this.draggingBlockId );
										}

										const parent   = store.getBlock( parentId );
										const targetIdx = parent?.innerBlocks?.findIndex( ( b ) => b.id === targetId ) ?? -1;
										store.addInnerBlock( parentId, {
											type: blockData.type,
											attributes: blockData.attributes,
											innerBlocks: blockData.innerBlocks || [],
										}, -1 !== targetIdx ? targetIdx : null );
									}

									this.draggingBlockId = null;
									return;
								}

								// Case 2: Drop onto the inner blocks container (append).
								const innerBlocksEl = e.target.closest( '[data-ve-inner-blocks]' );
								if ( ! innerBlocksEl ) return;

								e.preventDefault();
								e.stopPropagation();
								innerBlocksEl.classList.remove( 'ring-2', 'ring-info', 'ring-offset-1' );

								const parentId = innerBlocksEl.getAttribute( 'data-parent-id' );
								if ( ! parentId ) return;

								const block = store.getBlock( this.draggingBlockId );
								if ( ! block || this.draggingBlockId === parentId ) return;

								const blockData = JSON.parse( JSON.stringify( block ) );

								const parent = store.getParentBlock( this.draggingBlockId );
								if ( parent ) {
									store.removeInnerBlock( parent.id, this.draggingBlockId );
								} else {
									store.removeBlock( this.draggingBlockId );
								}

								store.addInnerBlock( parentId, {
									type: blockData.type,
									attributes: blockData.attributes,
									innerBlocks: blockData.innerBlocks || [],
								} );

								this.draggingBlockId = null;
							} );

							const markEmpty = ( target ) => {
								const text = target.textContent.trim();
								target.classList.toggle( 've-is-empty', 0 === text.length );
							};

							const markAllEmpty = () => {
								el.querySelectorAll( '[contenteditable][data-placeholder]' ).forEach( markEmpty );
								el.querySelectorAll( '[contenteditable] > [data-placeholder]' ).forEach( markEmpty );
							};

							// Initial mark after Alpine has rendered the x-for + x-html.
							this.$nextTick( () => markAllEmpty() );

							// Re-mark whenever the block list changes (add, remove, reorder, update).
							this.$watch( '$store.editor.blocks', () => {
								this.$nextTick( () => markAllEmpty() );
							} );

							// Re-check on blur so placeholder reappears after editing.
							// Also sync inner block and citation content to the store
							// on blur (content lives in the DOM during active editing).
							el.addEventListener( 'focusout', ( e ) => {
								if ( e.target.hasAttribute( 'contenteditable' ) ) {
									markEmpty( e.target );
									// Also mark nested placeholder elements (e.g. li inside ul/ol).
									e.target.querySelectorAll( '[data-placeholder]' ).forEach( markEmpty );
								}

								const store = Alpine.store( 'editor' );
								if ( ! store ) return;

								// Sync inner block text to store on blur.
								const innerWrapper = e.target.hasAttribute( 'data-inner-block-id' )
									? e.target
									: e.target.closest( '[data-inner-block-id]' );
								if ( innerWrapper ) {
									const innerBlockId = innerWrapper.getAttribute( 'data-inner-block-id' );
									const innerBlock   = store.getBlock( innerBlockId );
									const contentEl    = innerWrapper.querySelector( '[contenteditable]' ) || e.target;
									if ( innerBlock ) {
										store.updateBlock( innerBlockId, { text: contentEl.innerHTML } );
									}
								}

								// Sync citation text to store on blur.
								if ( e.target.classList.contains( 've-quote-citation' ) ) {
									const blockEl = e.target.closest( '[data-block-id]' );
									if ( blockEl ) {
										const blockId = blockEl.getAttribute( 'data-block-id' );
										store.updateBlock( blockId, { citation: e.target.innerHTML } );
									}
								}

								// Generic: sync contenteditable text into a parent block array attribute.
								// Uses: data-ve-sync-parent-array, data-parent-id, data-attr-name, data-attr-index, data-attr-field.
								if ( e.target.hasAttribute( 'data-ve-sync-parent-array' ) ) {
									const parentId  = e.target.getAttribute( 'data-parent-id' );
									const attrName  = e.target.getAttribute( 'data-attr-name' );
									const attrIndex = parseInt( e.target.getAttribute( 'data-attr-index' ), 10 );
									const attrField = e.target.getAttribute( 'data-attr-field' );
									if ( parentId && attrName && attrField && ! isNaN( attrIndex ) ) {
										const block = store.getBlock( parentId );
										if ( block ) {
											const arr = [ ...( block.attributes?.[ attrName ] || [] ) ];
											if ( arr[ attrIndex ] ) {
												arr[ attrIndex ] = { ...arr[ attrIndex ], [ attrField ]: e.target.textContent.trim() };
												store.updateBlock( parentId, { [ attrName ]: arr } );
											}
										}
									}
								}

								// Sync details summary text to store on blur.
								if ( e.target.classList.contains( 've-details-summary' ) ) {
									const blockEl = e.target.closest( '[data-block-id]' );
									if ( blockEl ) {
										const blockId = blockEl.getAttribute( 'data-block-id' );
										store.updateBlock( blockId, { summary: e.target.innerHTML } );
									}
								}

								// Sync table cell content to store on blur.
								// Skip if focus is moving to another cell or caption
								// in the same table — updating the store would re-render
								// the table via x-html and destroy the newly focused cell.
								if ( e.target.hasAttribute( 'data-row' ) && e.target.hasAttribute( 'data-col' ) ) {
									const related = e.relatedTarget;
									const sameTable = related && e.target.closest( '.ve-block-table' ) === related.closest( '.ve-block-table' );
									const movingToCell = sameTable && ( related.hasAttribute( 'data-row' ) || 'CAPTION' === related.tagName );
									if ( ! movingToCell ) {
										const blockEl = e.target.closest( '[data-block-id]' );
										if ( blockEl ) {
											const blockId = blockEl.getAttribute( 'data-block-id' );
											this._syncTableCellsToStore( store, blockEl, blockId );
										}
									}
								}

								// Sync table caption to store on blur.
								// Same guard: skip if moving to a cell in the same table.
								if ( 'CAPTION' === e.target.tagName && e.target.closest( '.ve-block-table' ) ) {
									const related = e.relatedTarget;
									const sameTable = related && e.target.closest( '.ve-block-table' ) === related.closest( '.ve-block-table' );
									const movingToCell = sameTable && ( related.hasAttribute( 'data-row' ) || 'CAPTION' === related.tagName );
									if ( ! movingToCell ) {
										const blockEl = e.target.closest( '[data-block-id]' );
										if ( blockEl ) {
											const blockId = blockEl.getAttribute( 'data-block-id' );
											this._syncTableCellsToStore( store, blockEl, blockId );
										}
									}
								}
							} );

							// Expose for use in handleInput.
							this._markEmpty = markEmpty;

							// List block keyboard handlers: Tab, Shift+Tab, double-Enter.
							el.addEventListener( 'keydown', ( e ) => {
								const target = e.target;
								if ( ! target.hasAttribute( 'contenteditable' ) ) return;

								const blockEl = target.closest( '[data-block-id]' );
								if ( ! blockEl ) return;

								const blockId   = blockEl.getAttribute( 'data-block-id' );
								const block     = Alpine.store( 'editor' )?.getBlock( blockId );
								if ( ! block || 'list' !== block.type ) return;

								const li = window.getSelection()?.anchorNode?.closest
									? window.getSelection().anchorNode.closest( 'li' )
									: window.getSelection()?.anchorNode?.parentElement?.closest( 'li' );
								if ( ! li ) return;

								// Tab: indent list item
								if ( 'Tab' === e.key && ! e.shiftKey ) {
									e.preventDefault();
									document.execCommand( 'indent', false, null );
									return;
								}

								// Shift+Tab: outdent list item
								if ( 'Tab' === e.key && e.shiftKey ) {
									e.preventDefault();
									document.execCommand( 'outdent', false, null );
									return;
								}

								// Enter on empty list item: exit list and create new paragraph
								if ( 'Enter' === e.key && ! e.shiftKey ) {
									const liText = li.textContent.trim();
									if ( 0 === liText.length ) {
										e.preventDefault();

										// Remove the empty li
										li.remove();

										// If the list is now empty, remove the list block entirely
										const listItems = target.querySelectorAll( 'li' );
										const blockIndex = Alpine.store( 'editor' ).getBlockIndex( blockId );

										if ( 0 === listItems.length ) {
											Alpine.store( 'editor' ).removeBlock( blockId );
										}

										// Add a new default block after the list
										const newBlock = Alpine.store( 'editor' ).addBlock(
											{ type: Alpine.store( 'editor' ).defaultBlockType },
											0 === listItems.length ? blockIndex : blockIndex + 1,
										);
										if ( newBlock ) {
											this.$nextTick( () => {
												const newEl = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
												if ( newEl ) { newEl.focus(); }
												if ( Alpine.store( 'selection' ) ) {
													Alpine.store( 'selection' ).select( newBlock.id, false );
												}
											} );
										}
									}
								}
							} );

							// Paste-as-link: when pasting a URL with text selected
							// inside a contenteditable, wrap the selection in a link
							// instead of replacing it. For button blocks, set the
							// block's url attribute regardless of text selection.
							el.addEventListener( 'paste', ( e ) => {
								const text = ( e.clipboardData || window.clipboardData ).getData( 'text/plain' ).trim();
								if ( ! text ) return;

								// Quick URL check.
								try { new URL( text ); } catch { return; }

								const blockEl = e.target.closest( '[data-block-id]' );
								if ( ! blockEl ) return;

								const store   = Alpine.store( 'editor' );
								const blockId = blockEl.getAttribute( 'data-block-id' );
								const block   = store ? store.getBlock( blockId ) : null;
								if ( ! block ) return;

								// Button block: always set the url attribute.
								if ( 'button' === block.type ) {
									e.preventDefault();
									store.updateBlock( blockId, { url: text } );
									return;
								}

								// Inner button block (child of buttons container).
								const innerWrapper = e.target.closest( '[data-inner-block-id]' );
								if ( innerWrapper ) {
									const innerBlockId = innerWrapper.getAttribute( 'data-inner-block-id' );
									const innerBlock   = store.getBlock( innerBlockId );
									if ( innerBlock && 'button' === innerBlock.type ) {
										e.preventDefault();
										store.updateBlock( innerBlockId, { url: text } );
										return;
									}
								}

								// Text blocks: only convert when there is a non-collapsed selection.
								if ( ! e.target.hasAttribute( 'contenteditable' ) ) return;
								const sel = window.getSelection();
								if ( ! sel || sel.isCollapsed ) return;

								e.preventDefault();
								document.execCommand( 'createLink', false, text );
							} );

						},

						/**
						 * Size classes per heading level so each level is visually distinct.
						 */
						headingPlaceholder: {{ Js::from( __( 'visual-editor::ve.block_heading_placeholder' ) ) }},
						listPlaceholder: {{ Js::from( __( 'visual-editor::ve.list_placeholder' ) ) }},
						imageName: {{ Js::from( __( 'visual-editor::ve.block_image_name' ) ) }},
						imageDesc: {{ Js::from( __( 'visual-editor::ve.image_placeholder_desc' ) ) }},
						imageUpload: {{ Js::from( __( 'visual-editor::ve.placeholder_upload' ) ) }},
						imageMediaLib: {{ Js::from( __( 'visual-editor::ve.placeholder_media_library' ) ) }},
						captionPlaceholder: {{ Js::from( __( 'visual-editor::ve.caption_placeholder' ) ) }},
						videoName: {{ Js::from( __( 'visual-editor::ve.block_video_name' ) ) }},
						videoDesc: {{ Js::from( __( 'visual-editor::ve.video_placeholder_desc' ) ) }},
						videoUrlLabel: {{ Js::from( __( 'visual-editor::ve.placeholder_insert_url' ) ) }},
						galleryName: {{ Js::from( __( 'visual-editor::ve.block_gallery_name' ) ) }},
						galleryDesc: {{ Js::from( __( 'visual-editor::ve.gallery_placeholder_desc' ) ) }},
						galleryAddImages: {{ Js::from( __( 'visual-editor::ve.gallery_add_images' ) ) }},
						fileName: {{ Js::from( __( 'visual-editor::ve.block_file_name' ) ) }},
						fileDesc: {{ Js::from( __( 'visual-editor::ve.file_placeholder_desc' ) ) }},
						fileDownloadText: {{ Js::from( __( 'visual-editor::ve.download' ) ) }},
						audioName: {{ Js::from( __( 'visual-editor::ve.block_audio_name' ) ) }},
						audioDesc: {{ Js::from( __( 'visual-editor::ve.audio_placeholder_desc' ) ) }},
						columnsLayoutLabel: {{ Js::from( __( 'visual-editor::ve.columns_layout' ) ) }},
						columnsSkipLabel: {{ Js::from( __( 'visual-editor::ve.columns_skip' ) ) }},
						columnPlaceholder: {{ Js::from( __( 'visual-editor::ve.block_column_placeholder' ) ) }},
						addColumnLabel: {{ Js::from( __( 'visual-editor::ve.add_column' ) ) }},
						gridLayoutLabel: {{ Js::from( __( 'visual-editor::ve.grid_layout' ) ) }},
						gridSkipLabel: {{ Js::from( __( 'visual-editor::ve.grid_skip' ) ) }},
						gridCustomLabel: {{ Js::from( __( 'visual-editor::ve.grid_custom' ) ) }},
						gridItemPlaceholder: {{ Js::from( __( 'visual-editor::ve.grid_item_placeholder' ) ) }},
						addGridItemLabel: {{ Js::from( __( 'visual-editor::ve.add_grid_item' ) ) }},

						headingSizeClasses: {
							h1: 'text-4xl font-extrabold',
							h2: 'text-3xl font-bold',
							h3: 'text-2xl font-bold',
							h4: 'text-xl font-semibold',
							h5: 'text-lg font-semibold',
							h6: 'text-base font-semibold',
						},

						/**
						 * Build an inline style string from a block's style attributes.
						 *
						 * Handles all support categories: color, typography, spacing,
						 * border, shadow, dimensions, and background. Works for all
						 * block types, including custom blocks.
						 *
						 * @param {Object} attrs - The block attributes object.
						 * @returns {string} A CSS inline style string.
						 */
						/**
						 * Resolve a spacing value that may be a preset slug or CSS value.
						 *
						 * @param {string} val - Preset slug (e.g. 'md') or CSS value (e.g. '16px').
						 * @returns {string} The resolved CSS value, or empty string.
						 */
						_resolveSpacingValue( val ) {
							if ( ! val || '' === val ) return '';
							// If it already looks like a CSS value, return as-is
							if ( /^-?\d/.test( val ) || val.startsWith( 'var(' ) || val.startsWith( 'calc(' ) ) {
								return val;
							}
							// Look up preset slug from the store
							const store = Alpine.store( 'editor' );
							if ( store && store.globalStyles && store.globalStyles.spacing ) {
								const allSteps = [
									...( store.globalStyles.spacing.scale || [] ),
									...( store.globalStyles.spacing.customSteps || [] ),
								];
								const step = allSteps.find( ( s ) => s.slug === val );
								if ( step ) return step.value;
							}
							// Fall back to CSS custom property
							return 'var(--ve-spacing-' + val + ')';
						},

						buildBlockStyle( attrs ) {
							let s = '';

							// ── Color ──────────────────────────────────────
							if ( attrs.textColor ) s += 'color:' + attrs.textColor + ';';
							if ( attrs.backgroundColor ) s += 'background-color:' + attrs.backgroundColor + ';';

							// ── Typography ──────────────────────────────────
							if ( attrs.fontSize ) s += 'font-size:' + attrs.fontSize + ';';
							if ( attrs.fontFamily ) s += 'font-family:' + attrs.fontFamily + ';';
							if ( attrs.lineHeight ) s += 'line-height:' + attrs.lineHeight + ';';
							if ( attrs.letterSpacing ) s += 'letter-spacing:' + attrs.letterSpacing + ';';
							if ( attrs.textDecoration ) s += 'text-decoration:' + attrs.textDecoration + ';';
							if ( attrs.textTransform ) s += 'text-transform:' + attrs.textTransform + ';';
							if ( attrs.fontAppearance ) {
								const parts = ( attrs.fontAppearance || '' ).split( '/' );
								if ( parts[0] ) s += 'font-weight:' + parts[0] + ';';
								if ( parts[1] ) s += 'font-style:' + parts[1] + ';';
							}

							// ── Spacing (box model with per-side support) ──
							[ 'padding', 'margin' ].forEach( ( prop ) => {
								const val = attrs[ prop ];
								if ( ! val ) return;
								if ( 'object' === typeof val ) {
									[ 'top', 'right', 'bottom', 'left' ].forEach( ( side ) => {
										const resolved = this._resolveSpacingValue( val[ side ] );
										if ( resolved ) {
											s += prop + '-' + side + ':' + resolved + ';';
										}
									} );
								} else {
									const resolved = this._resolveSpacingValue( val );
									if ( resolved ) s += prop + ':' + resolved + ';';
								}
							} );
							if ( attrs.blockSpacing ) {
								const resolved = this._resolveSpacingValue( attrs.blockSpacing );
								if ( resolved ) s += 'gap:' + resolved + ';';
							}

							// ── Border ──────────────────────────────────────
							if ( attrs.border && 'object' === typeof attrs.border ) {
								const b = attrs.border;
								if ( b.width && '0' !== b.width && 'none' !== b.style ) {
									s += 'border-width:' + b.width + ( b.widthUnit || 'px' ) + ';';
									s += 'border-style:' + ( b.style || 'solid' ) + ';';
									if ( b.color ) s += 'border-color:' + b.color + ';';
								}
								if ( b.radius && '0' !== b.radius ) {
									s += 'border-radius:' + b.radius + ( b.radiusUnit || 'px' ) + ';';
								}
							}

							// ── Shadow ──────────────────────────────────────
							if ( attrs.shadow ) s += 'box-shadow:' + attrs.shadow + ';';

							// ── Dimensions ─────────────────────────────────
							if ( attrs.aspectRatio ) s += 'aspect-ratio:' + attrs.aspectRatio + ';';
							if ( attrs.minHeight ) s += 'min-height:' + attrs.minHeight + ';';

							// ── Background ─────────────────────────────────
							if ( attrs.backgroundImage ) {
								const safeUrl = ( /^(https?:\/\/|\/(?!\/)|data:image\/(png|jpeg|gif|webp|svg\+xml)(;base64)?,[^\s]+$)/i.test( attrs.backgroundImage ) )
									? attrs.backgroundImage.replace( /'/g, '%27' ).replace( /\\/g, '%5C' ).replace( /\(/g, '%28' ).replace( /\)/g, '%29' )
									: '';
								if ( safeUrl ) {
									s += 'background-image:url(' + safeUrl + ');';
									if ( attrs.backgroundSize ) s += 'background-size:' + attrs.backgroundSize + ';';
									if ( attrs.backgroundPosition ) s += 'background-position:' + attrs.backgroundPosition + ';';
								}
							}
							if ( attrs.backgroundGradient ) {
								s += 'background-image:' + attrs.backgroundGradient + ';';
							}

							return s;
						},

						getBlockHtml( block ) {
							// Delegate to the block renderer registry first.
							// Registered renderers handle their own rendering.
							const rendererStore = Alpine.store( 'blockRenderers' );
							if ( rendererStore && rendererStore.hasRenderer( block.type ) ) {
								const result = rendererStore.getHtml( block, {
									headingPlaceholder: this.headingPlaceholder,
									paragraphPlaceholder: {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }},
									headingSizeClasses: this.headingSizeClasses,
								} );
								if ( null !== result ) {
									return result;
								}
							}

							// Heading blocks are generated dynamically so the tag and
							// size classes react to level changes from the toolbar.
							if ( 'heading' === block.type ) {
								const level     = block.attributes?.level || 'h2';
								const tag       = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( level ) ? level : 'h2';
								const text      = block.attributes?.text || '';
								const alignment = block.attributes?.alignment || 'left';
								const sizeClass = this.headingSizeClasses[ tag ] || this.headingSizeClasses.h2;
								const placeholder = this.headingPlaceholder;
								return '<' + tag
									+ ' class=\'ve-block ve-block-heading ve-block-editing ' + sizeClass + ' text-' + alignment + '\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + placeholder + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ ' data-ve-slash-command=\'true\''
									+ '>' + text + '</' + tag + '>';
							}

							// List blocks are generated dynamically so the tag and
							// styles react to type changes from the toolbar.
							if ( 'list' === block.type ) {
								const listType    = block.attributes?.type || 'unordered';
								const tag         = 'ordered' === listType ? 'ol' : 'ul';
								const styleClass  = 'ordered' === listType ? 'list-decimal' : 'list-disc';
								const start       = block.attributes?.start || '1';
								const reversed    = block.attributes?.reversed || false;
								const placeholder = this.listPlaceholder;

								let attrs = ' class=\'ve-block ve-block-list ve-block-editing ve-list-' + listType + ' ' + styleClass + ' pl-6\''
									+ ' contenteditable=\'true\'';

								if ( 'ordered' === listType && '1' !== start ) {
									attrs += ' start=\'' + start + '\'';
								}
								if ( 'ordered' === listType && reversed ) {
									attrs += ' reversed';
								}

								// Preserve existing content from the DOM if available.
								const existingEl = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] ' + tag );
								let innerHtml = '<li data-placeholder=\'' + placeholder + '\'></li>';
								if ( existingEl ) {
									innerHtml = existingEl.innerHTML;
								} else if ( block.attributes?._transformedContent ) {
									// Content injected by transformBlock() from another block type.
									innerHtml = block.attributes._transformedContent;
									const { _transformedContent, ...cleanAttrs } = block.attributes;
									Alpine.store( 'editor' ).updateBlock( block.id, cleanAttrs );
								} else {
									// Check if we have pre-rendered HTML for a different tag type
									const altTag = 'ol' === tag ? 'ul' : 'ol';
									const altEl  = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] ' + altTag );
									if ( altEl ) {
										innerHtml = altEl.innerHTML;
									}
								}

								return '<' + tag + attrs + '>' + innerHtml + '</' + tag + '>';
							}

							// Quote blocks are rendered dynamically so inner blocks
							// react to structural changes (add/remove) from the store.
							// Inner block text and citation content live in the DOM during
							// editing (same pattern as list blocks) to avoid re-renders
							// that steal focus from contenteditable elements.
							if ( 'quote' === block.type ) {
								const showCitation = block.attributes?.showCitation || false;
								const alignment    = block.attributes?.alignment || 'left';
								const textColor    = block.attributes?.textColor || '';
								const bgColor      = block.attributes?.backgroundColor || '';

								const bgImage    = block.attributes?.backgroundImage || '';
								const bgSize     = block.attributes?.backgroundSize || 'cover';
								const bgPosition = block.attributes?.backgroundPosition || 'center center';

								let inlineStyle = '';
								if ( textColor ) { inlineStyle += 'color:' + textColor + ';'; }
								if ( bgColor ) { inlineStyle += 'background-color:' + bgColor + ';'; }
								if ( bgImage ) {
									inlineStyle += 'background-image:url(\x27' + bgImage + '\x27);';
									inlineStyle += 'background-size:' + bgSize + ';';
									inlineStyle += 'background-position:' + bgPosition + ';';
								}

								// Read citation from DOM if element exists, else fall back to store.
								let citationText = '';
								if ( showCitation ) {
									const existingCite = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] .ve-quote-citation' );
									citationText = existingCite ? existingCite.innerHTML : ( block.attributes?.citation || '' );
								}

								let innerHtml = '';
								if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
									innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\'>';
									block.innerBlocks.forEach( ( inner, idx ) => {
										// Insertion point before each inner block
										innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
											+ '<div class=\'flex justify-center\'>'
											+ '<button type=\'button\''
											+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
											+ ' data-ve-inner-insert'
											+ ' data-parent-id=\'' + block.id + '\''
											+ ' data-insert-index=\'' + idx + '\''
											+ '>+</button>'
											+ '</div></div>';

										// Read inner block text from DOM if element exists (preserves
										// user's typed content without creating a reactive dependency).
										const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
										let innerText = inner.attributes?.text || '';
										if ( existingWrapper ) {
											const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
											innerText = contentEl.innerHTML;
										}

										innerHtml += '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
											+ ' data-block-id=\'' + inner.id + '\''
											+ ' data-inner-block-id=\'' + inner.id + '\''
											+ ' data-parent-id=\'' + block.id + '\''
											+ ' tabindex=\'-1\''
											+ '>'
											+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
											+ ' draggable=\'true\''
											+ ' data-ve-inner-drag-handle'
											+ ' data-inner-drag-id=\'' + inner.id + '\''
											+ ' data-parent-id=\'' + block.id + '\''
											+ '>'
											+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
											+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
											+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
											+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
											+ '</svg></div>';

										// Render inner block content element based on type.
										if ( 'heading' === inner.type ) {
											const innerLevel = inner.attributes?.level || 'h2';
											const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
											const innerSize  = this.headingSizeClasses[ innerTag ] || this.headingSizeClasses.h2;
											innerHtml += '<' + innerTag
												+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
												+ ' contenteditable=\'true\''
												+ ' data-placeholder=\'' + this.headingPlaceholder + '\''
												+ ' data-ve-enter-new-block=\'true\''
												+ ' data-ve-slash-command=\'true\''
												+ '>' + innerText + '</' + innerTag + '></div>';
										} else {
											innerHtml += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
												+ ' contenteditable=\'true\''
												+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }} + '\''
												+ ' data-ve-enter-new-block=\'true\''
												+ ' data-ve-slash-command=\'true\''
												+ '>' + innerText + '</div></div>';
										}
									} );

									// Insertion point after last inner block
									innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
										+ '<div class=\'flex justify-center\'>'
										+ '<button type=\'button\''
										+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
										+ ' data-ve-inner-insert'
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' data-insert-index=\'' + block.innerBlocks.length + '\''
										+ '>+</button>'
										+ '</div></div>';

									innerHtml += '</div>';
								} else {
									innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\'>'
										+ '<div class=\'ve-inner-blocks-placeholder\''
										+ ' contenteditable=\'true\''
										+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_quote_placeholder' ) ) }} + '\''
										+ ' data-ve-enter-new-block=\'true\''
										+ '></div></div>';
								}

								if ( showCitation ) {
									innerHtml += '<cite class=\'ve-quote-citation block mt-2 text-sm not-italic text-base-content/70\''
										+ ' contenteditable=\'true\''
										+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.citation_placeholder' ) ) }} + '\''
										+ '>' + citationText + '</cite>';
								}

								return '<blockquote class=\'ve-block ve-block-quote ve-block-editing text-' + alignment + '\''
									+ ( inlineStyle ? ' style=\'' + inlineStyle + '\'' : '' )
									+ '>' + innerHtml + '</blockquote>';
							}

							// Gallery blocks are rendered dynamically so inner
							// image blocks react to additions, removals, and
							// gallery-level style changes (columns, crop, etc.).
							if ( 'gallery' === block.type ) {
								return this.getGalleryBlockHtml( block );
							}

							// Image blocks are rendered dynamically so the image
							// reacts to URL, style, and dimension changes from the
							// media picker and inspector controls.
							if ( 'image' === block.type ) {
								return this.getImageBlockHtml( block );
							}

							// Video blocks are rendered dynamically so the video
							// preview reacts to URL and poster changes from the
							// media picker and inspector controls.
							if ( 'video' === block.type ) {
								return this.getVideoBlockHtml( block );
							}

							// Audio blocks are rendered dynamically so the audio
							// player reacts to URL and setting changes from the
							// media picker and inspector controls.
							if ( 'audio' === block.type ) {
								return this.getAudioBlockHtml( block );
							}

							// File blocks are rendered dynamically so the file
							// preview reacts to URL and setting changes from the
							// media picker and inspector controls.
							if ( 'file' === block.type ) {
								return this.getFileBlockHtml( block );
							}

							// Cover blocks are rendered dynamically so inner blocks
							// and media/overlay settings react to changes.
							if ( 'cover' === block.type ) {
								return this.getCoverBlockHtml( block );
							}

							// Media & Text blocks are rendered dynamically so inner
							// blocks, media, and layout settings react to changes.
							if ( 'media-text' === block.type ) {
								return this.getMediaTextBlockHtml( block );
							}

							// Group blocks are rendered dynamically so inner blocks
							// and variation picker react to structural changes.
							if ( 'group' === block.type ) {
								return this.getGroupBlockHtml( block );
							}

							// Columns blocks are rendered dynamically so inner column
							// blocks and the layout variation picker react to changes.
							if ( 'columns' === block.type ) {
								return this.getColumnsBlockHtml( block );
							}

							// Column blocks are child-only; rendered by getColumnsBlockHtml.
							if ( 'column' === block.type ) {
								return this.getColumnBlockHtml( block );
							}

							// Grid blocks are rendered dynamically so inner grid-item
							// blocks and the layout picker react to changes.
							if ( 'grid' === block.type ) {
								return this.getGridBlockHtml( block );
							}

							// Grid item blocks are child-only; rendered by getGridBlockHtml.
							if ( 'grid-item' === block.type ) {
								return this.getGridItemBlockHtml( block, block.attributes?._parentId || '' );
							}

							if ( this.preRendered[ block.id ] ) {
								return this.preRendered[ block.id ];
							}
							if ( this.defaultTemplates[ block.type ] ) {
								return this.defaultTemplates[ block.type ];
							}
							const label = this.blockNames[ block.type ] || block.type;
							return '<div class=\'ve-block p-4 border border-dashed border-base-300 rounded text-center text-sm text-base-content/50\'>' + label + '</div>';
						},

						getImageBlockHtml( block ) {
							const url         = block.attributes?.url || '';
							const alt         = block.attributes?.alt || '';
							const caption     = block.attributes?.caption || '';
							const size        = block.attributes?.size || 'large';
							const alignment   = block.attributes?.alignment || 'center';
							const rounded     = block.attributes?.rounded || false;
							const shadow      = block.attributes?.shadow || false;
							const objectFit   = block.attributes?.objectFit || 'cover';
							const aspectRatio = block.attributes?.aspectRatio || 'original';
							const imgWidth    = block.attributes?.width || '';
							const imgHeight   = block.attributes?.height || '';

							let figClasses = 've-block ve-block-image ve-block-image--' + size + ' ve-block-editing text-' + alignment;
							if ( rounded ) { figClasses += ' ve-rounded'; }
							if ( shadow ) { figClasses += ' ve-shadow'; }

							let imgStyle = 'object-fit:' + objectFit + ';';
							if ( 'original' !== aspectRatio ) { imgStyle += 'aspect-ratio:' + aspectRatio + ';'; }
							if ( imgWidth ) { imgStyle += 'width:' + ( String( imgWidth ).match( /^\d+$/ ) ? imgWidth + 'px' : imgWidth ) + ';'; }
							if ( imgHeight ) { imgStyle += 'height:' + ( String( imgHeight ).match( /^\d+$/ ) ? imgHeight + 'px' : imgHeight ) + ';'; }

							if ( ! url ) {
								const ctx = block.id + ':image-url';
								return '<figure class=\'' + figClasses + '\'>'
									+ '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + this.imageName + '</p>'
									+ '<p class=\'ve-block-placeholder__description text-sm text-base-content/70\'>' + this.imageDesc + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ '</div>'
									+ '</div>'
									+ '</figure>';
							}

							const safeAlt = alt.replace( /&/g, '\x26amp;' ).replace( /</g, '\x26lt;' ).replace( />/g, '\x26gt;' ).replace( /\u0022/g, '\x26quot;' ).replace( /\x27/g, '\x26#39;' );
							return '<figure class=\'' + figClasses + '\'>'
								+ '<img src=\'' + url + '\' alt=\'' + safeAlt + '\' style=\'' + imgStyle + '\' />'
								+ '<figcaption contenteditable=\'true\' data-placeholder=\'' + this.captionPlaceholder + '\'>' + caption + '</figcaption>'
								+ '</figure>';
						},

						getVideoBlockHtml( block ) {
							const url     = block.attributes?.url || '';
							const caption = block.attributes?.caption || '';
							const poster  = block.attributes?.poster || '';

							const figClasses = 've-block ve-block-video ve-block-editing';

							if ( ! url ) {
								const ctx = block.id + ':video-url';
								return '<figure class=\'' + figClasses + '\'>'
									+ '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + this.videoName + '</p>'
									+ '<p class=\'ve-block-placeholder__description text-sm text-base-content/70\'>' + this.videoDesc + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ '</div>'
									+ '</div>'
									+ '</figure>';
							}

							let videoAttrs = 'src=\'' + url + '\' controls muted style=\'width:100%;display:block;\'';
							if ( poster ) { videoAttrs += ' poster=\'' + poster + '\''; }

							return '<figure class=\'' + figClasses + '\'>'
								+ '<div class=\'ve-block-video__preview\' style=\'position:relative;\'>'
								+ '<video ' + videoAttrs + '></video>'
								+ '<div style=\'position:absolute;inset:0;cursor:default;\' aria-hidden=\'true\'></div>'
								+ '</div>'
								+ '<figcaption contenteditable=\'true\' data-placeholder=\'' + this.captionPlaceholder + '\'>' + caption + '</figcaption>'
								+ '</figure>';
						},

						getAudioBlockHtml( block ) {
							const url     = block.attributes?.url || '';
							const caption = block.attributes?.caption || '';

							const figClasses = 've-block ve-block-audio ve-block-editing';

							if ( ! url ) {
								const ctx = block.id + ':audio-url';
								return '<figure class=\'' + figClasses + '\'>'
									+ '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + this.audioName + '</p>'
									+ '<p class=\'ve-block-placeholder__description text-sm text-base-content/70\'>' + this.audioDesc + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ '</div>'
									+ '</div>'
									+ '</figure>';
							}

							return '<figure class=\'' + figClasses + '\'>'
								+ '<div class=\'ve-block-audio__preview\' style=\'position:relative;\'>'
								+ '<audio src=\'' + url + '\' controls style=\'width:100%;display:block;\'></audio>'
								+ '<div style=\'position:absolute;inset:0;cursor:default;\' aria-hidden=\'true\'></div>'
								+ '</div>'
								+ '<figcaption contenteditable=\'true\' data-placeholder=\'' + this.captionPlaceholder + '\'>' + caption + '</figcaption>'
								+ '</figure>';
						},

						getFileBlockHtml( block ) {
							const url                = block.attributes?.url || '';
							const filename           = block.attributes?.filename || ( url ? url.split( '/' ).pop().split( '?' )[0] : '' );
							const fileSize           = block.attributes?.fileSize || '';
							const downloadButtonText = block.attributes?.downloadButtonText || this.fileDownloadText;
							const showDownloadButton = block.attributes?.showDownloadButton !== false;
							const displayPreview     = block.attributes?.displayPreview || false;
							const previewHeight      = block.attributes?.previewHeight || 600;

							const divClasses = 've-block ve-block-file ve-block-editing';

							if ( ! url ) {
								const ctx = block.id + ':file-url';
								return '<div class=\'' + divClasses + '\'>'
									+ '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + this.fileName + '</p>'
									+ '<p class=\'ve-block-placeholder__description text-sm text-base-content/70\'>' + this.fileDesc + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ '</div>'
									+ '</div>'
									+ '</div>';
							}

							let html = '<div class=\'' + divClasses + '\'>';

							// PDF preview embed
							const isPdf = /\.pdf($|\?)/i.test( url );
							if ( displayPreview && isPdf ) {
								const safeName = ( filename || url ).replace( /'/g, '\u0026#39;' );
								html += '<div class=\'ve-block-file__preview mb-3 rounded-lg overflow-hidden border border-base-300\'>'
									+ '<object data=\'' + url + '\' type=\'application/pdf\' style=\'width:100%;height:' + previewHeight + 'px;display:block;\'>'
									+ '<p class=\'p-4 text-sm text-base-content/70\'>' + this.fileDownloadText + ': <a href=\'' + url + '\' class=\'link link-primary\'>' + safeName + '</a></p>'
									+ '</object>'
									+ '</div>';
							}

							const safeFn = filename.replace( /'/g, '\u0026#39;' );
							html += '<div class=\'ve-block-file__content flex items-center gap-3 p-4 rounded-lg border border-base-300 bg-base-100\'>'
								+ '<div class=\'shrink-0 text-base-content/60\'>'
								+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'24\' height=\'24\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z\' />'
								+ '</svg>'
								+ '</div>'
								+ '<div class=\'ve-block-file__info flex-1 min-w-0\'>'
								+ '<span class=\'ve-block-file__name block font-medium text-primary truncate\' contenteditable=\'true\' data-placeholder=\'' + this.fileName + '\'>' + safeFn + '</span>';

							if ( fileSize ) {
								html += '<span class=\'ve-block-file__size block text-xs text-base-content/50 mt-0.5\'>' + fileSize + '</span>';
							}

							html += '</div>';

							if ( showDownloadButton ) {
								const safeBtnText = downloadButtonText.replace( /'/g, '\u0026#39;' );
								html += '<span class=\'ve-block-file__download shrink-0 btn btn-sm btn-primary\' contenteditable=\'true\' data-placeholder=\'' + this.fileDownloadText + '\'>' + safeBtnText + '</span>';
							}

							html += '</div></div>';
							return html;
						},

						getCoverBlockHtml( block ) {
							const mediaType        = block.attributes?.mediaType || 'image';
							const mediaUrl         = block.attributes?.mediaUrl || '';
							const alt              = block.attributes?.alt || '';
							const focalPoint       = block.attributes?.focalPoint || { x: 0.5, y: 0.5 };
							const hasParallax      = block.attributes?.hasParallax || false;
							const isRepeated       = block.attributes?.isRepeated || false;
							const overlayColor     = block.attributes?.overlayColor || '#000000';
							const overlayOpacity   = Math.max( 0, Math.min( 100, parseInt( block.attributes?.overlayOpacity ?? 50, 10 ) ) );
							const minHeight        = block.attributes?.minHeight || '430px';
							const contentAlignment = block.attributes?.contentAlignment || 'center';
							const textColor        = block.attributes?.textColor || '';
							const safeUrl = ( /^(https?:\/\/|\/(?!\/)|data:image\/(png|jpeg|gif|webp|svg\+xml)(;base64)?,[^\s]+$)/i.test( mediaUrl ) ) ? mediaUrl.replace( /'/g, '%27' ).replace( /\\/g, '%5C' ).replace( /\(/g, '%28' ).replace( /\)/g, '%29' ) : '';

							const focalX = Math.max( 0, Math.min( 1, parseFloat( focalPoint.x ?? 0.5 ) ) );
							const focalY = Math.max( 0, Math.min( 1, parseFloat( focalPoint.y ?? 0.5 ) ) );
							const objPos = Math.round( focalX * 100 ) + '% ' + Math.round( focalY * 100 ) + '%';

							const alignMap = {
								'top-left':      [ 'flex-start', 'flex-start' ],
								'top-center':    [ 'flex-start', 'center' ],
								'top-right':     [ 'flex-start', 'flex-end' ],
								'center-left':   [ 'center', 'flex-start' ],
								'center':        [ 'center', 'center' ],
								'center-right':  [ 'center', 'flex-end' ],
								'bottom-left':   [ 'flex-end', 'flex-start' ],
								'bottom-center': [ 'flex-end', 'center' ],
								'bottom-right':  [ 'flex-end', 'flex-end' ],
							};
							const align = alignMap[ contentAlignment ] || [ 'center', 'center' ];

							let containerStyle = 'position:relative;display:flex;flex-direction:column;justify-content:' + align[0] + ';align-items:' + align[1] + ';min-height:' + minHeight + ';overflow:hidden;';
							if ( textColor ) { containerStyle += 'color:' + textColor + ';'; }

							let bgHtml = '';
							if ( 'image' === mediaType && safeUrl ) {
								if ( hasParallax ) {
									const bgRepeat = isRepeated ? 'background-repeat:repeat;background-size:auto;' : 'background-repeat:no-repeat;';
									bgHtml = '<div style=\'position:absolute;inset:0;background-image:url(' + safeUrl + ');background-position:' + objPos + ';background-attachment:fixed;background-size:cover;' + bgRepeat + '\' aria-hidden=\'true\'></div>';
								} else if ( isRepeated ) {
									bgHtml = '<div style=\'position:absolute;inset:0;background-image:url(' + safeUrl + ');background-repeat:repeat;background-size:auto;\' aria-hidden=\'true\'></div>';
								} else {
									const ariaAttr = alt ? '' : ' aria-hidden=\'true\'';
									bgHtml = '<img src=\'' + safeUrl + '\' alt=\'' + ( alt || '' ).replace( /&/g, '\x26amp;' ).replace( /</g, '\x26lt;' ).replace( />/g, '\x26gt;' ).replace( /\u0022/g, '\x26quot;' ).replace( /\x27/g, '\x26#39;' ) + '\' style=\'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:' + objPos + ';\'' + ariaAttr + ' />';
								}
							} else if ( 'video' === mediaType && safeUrl ) {
								bgHtml = '<video src=\'' + safeUrl + '\' autoplay muted loop playsinline style=\'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:' + objPos + ';\' aria-hidden=\'true\'></video>';
							}

							const overlayHtml = '<div class=\'ve-block-cover__overlay\' style=\'position:absolute;inset:0;background-color:' + overlayColor + ';opacity:' + ( overlayOpacity / 100 ) + ';\' aria-hidden=\'true\'></div>';

							// Inner blocks content.
							let innerHtml = '';
							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\'>';
								block.innerBlocks.forEach( ( inner, idx ) => {
									innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
										+ '<div class=\'flex justify-center\'>'
										+ '<button type=\'button\''
										+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
										+ ' data-ve-inner-insert'
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' data-insert-index=\'' + idx + '\''
										+ '>+</button>'
										+ '</div></div>';

									const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
									let innerText = inner.attributes?.text || inner.attributes?.content || '';
									if ( existingWrapper ) {
										const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
										innerText = contentEl.innerHTML;
									}

									innerHtml += '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
										+ ' data-block-id=\'' + inner.id + '\''
										+ ' data-inner-block-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' tabindex=\'-1\''
										+ '>'
										+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
										+ ' draggable=\'true\''
										+ ' data-ve-inner-drag-handle'
										+ ' data-inner-drag-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ '>'
										+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
										+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
										+ '</svg></div>';

									if ( 'heading' === inner.type ) {
										const innerLevel = inner.attributes?.level || 'h2';
										const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
										const innerSize  = this.headingSizeClasses[ innerTag ] || this.headingSizeClasses.h2;
										innerHtml += '<' + innerTag
											+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + this.headingPlaceholder + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</' + innerTag + '></div>';
									} else {
										innerHtml += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }} + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</div></div>';
									}
								} );

								innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
									+ '<div class=\'flex justify-center\'>'
									+ '<button type=\'button\''
									+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
									+ ' data-ve-inner-insert'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-insert-index=\'' + block.innerBlocks.length + '\''
									+ '>+</button>'
									+ '</div></div>';

								innerHtml += '</div>';
							} else {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\'>'
									+ '<div class=\'ve-inner-blocks-placeholder\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_group_placeholder' ) ) }} + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ '></div></div>';
							}

							const hasMedia = ( 'color' !== mediaType && mediaUrl ) || 'color' === mediaType;

							if ( ! hasMedia && 'color' !== mediaType ) {
								// Show placeholder for media selection with color swatches.
								const ctx = block.id + ':cover-media';
								const coverColors = [
									{ color: '#000000', label: 'Black' },
									{ color: '#FFFFFF', label: 'White' },
									{ color: '#0ea5e9', label: 'Sky' },
									{ color: '#3b82f6', label: 'Blue' },
									{ color: '#1e3a5f', label: 'Navy' },
									{ color: '#6366f1', label: 'Indigo' },
									{ color: '#6b7280', label: 'Gray' },
									{ color: '#059669', label: 'Emerald' },
									{ color: '#dc2626', label: 'Red' },
									{ color: '#d97706', label: 'Amber' },
								];
								let swatchHtml = '<div class=\'flex gap-2 flex-wrap justify-center\'>';
								coverColors.forEach( ( c ) => {
									const borderStyle = '#FFFFFF' === c.color ? 'border:1px solid oklch(var(--bc)/0.2);' : '';
									swatchHtml += '<button type=\'button\''
										+ ' class=\'w-7 h-7 rounded-full cursor-pointer hover:ring-2 hover:ring-primary hover:ring-offset-2 transition-shadow\''
										+ ' style=\'background-color:' + c.color + ';' + borderStyle + '\''
										+ ' data-ve-cover-color=\'' + c.color + '\''
										+ ' title=\'' + c.label + '\''
										+ '></button>';
								} );
								swatchHtml += '</div>';

								return '<div class=\'ve-block ve-block-cover ve-block-editing\' style=\'' + containerStyle + '\'>'
									+ '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content w-full\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-1.007.66-1.862 1.572-2.149Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + {{ Js::from( __( 'visual-editor::ve.block_cover_name' ) ) }} + '</p>'
									+ '<p class=\'ve-block-placeholder__description text-sm text-base-content/70\'>' + {{ Js::from( __( 'visual-editor::ve.cover_placeholder_desc' ) ) }} + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2 flex-wrap justify-center\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ ( this.featuredImageUrl ? '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-cover-featured-image=\'' + this.featuredImageUrl + '\'>' + {{ Js::from( __( 'visual-editor::ve.cover_use_featured_image' ) ) }} + '</button>' : '' )
									+ '</div>'
									+ swatchHtml
									+ '</div>'
									+ '</div>';
							}

							return '<div class=\'ve-block ve-block-cover ve-block-editing\' style=\'' + containerStyle + '\'>'
								+ bgHtml
								+ overlayHtml
								+ '<div class=\'ve-block-cover__content\' style=\'position:relative;z-index:1;padding:2rem;width:100%;\'>'
								+ innerHtml
								+ '</div>'
								+ '</div>';
						},

						getMediaTextBlockHtml( block ) {
							const mediaType          = block.attributes?.mediaType || 'image';
							const mediaUrl           = block.attributes?.mediaUrl || '';
							const mediaAlt           = block.attributes?.mediaAlt || '';
							const focalPoint         = block.attributes?.focalPoint || { x: 0.5, y: 0.5 };
							const mediaPosition      = block.attributes?.mediaPosition || 'left';
							const mediaWidth         = Math.max( 25, Math.min( 75, parseInt( block.attributes?.mediaWidth ?? 50, 10 ) ) );
							const verticalAlign      = block.attributes?.verticalAlignment || 'top';
							const imageFill          = block.attributes?.imageFill || false;
							const isStackedOnMobile  = block.attributes?.isStackedOnMobile !== false;
							const gridGap            = block.attributes?.gridGap || '0';
							const contentPadding     = block.attributes?.contentPadding || '1rem';
							const contentBgColor     = block.attributes?.contentBackgroundColor || '';
							const safeUrl = ( /^(https?:\/\/|\/(?!\/)|data:image\/(png|jpeg|gif|webp|svg\+xml)(;base64)?,[^\s]+$)/i.test( mediaUrl ) ) ? mediaUrl.replace( /'/g, '%27' ).replace( /\\/g, '%5C' ).replace( /\(/g, '%28' ).replace( /\)/g, '%29' ) : '';

							const contentWidth = 100 - mediaWidth;

							const focalX = Math.max( 0, Math.min( 1, parseFloat( focalPoint.x ?? 0.5 ) ) );
							const focalY = Math.max( 0, Math.min( 1, parseFloat( focalPoint.y ?? 0.5 ) ) );
							const objPos = Math.round( focalX * 100 ) + '% ' + Math.round( focalY * 100 ) + '%';

							const alignMap = { top: 'flex-start', center: 'center', bottom: 'flex-end' };
							const alignItems = alignMap[ verticalAlign ] || 'flex-start';

							const gridCols = 'right' === mediaPosition
								? contentWidth + '% ' + mediaWidth + '%'
								: mediaWidth + '% ' + contentWidth + '%';

							const containerStyle = 'display:grid;grid-template-columns:' + gridCols + ';align-items:' + alignItems + ';gap:' + gridGap + ';min-height:200px;';

							const mediaOrder = 'right' === mediaPosition ? 'order:1;' : 'order:0;';
							const contentOrder = 'right' === mediaPosition ? 'order:0;' : 'order:1;';

							let mediaStyle = mediaOrder;
							if ( imageFill && 'image' === mediaType ) {
								mediaStyle += 'position:relative;min-height:250px;';
							}

							let contentStyle = 'padding:' + contentPadding + ';' + contentOrder;
							if ( contentBgColor ) { contentStyle += 'background-color:' + contentBgColor + ';'; }

							// Media side.
							let mediaSideHtml = '';
							if ( ! mediaUrl ) {
								const ctx = block.id + ':media-text-url';
								mediaSideHtml = '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content h-full\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + {{ Js::from( __( 'visual-editor::ve.block_media-text_name' ) ) }} + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ '</div>'
									+ '</div>';
							} else if ( 'image' === mediaType ) {
								const safeAlt = ( mediaAlt || '' ).replace( /&/g, '\x26amp;' ).replace( /</g, '\x26lt;' ).replace( />/g, '\x26gt;' ).replace( /\u0022/g, '\x26quot;' ).replace( /\x27/g, '\x26#39;' );
								if ( imageFill ) {
									mediaSideHtml = '<img src=\'' + safeUrl + '\' alt=\'' + safeAlt + '\' style=\'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:' + objPos + ';\' />';
								} else {
									mediaSideHtml = '<img src=\'' + safeUrl + '\' alt=\'' + safeAlt + '\' style=\'width:100%;height:auto;display:block;\' />';
								}
							} else if ( 'video' === mediaType ) {
								mediaSideHtml = '<video src=\'' + safeUrl + '\' autoplay muted loop playsinline style=\'width:100%;height:100%;object-fit:cover;display:block;\' aria-hidden=\'true\'></video>';
							}

							// Inner blocks content side.
							let innerHtml = '';
							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\'>';
								block.innerBlocks.forEach( ( inner, idx ) => {
									innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
										+ '<div class=\'flex justify-center\'>'
										+ '<button type=\'button\''
										+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
										+ ' data-ve-inner-insert'
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' data-insert-index=\'' + idx + '\''
										+ '>+</button>'
										+ '</div></div>';

									const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
									let innerText = inner.attributes?.text || inner.attributes?.content || '';
									if ( existingWrapper ) {
										const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
										innerText = contentEl.innerHTML;
									}

									innerHtml += '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
										+ ' data-block-id=\'' + inner.id + '\''
										+ ' data-inner-block-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' tabindex=\'-1\''
										+ '>'
										+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
										+ ' draggable=\'true\''
										+ ' data-ve-inner-drag-handle'
										+ ' data-inner-drag-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ '>'
										+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
										+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
										+ '</svg></div>';

									if ( 'heading' === inner.type ) {
										const innerLevel = inner.attributes?.level || 'h2';
										const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
										const innerSize  = this.headingSizeClasses[ innerTag ] || this.headingSizeClasses.h2;
										innerHtml += '<' + innerTag
											+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + this.headingPlaceholder + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</' + innerTag + '></div>';
									} else {
										innerHtml += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }} + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</div></div>';
									}
								} );

								innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
									+ '<div class=\'flex justify-center\'>'
									+ '<button type=\'button\''
									+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
									+ ' data-ve-inner-insert'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-insert-index=\'' + block.innerBlocks.length + '\''
									+ '>+</button>'
									+ '</div></div>';

								innerHtml += '</div>';
							} else {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\'>'
									+ '<div class=\'ve-inner-blocks-placeholder\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_group_placeholder' ) ) }} + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ '></div></div>';
							}

							const stackedClass = isStackedOnMobile ? ' ve-media-text--stacked-mobile' : '';

							return '<div class=\'ve-block ve-block-media-text ve-block-editing' + stackedClass + '\' style=\'' + containerStyle + '\'>'
								+ '<div class=\'ve-media-text__media\' style=\'' + mediaStyle + '\'>' + mediaSideHtml + '</div>'
								+ '<div class=\'ve-media-text__content\' style=\'' + contentStyle + '\'>' + innerHtml + '</div>'
								+ '</div>';
						},

						getGroupBlockHtml( block ) {
							const flexDirection  = block.attributes?.flexDirection || 'column';
							const flexWrap       = block.attributes?.flexWrap || 'nowrap';
							const justifyContent = block.attributes?.justifyContent || 'flex-start';
							const textColor      = block.attributes?.textColor || '';
							const bgColor        = block.attributes?.backgroundColor || '';
							const gap            = block.attributes?.gap || '';
							const tag            = block.attributes?.tag || 'div';
							const useFlexbox     = block.attributes?.useFlexbox || false;
							const fillHeight     = block.attributes?.fillHeight || false;
							const innerSpacing   = block.attributes?.innerSpacing || 'normal';

							let inlineStyle = 'display:flex;flex-direction:' + flexDirection + ';flex-wrap:' + flexWrap + ';';

							if ( 'row' === flexDirection ) {
								inlineStyle += 'justify-content:' + justifyContent + ';';
							}

							if ( textColor ) { inlineStyle += 'color:' + textColor + ';'; }
							if ( bgColor ) { inlineStyle += 'background-color:' + bgColor + ';'; }
							if ( gap ) { inlineStyle += 'gap:' + gap + ';'; }

							if ( useFlexbox ) {
								const spacingMap = { none: '0', small: '0.5rem', normal: '1rem', medium: '1.5rem', large: '2rem' };
								inlineStyle += 'gap:' + ( spacingMap[ innerSpacing ] || '1rem' ) + ';';
							}
							if ( fillHeight ) { inlineStyle += 'height:100%;'; }

							const groupVariation       = block.attributes?._groupVariation || null;
							const hasExplicitVariation = groupVariation || bgColor || textColor;
							const showVariationPicker  = ( ! block.innerBlocks || 0 === block.innerBlocks.length ) && ! hasExplicitVariation;

							if ( showVariationPicker ) {
								return '<' + tag + ' class=\'ve-block ve-block-group ve-block-editing\' style=\'' + inlineStyle + '\'>'
									+ '<div class=\'ve-group-variation-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full\'>'
									+ '<p class=\'text-sm text-base-content/60\'>' + {{ Js::from( __( 'visual-editor::ve.variation_picker_instruction' ) ) }} + '</p>'
									+ '<div class=\'flex gap-3\'>'
									+ '<button type=\'button\' class=\'ve-variation-btn flex flex-col items-center gap-1 rounded-lg border border-base-300 px-4 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-variation=\'group\'>'
									+ '<svg class=\'w-8 h-8 text-base-content/70\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\'>'
									+ '<rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><line x1=\'3\' y1=\'9\' x2=\'21\' y2=\'9\'/><line x1=\'3\' y1=\'15\' x2=\'21\' y2=\'15\'/>'
									+ '</svg>'
									+ '<span class=\'text-xs font-medium\'>' + {{ Js::from( __( 'visual-editor::ve.variation_group' ) ) }} + '</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-variation-btn flex flex-col items-center gap-1 rounded-lg border border-base-300 px-4 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-variation=\'row\'>'
									+ '<svg class=\'w-8 h-8 text-base-content/70\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\'>'
									+ '<rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><line x1=\'9\' y1=\'3\' x2=\'9\' y2=\'21\'/><line x1=\'15\' y1=\'3\' x2=\'15\' y2=\'21\'/>'
									+ '</svg>'
									+ '<span class=\'text-xs font-medium\'>' + {{ Js::from( __( 'visual-editor::ve.variation_row' ) ) }} + '</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-variation-btn flex flex-col items-center gap-1 rounded-lg border border-base-300 px-4 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-variation=\'stack\'>'
									+ '<svg class=\'w-8 h-8 text-base-content/70\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\'>'
									+ '<rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><line x1=\'3\' y1=\'8\' x2=\'21\' y2=\'8\'/><line x1=\'3\' y1=\'13\' x2=\'21\' y2=\'13\'/><line x1=\'3\' y1=\'18\' x2=\'21\' y2=\'18\'/>'
									+ '</svg>'
									+ '<span class=\'text-xs font-medium\'>' + {{ Js::from( __( 'visual-editor::ve.variation_stack' ) ) }} + '</span>'
									+ '</button>'
									+ '</div>'
									+ '</div>'
									+ '</' + tag + '>';
							}

							// Render with inner blocks (same pattern as quote block).
							const orientation = 'row' === flexDirection ? 'horizontal' : 'vertical';
							const orientClass = 'horizontal' === orientation ? 'flex flex-row flex-wrap gap-2' : 'flex flex-col';

							let innerHtml = '';
							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								innerHtml += '<div class=\'ve-inner-blocks ' + orientClass + '\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'' + orientation + '\'>';
								block.innerBlocks.forEach( ( inner, idx ) => {
									// Insertion point before each inner block
									innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
										+ '<div class=\'flex justify-center\'>'
										+ '<button type=\'button\''
										+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
										+ ' data-ve-inner-insert'
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' data-insert-index=\'' + idx + '\''
										+ '>+</button>'
										+ '</div></div>';

									// Read inner block text from DOM if element exists.
									const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
									let innerText = inner.attributes?.text || '';
									if ( existingWrapper ) {
										const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
										innerText = contentEl.innerHTML;
									}

									innerHtml += '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
										+ ' data-block-id=\'' + inner.id + '\''
										+ ' data-inner-block-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' tabindex=\'-1\''
										+ '>'
										+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
										+ ' draggable=\'true\''
										+ ' data-ve-inner-drag-handle'
										+ ' data-inner-drag-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ '>'
										+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
										+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
										+ '</svg></div>';

									// Render inner block content based on type.
									if ( 'heading' === inner.type ) {
										const innerLevel = inner.attributes?.level || 'h2';
										const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
										const innerSize  = this.headingSizeClasses[ innerTag ] || this.headingSizeClasses.h2;
										innerHtml += '<' + innerTag
											+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + this.headingPlaceholder + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</' + innerTag + '></div>';
									} else {
										innerHtml += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }} + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</div></div>';
									}
								} );

								// Insertion point after last inner block
								innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
									+ '<div class=\'flex justify-center\'>'
									+ '<button type=\'button\''
									+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
									+ ' data-ve-inner-insert'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-insert-index=\'' + block.innerBlocks.length + '\''
									+ '>+</button>'
									+ '</div></div>';

								innerHtml += '</div>';
							} else {
								innerHtml += '<div class=\'ve-inner-blocks ' + orientClass + '\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'' + orientation + '\'>'
									+ '<div class=\'ve-inner-blocks-placeholder\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_group_placeholder' ) ) }} + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ '></div></div>';
							}

							return '<' + tag + ' class=\'ve-block ve-block-group ve-block-editing\' style=\'' + inlineStyle + '\'>'
								+ innerHtml
								+ '</' + tag + '>';
						},

						getColumnsBlockHtml( block ) {
							const gap            = block.attributes?.gap || 'medium';
							const vAlign         = block.attributes?.verticalAlignment || 'top';
							const isStacked      = block.attributes?.isStacked || false;
							const colData        = block.attributes?.columns || { mode: 'global', global: 2 };
							const columnsCount   = typeof colData === 'object' ? ( colData.global ?? colData.desktop ?? 2 ) : ( parseInt( colData ) || 2 );

							const gapMap    = { none: '0', small: '0.5rem', medium: '1rem', large: '2rem' };
							const alignMap  = { top: 'flex-start', center: 'center', bottom: 'flex-end', stretch: 'stretch' };

							let inlineStyle = 'display:flex;gap:' + ( gapMap[ gap ] || '1rem' ) + ';align-items:' + ( alignMap[ vAlign ] || 'flex-start' ) + ';';
							if ( isStacked ) {
								inlineStyle += 'flex-direction:column;';
							} else {
								inlineStyle += 'flex-direction:row;flex-wrap:wrap;';
							}

							const hasInnerBlocks = block.innerBlocks && block.innerBlocks.length > 0;

							// No inner blocks: show layout variation picker.
							if ( ! hasInnerBlocks ) {
								return '<div class=\'ve-block ve-block-columns ve-block-editing\' style=\'' + inlineStyle + '\' data-columns=\'' + columnsCount + '\'>'
									+ '<div class=\'ve-columns-layout-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full\'>'
									+ '<p class=\'text-sm text-base-content/60\'>' + this.columnsLayoutLabel + '</p>'
									+ '<div class=\'grid grid-cols-3 gap-2 w-full max-w-sm\'>'

									// 100 (1 column)
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'100\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div class=\'flex-1 bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>100</span>'
									+ '</button>'

									// 50/50
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'50-50\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div class=\'flex-1 bg-base-content/20 rounded\'></div><div class=\'flex-1 bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>50 / 50</span>'
									+ '</button>'

									// 33/66
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'33-66\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:2\' class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>33 / 66</span>'
									+ '</button>'

									// 66/33
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'66-33\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div style=\'flex:2\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>66 / 33</span>'
									+ '</button>'

									// 33/33/33
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'33-33-33\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div class=\'flex-1 bg-base-content/20 rounded\'></div><div class=\'flex-1 bg-base-content/20 rounded\'></div><div class=\'flex-1 bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>33 / 33 / 33</span>'
									+ '</button>'

									// 25/50/25
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'25-50-25\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:2\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>25 / 50 / 25</span>'
									+ '</button>'
									+ '</div>'

									// Skip link
									+ '<button type=\'button\' class=\'text-xs text-primary hover:underline\' data-ve-set-columns-layout=\'skip\'>'
									+ this.columnsSkipLabel
									+ '</button>'
									+ '</div>'
									+ '</div>';
							}

							// Render inner column blocks.
							let innerHtml = '<div class=\'ve-inner-blocks flex\' style=\'' + ( isStacked ? 'flex-direction:column;' : 'flex-direction:row;flex-wrap:nowrap;' ) + 'gap:' + ( gapMap[ gap ] || '1rem' ) + ';align-items:' + ( alignMap[ vAlign ] || 'flex-start' ) + ';\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'' + ( isStacked ? 'vertical' : 'horizontal' ) + '\'>';

							block.innerBlocks.forEach( ( col ) => {
								innerHtml += this.getColumnBlockHtml( col, block.id );
							} );

							// Add column button at the end.
							innerHtml += '<button type=\'button\''
								+ ' class=\'ve-add-column-btn flex items-center justify-center rounded-lg border-2 border-dashed border-base-300 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer\''
								+ ' style=\'min-width:40px;min-height:60px;flex:0 0 40px;align-self:stretch;\''
								+ ' data-ve-add-column'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' title=\'' + this.addColumnLabel + '\''
								+ '>'
								+ '<svg class=\'w-5 h-5 text-base-content/40\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4.5v15m7.5-7.5h-15\' />'
								+ '</svg>'
								+ '</button>';

							innerHtml += '</div>';

							return '<div class=\'ve-block ve-block-columns ve-block-editing\' data-columns=\'' + columnsCount + '\'>'
								+ innerHtml
								+ '</div>';
						},

						getColumnBlockHtml( block, parentId ) {
							const width  = block.attributes?.width || '';
							const vAlign = block.attributes?.verticalAlignment || 'top';

							const alignMap = { top: 'flex-start', center: 'center', bottom: 'flex-end', stretch: 'stretch' };

							let colStyle = 'display:flex;flex-direction:column;justify-content:' + ( alignMap[ vAlign ] || 'flex-start' ) + ';';
							if ( width ) {
								colStyle += 'flex-basis:' + width + ';flex-grow:0;flex-shrink:1;';
							} else {
								colStyle += 'flex:1;';
							}
							colStyle += 'min-width:0;';

							const pid = parentId || block.attributes?._parentId || '';

							let innerHtml = '';
							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col gap-2\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'vertical\'>';
								block.innerBlocks.forEach( ( inner, idx ) => {
									// Insertion point before each inner block
									innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
										+ '<div class=\'flex justify-center\'>'
										+ '<button type=\'button\''
										+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
										+ ' data-ve-inner-insert'
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' data-insert-index=\'' + idx + '\''
										+ '>+</button>'
										+ '</div></div>';

									// Read inner block text from DOM if element exists.
									const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
									let innerText = inner.attributes?.text || '';
									if ( existingWrapper ) {
										const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
										innerText = contentEl.innerHTML;
									}

									innerHtml += '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
										+ ' data-block-id=\'' + inner.id + '\''
										+ ' data-inner-block-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' tabindex=\'-1\''
										+ '>'
										+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
										+ ' draggable=\'true\''
										+ ' data-ve-inner-drag-handle'
										+ ' data-inner-drag-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ '>'
										+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
										+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
										+ '</svg></div>';

									// Render inner block content based on type.
									if ( 'heading' === inner.type ) {
										const innerLevel = inner.attributes?.level || 'h2';
										const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
										const innerSize  = this.headingSizeClasses[ innerTag ] || this.headingSizeClasses.h2;
										innerHtml += '<' + innerTag
											+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + this.headingPlaceholder + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</' + innerTag + '></div>';
									} else {
										innerHtml += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }} + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</div></div>';
									}
								} );

								// Insertion point after last inner block
								innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
									+ '<div class=\'flex justify-center\'>'
									+ '<button type=\'button\''
									+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
									+ ' data-ve-inner-insert'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-insert-index=\'' + block.innerBlocks.length + '\''
									+ '>+</button>'
									+ '</div></div>';

								innerHtml += '</div>';
							} else {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'vertical\'>'
									+ '<div class=\'ve-inner-blocks-placeholder\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + this.columnPlaceholder + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ '></div></div>';
							}

							// Column wrapper intentionally omits data-inner-block-id
							// to prevent the canvas Enter handler from mistaking the
							// column itself for an inner block when creating new
							// paragraphs inside the column's own inner-blocks container.
							return '<div class=\'ve-block ve-block-column ve-block-editing\''
								+ ' style=\'' + colStyle + '\''
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ innerHtml
								+ '</div>';
						},

						getGridBlockHtml( block ) {
							const colData = block.attributes?.columns || { mode: 'global', global: 3, desktop: 3, tablet: 2, mobile: 1 };
							let columns = 3;
							if ( typeof colData === 'object' ) {
								if ( 'responsive' === colData.mode ) {
									const device = Alpine.store( 'editor' )?.devicePreview || 'desktop';
									columns = colData[ device ] ?? colData.desktop ?? 3;
								} else {
									columns = colData.global ?? colData.desktop ?? 3;
								}
							} else {
								columns = colData;
							}

							const gap          = block.attributes?.gap || 'medium';
							const rowGap       = block.attributes?.rowGap || '';
							const alignItems   = block.attributes?.alignItems || 'stretch';
							const justifyItems = block.attributes?.justifyItems || 'stretch';
							const templateRows = block.attributes?.templateRows || 'auto';

							const gapMap   = { none: '0', small: '0.5rem', medium: '1rem', large: '2rem' };
							const gapValue    = gapMap[ gap ] || '1rem';
							const rowGapValue = rowGap ? ( gapMap[ rowGap ] || gapValue ) : gapValue;

							let inlineStyle = 'display:grid;grid-template-columns:repeat(' + columns + ',1fr);grid-template-rows:' + templateRows + ';';
							inlineStyle += 'column-gap:' + gapValue + ';row-gap:' + rowGapValue + ';';
							inlineStyle += 'align-items:' + alignItems + ';justify-items:' + justifyItems + ';';

							const hasInnerBlocks = block.innerBlocks && block.innerBlocks.length > 0;

							// No inner blocks: show grid layout picker.
							if ( ! hasInnerBlocks ) {
								return '<div class=\'ve-block ve-block-grid ve-block-editing\' style=\'' + inlineStyle + '\' data-columns=\'' + columns + '\'>'
									+ '<div class=\'ve-grid-layout-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full\' style=\'grid-column:1/-1;\'>'
									+ '<p class=\'text-sm text-base-content/60\'>' + this.gridLayoutLabel + '</p>'
									+ '<div class=\'grid grid-cols-3 gap-2 w-full max-w-sm\'>'

									// 2 columns
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'2\'>'
									+ '<div class=\'grid grid-cols-2 gap-0.5 w-full h-6\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>2 col</span>'
									+ '</button>'

									// 3 columns
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'3\'>'
									+ '<div class=\'grid grid-cols-3 gap-0.5 w-full h-6\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>3 col</span>'
									+ '</button>'

									// 4 columns
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'4\'>'
									+ '<div class=\'grid grid-cols-4 gap-0.5 w-full h-6\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>4 col</span>'
									+ '</button>'

									// 2x2 grid
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'2x2\'>'
									+ '<div class=\'grid grid-cols-2 grid-rows-2 gap-0.5 w-full h-10\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>2 × 2</span>'
									+ '</button>'

									// 3x2 grid
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'3x2\'>'
									+ '<div class=\'grid grid-cols-3 grid-rows-2 gap-0.5 w-full h-10\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>3 × 2</span>'
									+ '</button>'

									// 3x3 grid
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'3x3\'>'
									+ '<div class=\'grid grid-cols-3 grid-rows-3 gap-0.5 w-full h-12\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>3 × 3</span>'
									+ '</button>'

									+ '</div>'

									// Custom button
									+ '<button type=\'button\' class=\'text-xs text-primary hover:underline\' data-ve-set-grid-layout=\'custom\'>'
									+ this.gridCustomLabel
									+ '</button>'
									+ '</div>'
									+ '</div>';
							}

							// Render inner grid-item blocks.
							let innerHtml = '<div class=\'ve-inner-blocks\' style=\'display:contents;\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'horizontal\'>';

							block.innerBlocks.forEach( ( item ) => {
								innerHtml += this.getGridItemBlockHtml( item, block.id );
							} );

							innerHtml += '</div>';

							// Add grid item button at the end.
							innerHtml += '<button type=\'button\''
								+ ' class=\'ve-add-grid-item-btn flex items-center justify-center rounded-lg border-2 border-dashed border-base-300 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer\''
								+ ' style=\'min-height:60px;\''
								+ ' data-ve-add-grid-item'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' title=\'' + this.addGridItemLabel + '\''
								+ '>'
								+ '<svg class=\'w-5 h-5 text-base-content/40\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4.5v15m7.5-7.5h-15\' />'
								+ '</svg>'
								+ '</button>';

							return '<div class=\'ve-block ve-block-grid ve-block-editing\' style=\'' + inlineStyle + '\' data-columns=\'' + columns + '\'>'
								+ innerHtml
								+ '</div>';
						},

						getGridItemBlockHtml( block, parentId ) {
							const columnSpanData = block.attributes?.columnSpan || { mode: 'global', global: 1 };
							let columnSpan = 1;
							if ( typeof columnSpanData === 'object' ) {
								if ( 'responsive' === columnSpanData.mode ) {
									const device = Alpine.store( 'editor' )?.devicePreview || 'desktop';
									columnSpan = columnSpanData[ device ] ?? columnSpanData.desktop ?? 1;
								} else {
									columnSpan = columnSpanData.global ?? columnSpanData.desktop ?? 1;
								}
							} else {
								columnSpan = columnSpanData;
							}

							const rowSpanData = block.attributes?.rowSpan || { mode: 'global', global: 1 };
							let rowSpan = 1;
							if ( typeof rowSpanData === 'object' ) {
								if ( 'responsive' === rowSpanData.mode ) {
									const device = Alpine.store( 'editor' )?.devicePreview || 'desktop';
									rowSpan = rowSpanData[ device ] ?? rowSpanData.desktop ?? 1;
								} else {
									rowSpan = rowSpanData.global ?? rowSpanData.desktop ?? 1;
								}
							} else {
								rowSpan = rowSpanData;
							}

							const verticalAlignment = block.attributes?.verticalAlignment || 'stretch';
							const pid = parentId || block.attributes?._parentId || '';

							let itemStyle = '';
							if ( columnSpan > 1 ) { itemStyle += 'grid-column:span ' + columnSpan + ';'; }
							if ( rowSpan > 1 ) { itemStyle += 'grid-row:span ' + rowSpan + ';'; }
							if ( 'stretch' !== verticalAlignment ) { itemStyle += 'align-self:' + verticalAlignment + ';'; }

							let innerHtml = '';
							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col gap-2\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'vertical\'>';
								block.innerBlocks.forEach( ( inner, idx ) => {
									// Insertion point before each inner block
									innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
										+ '<div class=\'flex justify-center\'>'
										+ '<button type=\'button\''
										+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
										+ ' data-ve-inner-insert'
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' data-insert-index=\'' + idx + '\''
										+ '>+</button>'
										+ '</div></div>';

									// Read inner block text from DOM if element exists.
									const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
									let innerText = inner.attributes?.text || '';
									if ( existingWrapper ) {
										const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
										innerText = contentEl.innerHTML;
									}

									innerHtml += '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
										+ ' data-block-id=\'' + inner.id + '\''
										+ ' data-inner-block-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ ' tabindex=\'-1\''
										+ '>'
										+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
										+ ' draggable=\'true\''
										+ ' data-ve-inner-drag-handle'
										+ ' data-inner-drag-id=\'' + inner.id + '\''
										+ ' data-parent-id=\'' + block.id + '\''
										+ '>'
										+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
										+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
										+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
										+ '</svg></div>';

									// Render inner block content based on type.
									if ( 'heading' === inner.type ) {
										const innerLevel = inner.attributes?.level || 'h2';
										const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
										const innerSize  = this.headingSizeClasses[ innerTag ] || this.headingSizeClasses.h2;
										innerHtml += '<' + innerTag
											+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + this.headingPlaceholder + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</' + innerTag + '></div>';
									} else {
										innerHtml += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
											+ ' contenteditable=\'true\''
											+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_paragraph_placeholder' ) ) }} + '\''
											+ ' data-ve-enter-new-block=\'true\''
											+ ' data-ve-slash-command=\'true\''
											+ '>' + innerText + '</div></div>';
									}
								} );

								// Insertion point after last inner block
								innerHtml += '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
									+ '<div class=\'flex justify-center\'>'
									+ '<button type=\'button\''
									+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
									+ ' data-ve-inner-insert'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-insert-index=\'' + block.innerBlocks.length + '\''
									+ '>+</button>'
									+ '</div></div>';

								innerHtml += '</div>';
							} else {
								innerHtml += '<div class=\'ve-inner-blocks flex flex-col\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'vertical\'>'
									+ '<div class=\'ve-inner-blocks-placeholder\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + this.gridItemPlaceholder + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ '></div></div>';
							}

							return '<div class=\'ve-block ve-block-grid-item ve-block-editing\''
								+ ( itemStyle ? ' style=\'' + itemStyle + '\'' : '' )
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ innerHtml
								+ '</div>';
						},

						getGalleryBlockHtml( block ) {
							const colData = block.attributes?.columns || { mode: 'global', global: 3, desktop: 3, tablet: 2, mobile: 1 };
							let columns   = 3;
							if ( typeof colData === 'object' ) {
								if ( colData.mode === 'responsive' ) {
									const device = Alpine.store( 'editor' )?.devicePreview || 'desktop';
									columns = colData[ device ] ?? colData.desktop ?? 3;
								} else {
									columns = colData.global ?? colData.desktop ?? 3;
								}
							} else {
								columns = colData;
							}
							const gap         = block.attributes?.gap ?? 1;
							const crop        = block.attributes?.crop !== undefined ? block.attributes.crop : true;
							const aspectRatio = block.attributes?.aspectRatio || 'original';

							const gapValue = gap + 'rem';

							let cellStyle = '';
							if ( crop ) {
								const arValue = 'original' !== aspectRatio ? aspectRatio : '1/1';
								cellStyle = 'object-fit:cover;aspect-ratio:' + arValue + ';width:100%;height:100%;';
							}

							let figClasses = 've-block ve-block-gallery ve-block-editing';
							if ( crop ) { figClasses += ' ve-block-gallery--crop'; }

							// Empty gallery: show placeholder
							if ( ! block.innerBlocks || 0 === block.innerBlocks.length ) {
								const ctx = block.id + ':gallery-add';
								return '<figure class=\'' + figClasses + '\'>'
									+ '<div class=\'ve-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content\'>'
									+ '<div class=\'ve-block-placeholder__icon text-base-content/70\'>'
									+ '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' width=\'48\' height=\'48\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z\' />'
									+ '</svg>'
									+ '</div>'
									+ '<p class=\'ve-block-placeholder__name font-medium text-base-content\'>' + this.galleryName + '</p>'
									+ '<p class=\'ve-block-placeholder__description text-sm text-base-content/70\'>' + this.galleryDesc + '</p>'
									+ '<div class=\'ve-block-placeholder__actions flex gap-2\'>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-primary\' data-ve-media-context=\'' + ctx + '\'>' + this.imageUpload + '</button>'
									+ '<button type=\'button\' class=\'btn btn-sm btn-outline\' data-ve-media-context=\'' + ctx + '\'>' + this.imageMediaLib + '</button>'
									+ '</div>'
									+ '</div>'
									+ '</figure>';
							}

							// Gallery with inner image blocks
							let gridHtml = '<div class=\'ve-block-gallery__grid\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\''
								+ ' style=\'display:grid;grid-template-columns:repeat(' + columns + ',1fr);gap:' + gapValue + ';\'>';

							block.innerBlocks.forEach( ( inner ) => {
								const imgUrl = inner.attributes?.url || '';
								const imgAlt = ( inner.attributes?.alt || '' ).replace( /'/g, '\u0026#39;' );

								gridHtml += '<div class=\'ve-inner-block-wrapper ve-block-gallery__item relative group/inner-block\''
									+ ' data-block-id=\'' + inner.id + '\''
									+ ' data-inner-block-id=\'' + inner.id + '\''
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' tabindex=\'-1\''
									+ ' style=\'overflow:hidden;\''
									+ '>';

								if ( imgUrl ) {
									gridHtml += '<img src=\'' + imgUrl + '\' alt=\'' + imgAlt + '\''
										+ ( cellStyle ? ' style=\'' + cellStyle + '\'' : '' )
										+ ' />';
								} else {
									gridHtml += '<div class=\'flex items-center justify-center bg-base-200 text-base-content/40 min-h-[80px]\''
										+ ( cellStyle ? ' style=\'' + cellStyle + '\'' : '' )
										+ '>'
										+ '<svg class=\'w-8 h-8\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\' stroke-width=\'1.5\'>'
										+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z\' />'
										+ '</svg>'
										+ '</div>';
								}

								gridHtml += '</div>';
							} );

							gridHtml += '</div>';

							return '<figure class=\'' + figClasses + '\'>' + gridHtml + '</figure>';
						},

						handleInput( event ) {
							const target = event.target;
							if ( ! target.hasAttribute( 'contenteditable' ) ) return;

							this._markEmpty( target );
							target.querySelectorAll( '[data-placeholder]' ).forEach( this._markEmpty );

							// Inner block and citation content lives in the DOM during
							// editing (synced to store on blur). Updating the store here
							// would trigger x-html re-render and steal focus.
							if ( target.hasAttribute( 'data-inner-block-id' ) || target.closest( '[data-inner-block-id]' ) ) {
								return;
							}
							if ( target.classList.contains( 've-quote-citation' ) ) {
								return;
							}
							// Table cell and details summary content lives in DOM during editing.
							if ( target.hasAttribute( 'data-row' ) && target.hasAttribute( 'data-col' ) ) {
								return;
							}
							if ( target.classList.contains( 've-details-summary' ) ) {
								return;
							}
							if ( target.tagName === 'CAPTION' && target.closest( '.ve-block-table' ) ) {
								return;
							}

							const blockEl = target.closest( '[data-block-id]' );
							if ( ! blockEl ) return;

							const blockId = blockEl.getAttribute( 'data-block-id' );
							const block   = Alpine.store( 'editor' ).getBlock( blockId );
							if ( ! block ) return;

							const textContent = target.innerHTML;
							if ( 'heading' === block.type || 'paragraph' === block.type ) {
								Alpine.store( 'editor' ).updateBlock( blockId, { text: textContent } );
							} else if ( 'quote' === block.type ) {
								if ( target.classList.contains( 've-quote-citation' ) ) {
									Alpine.store( 'editor' ).updateBlock( blockId, { citation: textContent } );
								} else {
									Alpine.store( 'editor' ).updateBlock( blockId, { text: textContent } );
								}
							}
							// List blocks: no attribute update needed — content lives in the DOM.
						},

						/**
						 * Sync all table cell content and caption from the DOM
						 * into the store in a single updateBlock call.
						 */
						_syncTableCellsToStore( store, blockEl, blockId ) {
							const block = store.getBlock( blockId );
							if ( ! block || ! block.attributes?.rows ) return;

							const tableEl = blockEl.querySelector( 'table' );
							if ( ! tableEl ) return;

							const rows = JSON.parse( JSON.stringify( block.attributes.rows ) );
							tableEl.querySelectorAll( '[data-row][data-col]' ).forEach( ( cell ) => {
								const r = parseInt( cell.getAttribute( 'data-row' ) );
								const c = parseInt( cell.getAttribute( 'data-col' ) );
								if ( rows[ r ] && rows[ r ][ c ] ) {
									rows[ r ][ c ].content = cell.innerHTML;
								}
							} );

							const updates = { rows: rows };
							const captionEl = tableEl.querySelector( 'caption' );
							if ( captionEl ) {
								updates.caption = captionEl.innerHTML;
							}
							store.updateBlock( blockId, updates );
						},
					}"
					x-on:input="handleInput( $event )"
					x-on:click="
						const mediaBtn = $event.target.closest( '[data-ve-media-context]' );
						if ( mediaBtn ) {
							Livewire.dispatch( 'open-ve-media-picker', { context: mediaBtn.getAttribute( 'data-ve-media-context' ) } );
						}

						// Cover block color swatch: set color-only mode with selected overlay color.
						const coverColorBtn = $event.target.closest( '[data-ve-cover-color]' );
						if ( coverColorBtn ) {
							const color   = coverColorBtn.getAttribute( 'data-ve-cover-color' );
							const blockEl = coverColorBtn.closest( '[data-block-id]' );
							if ( blockEl ) {
								const blockId = blockEl.getAttribute( 'data-block-id' );
								const store   = Alpine.store( 'editor' );
								if ( store ) {
									store.updateBlock( blockId, { mediaType: 'color', overlayColor: color, overlayOpacity: 100 } );
								}
							}
						}

						// Cover block featured image: use the provided featured image URL.
						const coverFeaturedBtn = $event.target.closest( '[data-ve-cover-featured-image]' );
						if ( coverFeaturedBtn ) {
							const url     = coverFeaturedBtn.getAttribute( 'data-ve-cover-featured-image' );
							const blockEl = coverFeaturedBtn.closest( '[data-block-id]' );
							if ( blockEl && url ) {
								const blockId = blockEl.getAttribute( 'data-block-id' );
								const store   = Alpine.store( 'editor' );
								if ( store ) {
									store.updateBlock( blockId, { mediaType: 'image', mediaUrl: url, overlayOpacity: 50 } );
								}
							}
						}

						// Form block: select a form from the placeholder dropdown.
						const formSelectBtn = $event.target.closest( '[data-ve-form-select-btn]' );
						if ( formSelectBtn ) {
							const fBlockId = formSelectBtn.getAttribute( 'data-ve-form-select-btn' );
							const fSelect  = formSelectBtn.parentElement?.querySelector( 'select[data-ve-form-select]' );
							if ( fSelect ) {
								const store = Alpine.store( 'editor' );
								if ( store ) {
									store.updateBlock( fBlockId, { formId: fSelect.value ? parseInt( fSelect.value, 10 ) : null } );
								}
							}
						}

						// Table layout picker: create table from preset or custom config.
						const tableLayoutBtn = $event.target.closest( '[data-ve-set-table-layout]' );
						if ( tableLayoutBtn ) {
							const layout  = tableLayoutBtn.getAttribute( 'data-ve-set-table-layout' );
							const blockEl = tableLayoutBtn.closest( '[data-block-id]' );
							if ( ! blockEl ) return;

							const blockId = blockEl.getAttribute( 'data-block-id' );
							const store   = Alpine.store( 'editor' );
							if ( ! store ) return;

							const newCell = () => ( { content: '', colSpan: 1, rowSpan: 1, alignment: 'left' } );
							const makeRow = ( cols ) => Array.from( { length: cols }, newCell );
							let bodyRows, cols, hasHeader, hasFooter;

							if ( 'custom' === layout ) {
								const picker   = blockEl.querySelector( '.ve-table-custom-builder' );
								const colInput = picker ? picker.querySelector( '[data-ve-table-custom-cols]' ) : null;
								const rowInput = picker ? picker.querySelector( '[data-ve-table-custom-rows]' ) : null;
								const headerCb = picker ? picker.querySelector( '[data-ve-table-custom-header]' ) : null;
								const footerCb = picker ? picker.querySelector( '[data-ve-table-custom-footer]' ) : null;
								cols      = Math.max( 1, Math.min( 20, parseInt( colInput?.value ) || 3 ) );
								bodyRows  = Math.max( 1, Math.min( 50, parseInt( rowInput?.value ) || 3 ) );
								hasHeader = headerCb ? headerCb.checked : false;
								hasFooter = footerCb ? footerCb.checked : false;
							} else {
								bodyRows  = parseInt( tableLayoutBtn.getAttribute( 'data-body-rows' ) ) || 2;
								cols      = parseInt( tableLayoutBtn.getAttribute( 'data-cols' ) ) || 2;
								hasHeader = '1' === tableLayoutBtn.getAttribute( 'data-header' );
								hasFooter = '1' === tableLayoutBtn.getAttribute( 'data-footer' );
							}

							const rows = [];
							if ( hasHeader ) { rows.push( makeRow( cols ) ); }
							for ( let i = 0; i < bodyRows; i++ ) { rows.push( makeRow( cols ) ); }
							if ( hasFooter ) { rows.push( makeRow( cols ) ); }

							store.updateBlock( blockId, {
								rows: rows,
								hasHeaderRow: hasHeader,
								hasFooterRow: hasFooter,
							} );
						}

						// Table actions: add/insert/delete rows and columns.
						const tableAction = $event.target.closest( '[data-ve-table-action]' );
						if ( tableAction ) {
							const action  = tableAction.getAttribute( 'data-ve-table-action' );
							const blockEl = tableAction.closest( '[data-block-id]' );
							if ( ! blockEl ) return;

							const blockId = blockEl.getAttribute( 'data-block-id' );
							const store   = Alpine.store( 'editor' );
							if ( ! store ) return;
							const block = store.getBlock( blockId );
							if ( ! block || ! block.attributes?.rows ) return;

							// Sync all cell content from DOM before modifying rows.
							this._syncTableCellsToStore( store, blockEl, blockId );

							const rows    = JSON.parse( JSON.stringify( store.getBlock( blockId ).attributes.rows ) );
							const numCols = rows[ 0 ] ? rows[ 0 ].length : 2;
							const newCell = () => ( { content: '', colSpan: 1, rowSpan: 1, alignment: 'left' } );
							const makeRow = ( cols ) => Array.from( { length: cols }, newCell );

							if ( 'add-row' === action ) {
								const hasFooter = block.attributes?.hasFooterRow || false;
								const insertIdx = hasFooter && rows.length > 1 ? rows.length - 1 : rows.length;
								rows.splice( insertIdx, 0, makeRow( numCols ) );
								store.updateBlock( blockId, { rows: rows } );
							} else if ( 'add-column' === action ) {
								rows.forEach( ( row ) => { row.push( newCell() ); } );
								store.updateBlock( blockId, { rows: rows } );
							} else if ( 'insert-row-above' === action ) {
								const rowIdx = parseInt( tableAction.getAttribute( 'data-action-row' ) );
								rows.splice( rowIdx, 0, makeRow( numCols ) );
								store.updateBlock( blockId, { rows: rows } );
							} else if ( 'delete-row' === action ) {
								const rowIdx = parseInt( tableAction.getAttribute( 'data-action-row' ) );
								if ( rows.length > 1 ) {
									rows.splice( rowIdx, 1 );
									const updates = { rows: rows };
									// If we deleted the header row (index 0) and header was on, turn it off.
									if ( 0 === rowIdx && block.attributes?.hasHeaderRow ) {
										updates.hasHeaderRow = false;
									}
									// If we deleted the footer row (last index) and footer was on, turn it off.
									if ( rowIdx === rows.length && block.attributes?.hasFooterRow ) {
										updates.hasFooterRow = false;
									}
									store.updateBlock( blockId, updates );
								}
							} else if ( 'insert-col-left' === action ) {
								const colIdx = parseInt( tableAction.getAttribute( 'data-action-col' ) );
								rows.forEach( ( row ) => { row.splice( colIdx, 0, newCell() ); } );
								store.updateBlock( blockId, { rows: rows } );
							} else if ( 'delete-col' === action ) {
								const colIdx = parseInt( tableAction.getAttribute( 'data-action-col' ) );
								if ( numCols > 1 ) {
									rows.forEach( ( row ) => { row.splice( colIdx, 1 ); } );
									store.updateBlock( blockId, { rows: rows } );
								}
							}
						}

						// Embed, map, and coordinate buttons are handled in init()
						// via el.addEventListener('click', ...) to avoid scoping issues.
					"
					x-on:ve-insertion-point-click="
						if ( Alpine.store( 'editor' ) && $event.detail ) {
							const newBlock = Alpine.store( 'editor' ).addBlock( { type: Alpine.store( 'editor' ).defaultBlockType }, $event.detail.index );
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
				>
						<template x-for="( block, index ) in $store.editor.blocks" :key="block.id">
							<div>
								{{-- Insertion point before each block --}}
								<div
									class="relative group/insert py-1"
									x-on:mouseenter="$el._hovered = true"
									x-on:mouseleave="$el._hovered = false"
								>
									<div
										class="absolute inset-x-0 top-1/2 -translate-y-px h-0.5 bg-primary/0 group-hover/insert:bg-primary/30 transition-colors"
										aria-hidden="true"
									></div>
									<div class="flex justify-center">
										<button
											type="button"
											class="w-6 h-6 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/insert:opacity-100 focus:opacity-100 transition-opacity shadow-sm hover:shadow-md"
											x-on:click.stop="openInserterPopover( index )"
											:aria-label="'{{ __( 'Add block at position' ) }} ' + ( index + 1 )"
										>
											<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
												<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
											</svg>
										</button>
									</div>

									{{-- Inline inserter popover --}}
									<div
										x-show="inserterPopover.open && inserterPopover.index === index"
										x-on:click.outside="inserterPopover.open = false"
										x-on:keydown.escape.window="if ( inserterPopover.open ) { inserterPopover.open = false; }"
										x-transition:enter="transition ease-out duration-100"
										x-transition:enter-start="opacity-0 scale-95"
										x-transition:enter-end="opacity-100 scale-100"
										x-transition:leave="transition ease-in duration-75"
										x-transition:leave-start="opacity-100 scale-100"
										x-transition:leave-end="opacity-0 scale-95"
										class="absolute left-1/2 -translate-x-1/2 top-full mt-1 w-56 rounded-lg border border-base-300 bg-base-100 shadow-lg overflow-hidden z-50"
									>
										<div class="px-3 py-2">
											<div class="grid grid-cols-3 gap-1">
												<template x-for="iBlock in inserterBlocks.slice( 0, 3 )" :key="iBlock.name">
													<button
														type="button"
														class="flex flex-col items-center gap-1 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors text-center"
														x-on:click="insertFromPopover( iBlock.name )"
													>
														<div class="w-10 h-10 flex items-center justify-center rounded bg-base-200 text-base-content/60">
															<span class="w-5 h-5 flex items-center justify-center" x-html="inserterBlockIcons[ iBlock.name ] || inserterBlockIcons[ Object.keys( inserterBlockIcons )[0] ]"></span>
														</div>
														<span class="text-xs font-medium text-base-content leading-tight" x-text="iBlock.label || iBlock.name"></span>
													</button>
												</template>
											</div>
										</div>
										<div class="border-t border-base-300 px-3 py-2">
											<button
												type="button"
												class="w-full text-sm text-primary hover:text-primary-focus font-medium text-center py-1 rounded hover:bg-base-200 transition-colors"
												x-on:click="inserterPopover.open = false; if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).openInserter(); }"
											>
												{{ __( 'Browse all' ) }}
											</button>
										</div>
									</div>
								</div>

								{{-- Block wrapper: draggable is on this div, x-html on a child --}}
								<div
									:data-block-id="block.id"
									:data-block-type="block.type"
									:data-align="block.attributes?.align || 'none'"
									tabindex="-1"
									class="ve-canvas-block relative group"
									:class="[
										$store.selection?.focused === block.id ? 'ring-2 ring-primary ring-offset-2 rounded' : '',
										'align' + ( block.attributes?.align || 'none' ),
									].filter(Boolean).join(' ')"
									:style="buildBlockStyle( block.attributes || {} )"
									x-on:dragover.prevent="if ( ! $event.target.closest( '[data-ve-inner-blocks]' ) ) { $el.classList.add( 'ring-2', 'ring-info', 'ring-offset-1' ); }"
									x-on:dragleave="$el.classList.remove( 'ring-2', 'ring-info', 'ring-offset-1' )"
									x-on:drop="
										$el.classList.remove( 'ring-2', 'ring-info', 'ring-offset-1' );
										if ( $event.target.closest( '[data-ve-inner-blocks]' ) ) { return; }
										if ( draggingBlockId && draggingBlockId !== block.id && Alpine.store( 'editor' ) ) {
											$event.preventDefault();
											$event.stopPropagation();
											const store       = Alpine.store( 'editor' );
											const targetIndex = store.getBlockIndex( block.id );
											const topIndex    = store.getBlockIndex( draggingBlockId );

											if ( -1 !== topIndex && -1 !== targetIndex ) {
												store.moveBlock( draggingBlockId, targetIndex );
											} else if ( -1 === topIndex && -1 !== targetIndex ) {
												const dragBlock  = store.getBlock( draggingBlockId );
												const dragParent = store.getParentBlock( draggingBlockId );
												if ( dragBlock && dragParent ) {
													const blockData = JSON.parse( JSON.stringify( dragBlock ) );
													store.removeInnerBlock( dragParent.id, draggingBlockId );
													store.addBlock( {
														type: blockData.type,
														attributes: blockData.attributes,
														innerBlocks: blockData.innerBlocks || [],
													}, targetIndex );
												}
											}
											draggingBlockId = null;
										}
									"
								>
									<div x-html="getBlockHtml( block )"></div>
								</div>
							</div>
						</template>

						{{-- Inner block inserter popover (floating) --}}
						<div
							x-show="innerInserterPopover.open"
							x-on:click.outside="innerInserterPopover.open = false"
							x-on:keydown.escape.window="innerInserterPopover.open = false"
							x-transition:enter="transition ease-out duration-100"
							x-transition:enter-start="opacity-0 scale-95"
							x-transition:enter-end="opacity-100 scale-100"
							x-transition:leave="transition ease-in duration-75"
							x-transition:leave-start="opacity-100 scale-100"
							x-transition:leave-end="opacity-0 scale-95"
							:style="{ position: 'absolute', top: innerInserterPopover.top + 'px', left: innerInserterPopover.left + 'px', zIndex: 50 }"
							class="w-56 rounded-lg border border-base-300 bg-base-100 shadow-lg overflow-hidden"
						>
							<div class="px-3 py-2">
								<div class="grid grid-cols-3 gap-1">
									<template x-for="iBlock in inserterBlocks.slice( 0, 3 )" :key="'inner-' + iBlock.name">
										<button
											type="button"
											class="flex flex-col items-center gap-1 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors text-center"
											x-on:click="insertInnerFromPopover( iBlock.name )"
										>
											<div class="w-10 h-10 flex items-center justify-center rounded bg-base-200 text-base-content/60">
												<span class="w-5 h-5 flex items-center justify-center" x-html="inserterBlockIcons[ iBlock.name ] || inserterBlockIcons[ Object.keys( inserterBlockIcons )[0] ]"></span>
											</div>
											<span class="text-xs font-medium text-base-content leading-tight" x-text="iBlock.label || iBlock.name"></span>
										</button>
									</template>
								</div>
							</div>
							<div class="border-t border-base-300 px-3 py-2">
								<button
									type="button"
									class="w-full text-sm text-primary hover:text-primary-focus font-medium text-center py-1 rounded hover:bg-base-200 transition-colors"
									x-on:click="innerInserterPopover.open = false; if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).openInserter(); }"
								>
									{{ __( 'Browse all' ) }}
								</button>
							</div>
						</div>

						{{-- Final insertion point after last block --}}
						<div
							class="relative group/insert py-1"
							x-on:mouseenter="$el._hovered = true"
							x-on:mouseleave="$el._hovered = false"
						>
							<div
								class="absolute inset-x-0 top-1/2 -translate-y-px h-0.5 bg-primary/0 group-hover/insert:bg-primary/30 transition-colors"
								aria-hidden="true"
							></div>
							<div class="flex justify-center">
								<button
									type="button"
									class="w-6 h-6 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/insert:opacity-100 focus:opacity-100 transition-opacity shadow-sm hover:shadow-md"
									x-on:click.stop="openInserterPopover( $store.editor.blocks.length )"
									aria-label="{{ __( 'Add block at end' ) }}"
								>
									<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
										<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
									</svg>
								</button>
							</div>

							{{-- Inline inserter popover --}}
							<div
								x-show="inserterPopover.open && inserterPopover.index === $store.editor.blocks.length"
								x-on:click.outside="inserterPopover.open = false"
								x-on:keydown.escape.window="if ( inserterPopover.open ) { inserterPopover.open = false; }"
								x-transition:enter="transition ease-out duration-100"
								x-transition:enter-start="opacity-0 scale-95"
								x-transition:enter-end="opacity-100 scale-100"
								x-transition:leave="transition ease-in duration-75"
								x-transition:leave-start="opacity-100 scale-100"
								x-transition:leave-end="opacity-0 scale-95"
								class="absolute left-1/2 -translate-x-1/2 top-full mt-1 w-56 rounded-lg border border-base-300 bg-base-100 shadow-lg overflow-hidden z-50"
							>
								<div class="px-3 py-2">
									<div class="grid grid-cols-3 gap-1">
										<template x-for="iBlock in inserterBlocks.slice( 0, 3 )" :key="iBlock.name">
											<button
												type="button"
												class="flex flex-col items-center gap-1 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors text-center"
												x-on:click="insertFromPopover( iBlock.name )"
											>
												<div class="w-10 h-10 flex items-center justify-center rounded bg-base-200 text-base-content/60">
													<span class="w-5 h-5 flex items-center justify-center" x-html="inserterBlockIcons[ iBlock.name ] || inserterBlockIcons[ Object.keys( inserterBlockIcons )[0] ]"></span>
												</div>
												<span class="text-xs font-medium text-base-content leading-tight" x-text="iBlock.label || iBlock.name"></span>
											</button>
										</template>
									</div>
								</div>
								<div class="border-t border-base-300 px-3 py-2">
									<button
										type="button"
										class="w-full text-sm text-primary hover:text-primary-focus font-medium text-center py-1 rounded hover:bg-base-200 transition-colors"
										x-on:click="inserterPopover.open = false; if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).openInserter(); }"
									>
										{{ __( 'Browse all' ) }}
									</button>
								</div>
							</div>
						</div>

						{{-- Quick-add zone: click to append a new paragraph block --}}
						<div
							class="min-h-[150px] cursor-text"
							x-on:click="
								if ( Alpine.store( 'editor' ) ) {
									const newBlock = Alpine.store( 'editor' ).addBlock( { type: Alpine.store( 'editor' ).defaultBlockType } );
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
							aria-label="{{ __( 'Click to add a new block' ) }}"
							role="button"
							tabindex="0"
						></div>

					</div>
				</x-ve-editor-canvas>
