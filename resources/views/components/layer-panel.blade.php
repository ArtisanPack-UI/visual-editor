{{--
 * Layer Panel Component
 *
 * Provides a List View (block tree) and Outline (heading structure)
 * for the Layers tab in the left sidebar.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		activeView: {{ Js::from( $activeView ) }},
		fallbackLabels: {
			heading: {{ Js::from( __( 'visual-editor::ve.block_type_heading' ) ) }},
			paragraph: {{ Js::from( __( 'visual-editor::ve.block_type_paragraph' ) ) }},
			image: {{ Js::from( __( 'visual-editor::ve.block_type_image' ) ) }},
		},

		get blocks() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).blocks : [];
		},

		get headings() {
			return this.blocks.filter( ( b ) => 'heading' === b.type );
		},

		get wordCount() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).getWordCount() : 0;
		},

		get charCount() {
			let count = 0;
			const countChars = ( blocks ) => {
				blocks.forEach( ( block ) => {
					const attrs = block.attributes || {};
					[ 'content', 'text', 'caption' ].forEach( ( key ) => {
						if ( attrs[ key ] && 'string' === typeof attrs[ key ] ) {
							count += attrs[ key ].replace( /<[^>]*>/g, '' ).length;
						}
					} );
					if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
						countChars( block.innerBlocks );
					}
				} );
			};
			countChars( this.blocks );
			return count;
		},

		get readingTime() {
			return Math.max( 1, Math.ceil( this.wordCount / 200 ) );
		},

		selectBlock( blockId ) {
			if ( Alpine.store( 'selection' ) ) {
				Alpine.store( 'selection' ).select( blockId );
			}
		},

		getBlockLabel( block ) {
			const type = 'string' === typeof block?.type && block.type.length > 0 ? block.type : '';
			if ( 'heading' === type ) {
				const content = ( block.attributes?.content || block.attributes?.text || '' ).replace( /<[^>]*>/g, '' );
				return content || this.fallbackLabels.heading;
			}
			if ( 'paragraph' === type ) {
				const content = ( block.attributes?.content || block.attributes?.text || '' ).replace( /<[^>]*>/g, '' );
				return content.length > 40 ? content.substring( 0, 40 ) + '…' : content || this.fallbackLabels.paragraph;
			}
			if ( 'image' === type ) {
				return block.attributes?.alt || this.fallbackLabels.image;
			}
			if ( '' === type ) {
				return this.fallbackLabels.paragraph;
			}
			return this.fallbackLabels[ type ] || type.charAt( 0 ).toUpperCase() + type.slice( 1 );
		},

		getBlockIcon( block ) {
			const icons = {
				heading: 'H',
				paragraph: '¶',
				image: '🖼',
				list: '☰',
				quote: '❝',
				code: '</>',
				table: '▦',
				separator: '—',
			};
			return icons[ block.type ] || '◻';
		},

		{{-- Drag-and-drop state for the List View --}}
		layerDraggingId: null,
		layerDragOverId: null,
		layerDraggingParentId: null,

		handleLayerDragStart( event, block, parentId ) {
			this.layerDraggingId = block.id;
			this.layerDraggingParentId = parentId || null;
			event.dataTransfer.setData( 'text/plain', block.id );
			event.dataTransfer.effectAllowed = 'move';

			{{-- Delay adding drag class for visual feedback --}}
			requestAnimationFrame( () => {
				event.target.classList.add( 'opacity-50' );
			} );
		},

		handleLayerDragEnd( event ) {
			event.target.classList.remove( 'opacity-50' );
			this.layerDraggingId = null;
			this.layerDragOverId = null;
			this.layerDraggingParentId = null;
		},

		handleLayerDragOver( event, block ) {
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			this.layerDragOverId = block.id;
		},

		handleLayerDrop( event, targetBlock, targetParentId ) {
			event.preventDefault();
			this.layerDragOverId = null;

			if ( ! this.layerDraggingId || this.layerDraggingId === targetBlock.id ) return;

			const store = Alpine.store( 'editor' );
			if ( ! store ) return;

			const dragParentId   = this.layerDraggingParentId;
			targetParentId       = targetParentId || null;

			if ( dragParentId && targetParentId && dragParentId === targetParentId ) {
				{{-- Same parent: reorder within inner blocks --}}
				const parent    = store.getBlock( dragParentId );
				const targetIdx = parent?.innerBlocks?.findIndex( ( b ) => b.id === targetBlock.id ) ?? -1;
				if ( -1 !== targetIdx ) {
					store.moveInnerBlock( dragParentId, this.layerDraggingId, targetIdx );
				}
			} else if ( ! dragParentId && ! targetParentId ) {
				{{-- Both top-level: reorder top-level blocks --}}
				const targetIndex = store.getBlockIndex( targetBlock.id );
				if ( -1 !== targetIndex ) {
					store.moveBlock( this.layerDraggingId, targetIndex );
				}
			} else {
				{{-- Cross-context: move between top-level and inner blocks --}}
				const dragBlock = store.getBlock( this.layerDraggingId );
				if ( ! dragBlock ) { this.layerDraggingId = null; return; }
				const blockData = JSON.parse( JSON.stringify( dragBlock ) );

				if ( dragParentId ) {
					store.removeInnerBlock( dragParentId, this.layerDraggingId );
				} else {
					store.removeBlock( this.layerDraggingId );
				}

				if ( targetParentId ) {
					const parent    = store.getBlock( targetParentId );
					const targetIdx = parent?.innerBlocks?.findIndex( ( b ) => b.id === targetBlock.id ) ?? -1;
					store.addInnerBlock( targetParentId, { type: blockData.type, attributes: blockData.attributes, innerBlocks: blockData.innerBlocks || [] }, -1 !== targetIdx ? targetIdx : null );
				} else {
					const targetIndex = store.getBlockIndex( targetBlock.id );
					store.addBlock( { type: blockData.type, attributes: blockData.attributes, innerBlocks: blockData.innerBlocks || [] }, -1 !== targetIndex ? targetIndex : null );
				}
			}

			this.layerDraggingId = null;
			this.layerDraggingParentId = null;
		},
	}"
	{{ $attributes->merge( [ 'class' => 've-layer-panel flex flex-col h-full' ] ) }}
	aria-label="{{ $label ?? __( 'visual-editor::ve.layer_panel' ) }}"
