{{--
 * Template Structure Panel Component
 *
 * Displays a hierarchical outline of the block tree in the
 * left sidebar, reading blocks from the Alpine editor store.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		blockNames: {{ Js::from( $blockNames ) }},

		get flatBlocks() {
			const store = Alpine.store( 'editor' );
			if ( ! store || ! store.blocks ) return [];
			return this.flatten( store.blocks, 0 );
		},

		flatten( blocks, depth ) {
			let result = [];
			for ( const block of blocks ) {
				const text = block.attributes?.text || block.attributes?.content || '';
				const truncated = text.replace( /<[^>]*>/g, '' ).substring( 0, 40 );
				result.push( {
					id: block.id,
					type: block.type,
					depth: depth,
					label: this.blockNames[ block.type ] || block.type,
					preview: truncated,
					hasChildren: block.innerBlocks && block.innerBlocks.length > 0,
				} );
				if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
					result = result.concat( this.flatten( block.innerBlocks, depth + 1 ) );
				}
			}
			return result;
		},

		selectBlock( blockId ) {
			if ( Alpine.store( 'selection' ) ) {
				Alpine.store( 'selection' ).select( blockId );
			}
			const el = document.querySelector( '[data-block-id=\'' + CSS.escape( blockId ) + '\']' );
			if ( el ) {
				el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		},

		isSelected( blockId ) {
			return Alpine.store( 'selection' )?.focused === blockId;
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col h-full' ] ) }}
	role="navigation"
	aria-label="{{ $label ?? __( 'visual-editor::ve.template_structure' ) }}"
>
	{{-- Panel Header --}}
	<div class="px-3 py-2 border-b border-base-300">
		<h3 class="text-sm font-semibold text-base-content">
			{{ __( 'visual-editor::ve.template_structure' ) }}
		</h3>
	</div>

	{{-- Block Tree --}}
	<div class="flex-1 overflow-y-auto py-1" role="tree" aria-label="{{ __( 'visual-editor::ve.template_structure' ) }}">
		<template x-if="flatBlocks.length > 0">
			<div>
				<template x-for="item in flatBlocks" :key="item.id">
					<button
						type="button"
						class="w-full flex items-center gap-2 px-3 py-1.5 text-sm hover:bg-base-200 transition-colors text-left"
						:class="isSelected( item.id ) ? 'bg-primary/10 text-primary font-medium' : ''"
						:style="{ paddingLeft: ( 12 + item.depth * 16 ) + 'px' }"
						x-on:click="selectBlock( item.id )"
						role="treeitem"
						:aria-label="item.label"
					>
						<svg class="w-4 h-4 text-base-content/50 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
						</svg>
						<span class="flex-1 min-w-0">
							<span class="block truncate" x-text="item.label"></span>
							<span
								x-show="item.preview"
								class="block text-xs text-base-content/40 truncate"
								x-text="item.preview"
							></span>
						</span>
					</button>
				</template>
			</div>
		</template>

		<template x-if="flatBlocks.length === 0">
			<div class="px-3 py-8 text-center text-sm text-base-content/40">
				{{ __( 'visual-editor::ve.empty_canvas_description' ) }}
			</div>
		</template>
	</div>

	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.templateStructurePanel.rendered')
	@endif
</div>
