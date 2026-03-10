{{--
 * Slash Command Inserter Component
 *
 * An inline dropdown that appears when the user types "/" in an
 * empty paragraph block, filtering available blocks by name/keywords.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.1.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		open: false,
		query: '',
		blockId: null,
		activeIndex: 0,
		positionStyle: { position: 'absolute', top: '0', left: '0', display: 'none' },
		blocks: {{ Js::from( $blocks ) }},

		get filteredBlocks() {
			if ( '' === this.query.trim() ) {
				return this.blocks;
			}

			const q = this.query.toLowerCase();
			return this.blocks.filter( ( block ) => {
				return ( block.label || block.name || '' ).toLowerCase().includes( q )
					|| ( block.description || '' ).toLowerCase().includes( q )
					|| ( block.keywords || [] ).some( ( k ) => k.toLowerCase().includes( q ) );
			} );
		},

		show( detail ) {
			this.blockId     = detail.blockId || null;
			this.query       = detail.query || '';
			this.activeIndex = 0;

			const rect = detail.rect;
			if ( rect ) {
				const parentRect = this.$el.offsetParent
					? this.$el.offsetParent.getBoundingClientRect()
					: { top: 0, left: 0 };
				this.positionStyle = {
					position: 'absolute',
					top: ( rect.bottom - parentRect.top + 4 ) + 'px',
					left: ( rect.left - parentRect.left ) + 'px',
					display: 'block',
					zIndex: 50,
				};
			}

			this.open = true;
		},

		hide() {
			this.open    = false;
			this.blockId = null;
			this.query   = '';
		},

		selectBlock( blockType ) {
			document.dispatchEvent( new CustomEvent( 've-slash-command-select', {
				bubbles: true,
				detail: { blockType: blockType, blockId: this.blockId },
			} ) );
			this.hide();
		},

		handleKeydown( event ) {
			if ( ! this.open ) return;

			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				this.activeIndex = Math.min( this.activeIndex + 1, this.filteredBlocks.length - 1 );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				this.activeIndex = Math.max( this.activeIndex - 1, 0 );
			} else if ( 'Enter' === event.key ) {
				event.preventDefault();
				if ( this.filteredBlocks.length > 0 ) {
					this.selectBlock( this.filteredBlocks[ this.activeIndex ].name );
				}
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				this.hide();
			}
		},
	}"
	x-on:ve-slash-command-open.window="show( $event.detail )"
	x-on:ve-slash-command-update.window="query = $event.detail.query || ''; activeIndex = 0;"
	x-on:ve-slash-command-close.window="hide()"
	x-on:keydown.window="handleKeydown( $event )"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<div
		x-show="open"
		x-transition:enter="transition ease-out duration-100"
		x-transition:enter-start="opacity-0 scale-95"
		x-transition:enter-end="opacity-100 scale-100"
		x-transition:leave="transition ease-in duration-75"
		x-transition:leave-start="opacity-100 scale-100"
		x-transition:leave-end="opacity-0 scale-95"
		:style="positionStyle"
		class="w-64 max-h-64 overflow-y-auto rounded-lg border border-base-300 bg-base-100 shadow-lg py-1"
		role="listbox"
		aria-label="{{ __( 'visual-editor::ve.slash_command_insert' ) }}"
	>
		<template x-if="0 === filteredBlocks.length">
			<p class="px-3 py-2 text-sm text-base-content/50">
				{{ __( 'visual-editor::ve.slash_command_no_match' ) }}
			</p>
		</template>

		<template x-for="( block, index ) in filteredBlocks" :key="block.name">
			<button
				type="button"
				class="w-full text-left px-3 py-1.5 text-sm flex items-center gap-2 transition-colors"
				:class="index === activeIndex ? 'bg-primary/10 text-primary' : 'hover:bg-base-200'"
				x-on:click="selectBlock( block.name )"
				x-on:mouseenter="activeIndex = index"
				role="option"
				:aria-selected="index === activeIndex"
			>
				<span class="font-medium" x-text="block.label || block.name"></span>
			</button>
		</template>
	</div>
</div>
