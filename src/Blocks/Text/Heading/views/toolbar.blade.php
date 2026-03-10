{{--
 * Heading Block Toolbar Controls
 *
 * Provides a heading level dropdown (H1–H6) for the heading block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Heading\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		levelOpen: false,
		levels: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		levelLabels: { h1: 'H1', h2: 'H2', h3: 'H3', h4: 'H4', h5: 'H5', h6: 'H6' },

		get currentLevel() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'h2';
			const block = Alpine.store( 'editor' ).getBlock( blockId );
			return block?.attributes?.level ?? 'h2';
		},

		setLevel( level ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
			Alpine.store( 'editor' ).updateBlock( blockId, { level: level } );
			this.levelOpen = false;
		},
	}"
	class="relative flex items-center"
>
	<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>

	<button
		type="button"
		class="flex items-center justify-center rounded px-2.5 py-1.5 text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors gap-0.5 font-bold text-sm"
		x-on:click="levelOpen = ! levelOpen"
		:aria-expanded="levelOpen"
		aria-label="{{ __( 'Change heading level' ) }}"
	>
		<span x-text="levelLabels[ currentLevel ]"></span>
		<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
		</svg>
	</button>

	<div
		x-show="levelOpen"
		x-on:click.outside="levelOpen = false"
		x-transition
		class="absolute left-0 top-full mt-1 w-24 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50"
		role="menu"
	>
		<template x-for="level in levels" :key="level">
			<button
				type="button"
				class="flex w-full items-center px-3 py-1.5 text-sm font-semibold hover:bg-base-200"
				:class="level === currentLevel ? 'bg-primary/10 text-primary' : ''"
				role="menuitem"
				x-on:click="setLevel( level )"
				x-text="levelLabels[ level ]"
			></button>
		</template>
	</div>
</div>