>
	{{-- Sub-tab switcher --}}
	<div class="flex border-b border-base-300" role="tablist" aria-label="{{ $label ?? __( 'visual-editor::ve.layer_panel' ) }}">
		<button
			type="button"
			id="{{ $uuid }}-list-tab"
			class="flex-1 px-3 py-2 text-xs font-medium text-center transition-colors"
			:class="'list' === activeView ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
			x-on:click="activeView = 'list'; $nextTick( () => $el.focus() )"
			x-on:keydown.arrow-right.prevent="activeView = 'outline'; $nextTick( () => document.getElementById( '{{ $uuid }}-outline-tab' ).focus() )"
			x-on:keydown.arrow-left.prevent="activeView = 'outline'; $nextTick( () => document.getElementById( '{{ $uuid }}-outline-tab' ).focus() )"
			x-on:keydown.home.prevent="activeView = 'list'; $nextTick( () => $el.focus() )"
			x-on:keydown.end.prevent="activeView = 'outline'; $nextTick( () => document.getElementById( '{{ $uuid }}-outline-tab' ).focus() )"
			role="tab"
			:aria-selected="'list' === activeView"
			:tabindex="'list' === activeView ? 0 : -1"
			aria-controls="{{ $uuid }}-list-panel"
		>
			{{ __( 'visual-editor::ve.list_view' ) }}
		</button>
		<button
			type="button"
			id="{{ $uuid }}-outline-tab"
			class="flex-1 px-3 py-2 text-xs font-medium text-center transition-colors"
			:class="'outline' === activeView ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
			x-on:click="activeView = 'outline'; $nextTick( () => $el.focus() )"
			x-on:keydown.arrow-left.prevent="activeView = 'list'; $nextTick( () => document.getElementById( '{{ $uuid }}-list-tab' ).focus() )"
			x-on:keydown.arrow-right.prevent="activeView = 'list'; $nextTick( () => document.getElementById( '{{ $uuid }}-list-tab' ).focus() )"
			x-on:keydown.home.prevent="activeView = 'list'; $nextTick( () => document.getElementById( '{{ $uuid }}-list-tab' ).focus() )"
			x-on:keydown.end.prevent="activeView = 'outline'; $nextTick( () => $el.focus() )"
			role="tab"
			:aria-selected="'outline' === activeView"
			:tabindex="'outline' === activeView ? 0 : -1"
			aria-controls="{{ $uuid }}-outline-panel"
		>
			{{ __( 'visual-editor::ve.outline' ) }}
		</button>
	</div>

	{{-- List View --}}
	<div
		id="{{ $uuid }}-list-panel"
		x-show="'list' === activeView"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		tabindex="0"
		aria-labelledby="{{ $uuid }}-list-tab"
	>
		<div class="py-1">
			<template x-for="( block, index ) in blocks" :key="block.id">
				<div>
					<div
						draggable="true"
						style="-webkit-user-drag: element; user-select: none;"
						class="w-full text-left px-3 py-1.5 flex items-center gap-2 text-sm hover:bg-base-200 transition-colors cursor-grab active:cursor-grabbing"
						:class="{
							'bg-primary/10 text-primary': Alpine.store( 'selection' )?.focused === block.id,
							'text-base-content/70': Alpine.store( 'selection' )?.focused !== block.id,
							'border-t-2 border-primary': layerDragOverId === block.id && layerDraggingId !== block.id,
						}"
						x-on:click="selectBlock( block.id )"
						x-on:dragstart="handleLayerDragStart( $event, block, null )"
						x-on:dragend="handleLayerDragEnd( $event )"
						x-on:dragover="handleLayerDragOver( $event, block )"
						x-on:dragleave="layerDragOverId = null"
						x-on:drop="handleLayerDrop( $event, block, null )"
						role="button"
						tabindex="0"
					>
						<span class="w-5 text-center text-xs opacity-50 shrink-0 pointer-events-none" x-text="getBlockIcon( block )"></span>
						<span class="truncate pointer-events-none" x-text="getBlockLabel( block )"></span>
					</div>

					{{-- Nested inner blocks --}}
					<template x-if="block.innerBlocks && block.innerBlocks.length > 0">
						<div>
							<template x-for="innerBlock in block.innerBlocks" :key="innerBlock.id">
								<div
									draggable="true"
									style="-webkit-user-drag: element; user-select: none;"
									class="w-full text-left pl-8 pr-3 py-1.5 flex items-center gap-2 text-sm hover:bg-base-200 transition-colors cursor-grab active:cursor-grabbing"
									:class="{
										'bg-primary/10 text-primary': Alpine.store( 'selection' )?.focused === innerBlock.id,
										'text-base-content/70': Alpine.store( 'selection' )?.focused !== innerBlock.id,
										'border-t-2 border-primary': layerDragOverId === innerBlock.id && layerDraggingId !== innerBlock.id,
									}"
									x-on:click="selectBlock( innerBlock.id )"
									x-on:dragstart.stop="handleLayerDragStart( $event, innerBlock, block.id )"
									x-on:dragend="handleLayerDragEnd( $event )"
									x-on:dragover="handleLayerDragOver( $event, innerBlock )"
									x-on:dragleave="layerDragOverId = null"
									x-on:drop="handleLayerDrop( $event, innerBlock, block.id )"
									role="button"
									tabindex="0"
								>
									<span class="w-5 text-center text-xs opacity-50 shrink-0 pointer-events-none" x-text="getBlockIcon( innerBlock )"></span>
									<span class="truncate pointer-events-none" x-text="getBlockLabel( innerBlock )"></span>
								</div>
							</template>
						</div>
					</template>
				</div>
			</template>
		</div>
	</div>

	{{-- Outline View --}}
	<div
		id="{{ $uuid }}-outline-panel"
		x-show="'outline' === activeView"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		tabindex="0"
		aria-labelledby="{{ $uuid }}-outline-tab"
	>
		{{-- Heading list --}}
		<div class="py-1">
			<template x-if="headings.length > 0">
				<div>
					<template x-for="heading in headings" :key="heading.id">
						<button
							type="button"
							class="w-full text-left px-3 py-1.5 flex items-center gap-2 text-sm hover:bg-base-200 transition-colors"
							:class="Alpine.store( 'selection' )?.focused === heading.id ? 'bg-primary/10 text-primary' : 'text-base-content/70'"
							x-on:click="selectBlock( heading.id )"
							:style="'padding-left: ' + ( ( ( heading.attributes?.level || 1 ) - 1 ) * 12 + 12 ) + 'px'"
						>
							<span class="badge badge-xs badge-outline shrink-0" x-text="'H' + ( heading.attributes?.level || 1 )"></span>
							<span class="truncate" x-text="( heading.attributes?.content || '' ).replace( /<[^>]*>/g, '' ) || fallbackLabels.heading"></span>
						</button>
					</template>
				</div>
			</template>
			<template x-if="headings.length === 0">
				<p class="text-sm text-base-content/40 italic text-center py-4 px-3">
					{{ __( 'visual-editor::ve.no_headings_found' ) }}
				</p>
			</template>
		</div>

		{{-- Document stats --}}
		<div class="border-t border-base-300 p-3 space-y-1 text-xs text-base-content/60">
			<p x-text="wordCount + ' ' + ( 1 === wordCount ? {{ Js::from( trans_choice( 'visual-editor::ve.words_count_label', 1 ) ) }} : {{ Js::from( trans_choice( 'visual-editor::ve.words_count_label', 2 ) ) }} )"></p>
			<p x-text="charCount + ' ' + ( 1 === charCount ? {{ Js::from( trans_choice( 'visual-editor::ve.characters_count_label', 1 ) ) }} : {{ Js::from( trans_choice( 'visual-editor::ve.characters_count_label', 2 ) ) }} )"></p>
			<p x-text="readingTime + ' ' + ( 1 === readingTime ? {{ Js::from( trans_choice( 'visual-editor::ve.reading_time_label', 1 ) ) }} : {{ Js::from( trans_choice( 'visual-editor::ve.reading_time_label', 2 ) ) }} )"></p>
		</div>
	</div>
</div>
