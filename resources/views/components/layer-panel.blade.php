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
			if ( 'heading' === block.type ) {
				const content = ( block.attributes?.content || '' ).replace( /<[^>]*>/g, '' );
				return content || this.fallbackLabels.heading;
			}
			if ( 'paragraph' === block.type ) {
				const content = ( block.attributes?.content || '' ).replace( /<[^>]*>/g, '' );
				return content.length > 40 ? content.substring( 0, 40 ) + '…' : content || this.fallbackLabels.paragraph;
			}
			if ( 'image' === block.type ) {
				return block.attributes?.alt || this.fallbackLabels.image;
			}
			return this.fallbackLabels[ block.type ] || block.type.charAt( 0 ).toUpperCase() + block.type.slice( 1 );
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
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col h-full' ] ) }}
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
				<button
					type="button"
					class="w-full text-left px-3 py-1.5 flex items-center gap-2 text-sm hover:bg-base-200 transition-colors"
					:class="Alpine.store( 'selection' )?.focused === block.id ? 'bg-primary/10 text-primary' : 'text-base-content/70'"
					x-on:click="selectBlock( block.id )"
				>
					<span class="w-5 text-center text-xs opacity-50 shrink-0" x-text="getBlockIcon( block )"></span>
					<span class="truncate" x-text="getBlockLabel( block )"></span>
				</button>
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
