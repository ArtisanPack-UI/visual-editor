{{--
 * Block Inserter Component
 *
 * A searchable, categorized block library for adding new blocks
 * to the editor. Supports panel (sidebar) and inline (popover) modes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$blocks = function_exists( 'applyFilters' )
		? applyFilters( 'ap.visualEditor.blocks.registered', $blocks )
		: $blocks;

	$categories = function_exists( 'applyFilters' )
		? applyFilters( 'ap.visualEditor.blocks.categories', $categories )
		: $categories;

	$blockIcons = $resolveBlockIcons();
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		search: '',
		recentlyUsed: (() => { try { return JSON.parse( localStorage.getItem( 'veRecentBlocks' ) || '[]' ); } catch { return []; } })(),
		collapsedCategories: {},
		blocks: {{ Js::from( $blocks ) }},
		categories: {{ Js::from( $categories ) }},
		recentlyUsedMax: {{ Js::from( $recentlyUsedMax ) }},
		blockIcons: {{ Js::from( $blockIcons ) }},

		get filteredBlocks() {
			let result = this.blocks;

			if ( this.search.trim().length > 0 ) {
				const query = this.search.toLowerCase();
				result = result.filter( ( block ) => {
					return ( block.label || block.name || '' ).toLowerCase().includes( query )
						|| ( block.description || '' ).toLowerCase().includes( query )
						|| ( block.keywords || [] ).some( ( k ) => k.toLowerCase().includes( query ) );
				} );
			}

			return result;
		},

		get resultCount() {
			return this.filteredBlocks.length;
		},

		get groupedBlocks() {
			const groups = {};
			this.filteredBlocks.forEach( ( block ) => {
				const cat = block.category || 'text';
				if ( ! groups[ cat ] ) {
					groups[ cat ] = [];
				}
				groups[ cat ].push( block );
			} );
			return groups;
		},

		toggleCategory( category ) {
			this.collapsedCategories[ category ] = ! this.collapsedCategories[ category ];
		},

		isCategoryCollapsed( category ) {
			return !! this.collapsedCategories[ category ];
		},

		getBlockIcon( blockType ) {
			return this.blockIcons[ blockType ] || this.blockIcons['_default'];
		},

		insertAt: {{ Js::from( $insertAt ) }},

		insertBlock( blockType, blockLabel ) {
			if ( Alpine.store( 'editor' ) ) {
				const position = this.insertAt;
				const blockDef = this.blocks.find( ( b ) => b.name === blockType );
				const defaultInner = ( blockDef && blockDef.defaultInnerBlocks ) ? blockDef.defaultInnerBlocks : [];
				const newBlock = Alpine.store( 'editor' ).addBlock( { type: blockType, innerBlocks: defaultInner }, position );
				this._addToRecent( blockType );

				if ( newBlock ) {
					this.$nextTick( () => {
						const el = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
						if ( el ) { el.focus(); }
						if ( Alpine.store( 'selection' ) ) {
							Alpine.store( 'selection' ).select( newBlock.id, false );
						}
					} );
				}

				if ( Alpine.store( 'announcer' ) ) {
					Alpine.store( 'announcer' ).announce(
						{{ Js::from( __( 'visual-editor::ve.block_inserted', [ 'block' => '__BLOCK__' ] ) ) }}.replaceAll( '__BLOCK__', () => blockLabel )
					);
				}
			}
		},

		_addToRecent( blockType ) {
			this.recentlyUsed = this.recentlyUsed.filter( ( t ) => t !== blockType );
			this.recentlyUsed.unshift( blockType );
			if ( this.recentlyUsed.length > this.recentlyUsedMax ) {
				this.recentlyUsed = this.recentlyUsed.slice( 0, this.recentlyUsedMax );
			}
			try { localStorage.setItem( 'veRecentBlocks', JSON.stringify( this.recentlyUsed ) ); } catch {}
		},

		_announceResults() {
			if ( ! Alpine.store( 'announcer' ) ) return;
			const count = this.resultCount;
			const msg = 1 === count
				? {{ Js::from( trans_choice( 'visual-editor::ve.search_results', 1 ) ) }}
				: {{ Js::from( trans_choice( 'visual-editor::ve.search_results', 2, [ 'count' => '__COUNT__' ] ) ) }}.replaceAll( '__COUNT__', () => count );
			Alpine.store( 'announcer' ).announce( msg );
		},
	}"
	x-effect="if ( search.trim().length > 0 ) { _announceResults(); }"
	x-on:ve-block-inserter-select.window="if ( $event?.detail?.type ) { insertBlock( $event.detail.type, $event.detail.label || $event.detail.type ); }"
	{{ $attributes->merge( [ 'class' => 'flex flex-col' ] ) }}
	role="region"
	aria-label="{{ __( 'visual-editor::ve.block_inserter' ) }}"
>
	{{-- Search --}}
	@if ( $showSearch )
		<div class="px-3 py-2 border-b border-base-300">
			<div class="relative">
				<svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
				</svg>
				<input
					type="search"
					x-model.debounce.300ms="search"
					placeholder="{{ __( 'visual-editor::ve.search_blocks' ) }}"
					class="input input-sm input-bordered w-full pl-8"
					aria-controls="{{ $uuid }}-results"
					aria-label="{{ __( 'visual-editor::ve.search_blocks' ) }}"
				/>
			</div>
		</div>
	@endif

	{{-- Block list --}}
	<div
		id="{{ $uuid }}-results"
		class="flex-1 overflow-y-auto px-3 py-2"
		role="list"
	>
		{{-- Recently used --}}
		@if ( $showRecentlyUsed )
			<template x-if="recentlyUsed.length > 0 && '' === search.trim()">
				<div class="mb-1">
					<button
						type="button"
						class="flex items-center gap-1.5 w-full px-2 py-1.5 text-xs font-semibold text-base-content/60 uppercase tracking-wide hover:text-base-content transition-colors"
						x-on:click="toggleCategory( '_recent' )"
						:aria-expanded="! isCategoryCollapsed( '_recent' )"
					>
						<svg
							class="w-3.5 h-3.5 transition-transform"
							:class="isCategoryCollapsed( '_recent' ) ? '-rotate-90' : ''"
							fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"
						>
							<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
						</svg>
						{{ __( 'visual-editor::ve.recently_used' ) }}
					</button>
					<div x-show="! isCategoryCollapsed( '_recent' )" x-collapse>
						<div class="grid grid-cols-3 gap-1">
							<template x-for="blockType in recentlyUsed" :key="blockType">
								<template x-if="blocks.find( b => b.name === blockType )">
									<div
										class="flex flex-col items-center gap-1 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors text-center"
										role="listitem"
										tabindex="0"
										x-on:click="insertBlock( blockType, blocks.find( b => b.name === blockType )?.label || blockType )"
										x-on:keydown.enter="insertBlock( blockType, blocks.find( b => b.name === blockType )?.label || blockType )"
									>
										<div class="w-10 h-10 flex items-center justify-center rounded bg-base-200 text-base-content/60">
											<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false" x-html="getBlockIcon( blockType )"></svg>
										</div>
										<span class="text-xs font-medium text-base-content leading-tight" x-text="blocks.find( b => b.name === blockType )?.label || blockType"></span>
									</div>
								</template>
							</template>
						</div>
					</div>
				</div>
			</template>
		@endif

		{{-- Categorized blocks --}}
		<template x-for="( categoryBlocks, categoryName ) in groupedBlocks" :key="categoryName">
			<div class="mb-1" role="group" :aria-label="categoryName">
				<button
					type="button"
					class="flex items-center gap-1.5 w-full px-2 py-1.5 text-xs font-semibold text-base-content/60 uppercase tracking-wide hover:text-base-content transition-colors"
					x-on:click="toggleCategory( categoryName )"
					:aria-expanded="! isCategoryCollapsed( categoryName )"
				>
					<svg
						class="w-3.5 h-3.5 transition-transform"
						:class="isCategoryCollapsed( categoryName ) ? '-rotate-90' : ''"
						fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"
					>
						<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
					</svg>
					<span x-text="categoryName"></span>
				</button>
				<div x-show="! isCategoryCollapsed( categoryName )" x-collapse>
					<div class="grid grid-cols-3 gap-1">
						<template x-for="block in categoryBlocks" :key="block.name">
							<div
								class="flex flex-col items-center gap-1 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors text-center"
								role="listitem"
								tabindex="0"
								@if ( $enableDragToInsert )
									draggable="true"
									x-on:dragstart="
										$event.dataTransfer.setData( 'application/ve-block', JSON.stringify( {
											type: block.name,
											attributes: {},
											innerBlocks: block.defaultInnerBlocks || [],
										} ) );
										$event.dataTransfer.effectAllowed = 'copy';
									"
								@endif
								x-on:click="insertBlock( block.name, block.label || block.name )"
								x-on:keydown.enter="insertBlock( block.name, block.label || block.name )"
							>
								<div class="w-10 h-10 flex items-center justify-center rounded bg-base-200 text-base-content/60">
									<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false" x-html="getBlockIcon( block.name )"></svg>
								</div>
								<span class="text-xs font-medium text-base-content leading-tight" x-text="block.label || block.name"></span>
							</div>
						</template>
					</div>
				</div>
			</div>
		</template>

		{{-- No results --}}
		<template x-if="0 === resultCount">
			<p class="text-sm text-base-content/50 text-center py-4">
				{{ __( 'visual-editor::ve.no_blocks_found' ) }}
			</p>
		</template>
	</div>
</div>
