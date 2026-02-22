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

	$blockIcons = [
		'paragraph' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />',
		'heading'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.243 4.493v7.5m0 0v7.514m0-7.514h10.5m0-7.5v7.5m0 0v7.514m4.014-1.5 2.25-2.25m0 0 2.25-2.25m-2.25 2.25-2.25-2.25m2.25 2.25 2.25 2.25" />',
		'image'     => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />',
		'list'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />',
		'quote'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />',
		'code'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />',
		'separator' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />',
		'table'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-7.5c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125" />',
		'_default'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Z" />',
	];
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
			const groups = Object.create( null );
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
				Alpine.store( 'editor' ).addBlock( { type: blockType }, position );
				this._addToRecent( blockType );

				if ( Alpine.store( 'announcer' ) ) {
					Alpine.store( 'announcer' ).announce(
						{{ Js::from( __( 'visual-editor::ve.block_inserted', [ 'block' => '__BLOCK__' ] ) ) }}.replaceAll( '__BLOCK__', blockLabel )
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
				: {{ Js::from( trans_choice( 'visual-editor::ve.search_results', 2, [ 'count' => '__COUNT__' ] ) ) }}.replaceAll( '__COUNT__', count );
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
			<div class="mb-1" :aria-label="categoryName">
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
											innerBlocks: [],
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
