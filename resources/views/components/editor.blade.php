{{-- Editor Component View --}}
@once
@push( 'styles' )
<style>
	/* List block canvas styles */
	.ve-block-list ul,
	.ve-block-list.ve-list-unordered { list-style-type: disc; padding-left: 1.5rem; }
	.ve-block-list ol,
	.ve-block-list.ve-list-ordered { list-style-type: decimal; padding-left: 1.5rem; }
	.ve-block-list li { margin-bottom: 0.25rem; }
	.ve-block-list li ul { list-style-type: circle; padding-left: 1.5rem; margin-top: 0.25rem; }
	.ve-block-list li ol { list-style-type: lower-alpha; padding-left: 1.5rem; margin-top: 0.25rem; }
	.ve-block-list li li ul { list-style-type: square; }
	.ve-block-list li li ol { list-style-type: lower-roman; }

	/* Quote block canvas styles */
	.ve-block-quote {
		border-left: 4px solid oklch(var(--p));
		padding: 1rem 1.5rem;
		margin: 0;
		font-style: italic;
	}
	.ve-block-quote .ve-inner-blocks-placeholder:empty::before {
		content: attr(data-placeholder);
		color: oklch(var(--bc) / 0.4);
		pointer-events: none;
	}
	.ve-block-quote .ve-quote-citation {
		display: block;
		margin-top: 0.5rem;
		font-size: 0.875rem;
		font-style: normal;
		opacity: 0.7;
	}
	.ve-block-quote .ve-quote-citation:empty::before {
		content: attr(data-placeholder);
		color: oklch(var(--bc) / 0.4);
		pointer-events: none;
	}

	/* Block-level alignment */
	.ve-canvas-block.alignwide {
		margin-left: -60px;
		margin-right: -60px;
		max-width: calc(100% + 120px);
		width: calc(100% + 120px);
	}
	.ve-canvas-block.alignfull {
		margin-left: calc(-1 * var(--ve-canvas-padding, 1rem));
		margin-right: calc(-1 * var(--ve-canvas-padding, 1rem));
		max-width: calc(100% + 2 * var(--ve-canvas-padding, 1rem));
		width: calc(100% + 2 * var(--ve-canvas-padding, 1rem));
	}
	.ve-canvas-block.alignleft {
		float: left;
		margin-right: 1rem;
		margin-bottom: 0.5rem;
		max-width: 50%;
	}
	.ve-canvas-block.alignright {
		float: right;
		margin-left: 1rem;
		margin-bottom: 0.5rem;
		max-width: 50%;
	}
	.ve-canvas-block.aligncenter {
		margin-left: auto;
		margin-right: auto;
	}
	.ve-canvas-block.alignleft + .ve-canvas-block:not(.alignleft):not(.alignright),
	.ve-canvas-block.alignright + .ve-canvas-block:not(.alignleft):not(.alignright) {
		clear: both;
	}

	/* Column drag-and-drop drop indicators (inset box-shadow avoids overflow clipping) */
	.ve-block-column.ve-col-drop-before {
		box-shadow: inset 3px 0 0 0 oklch(var(--p));
	}
	.ve-block-column.ve-col-drop-after {
		box-shadow: inset -3px 0 0 0 oklch(var(--p));
	}
	/* Grid item drag-and-drop drop indicators */
	.ve-block-grid-item.ve-grid-item-drop-before {
		box-shadow: inset 3px 0 0 0 oklch(var(--p));
	}
	.ve-block-grid-item.ve-grid-item-drop-after {
		box-shadow: inset -3px 0 0 0 oklch(var(--p));
	}
</style>
@endpush
@endonce

{{-- State stores --}}
<x-ve-editor-state
	:initial-blocks="$initialBlocks"
	:patterns="$patterns"
	:block-transforms="$blockTransforms"
	:block-variations="$blockVariations"
	:autosave="$autosave"
	:autosave-interval="$autosaveInterval"
	:document-status="$documentStatus"
	:show-sidebar="$showSidebar"
	:mode="$mode"
/>
<x-ve-selection-manager />
<x-ve-aria-live-region />

{{-- Keyboard shortcuts --}}
<x-ve-keyboard-shortcuts :shortcuts="$editorShortcuts" :show-help-modal="false" />

{{-- Pattern Modal (full-screen) --}}
<x-ve-pattern-modal :patterns="$patternsWithPreviews" />

{{-- Full-page editor layout --}}
<div class="h-screen">
	<x-ve-editor-layout sidebar-width="280px" left-sidebar-width="280px">

		{{-- ============================================================ --}}
		{{-- TOP TOOLBAR --}}
		{{-- ============================================================ --}}
		<x-slot:toolbar>
			<x-ve-top-toolbar>
				<x-slot:center>
					{{ $toolbarCenter ?? '' }}
				</x-slot:center>
			</x-ve-top-toolbar>
		</x-slot:toolbar>

		{{-- ============================================================ --}}
		{{-- LEFT SIDEBAR --}}
		{{-- ============================================================ --}}
		<x-slot:leftSidebar>
			<x-ve-left-sidebar>
				<x-slot:blocksPanel>
					<x-ve-block-inserter :blocks="$inserterBlocks" :icon-renderer="$iconRenderer" />
				</x-slot:blocksPanel>

				<x-slot:patternsPanel>
					<x-ve-pattern-browser :patterns="$patterns" />
				</x-slot:patternsPanel>

				<x-slot:layersPanel>
					<x-ve-layer-panel />
				</x-slot:layersPanel>
			</x-ve-left-sidebar>
		</x-slot:leftSidebar>

		{{-- ============================================================ --}}
		{{-- CANVAS --}}
		{{-- ============================================================ --}}
		<x-slot:canvas>
			{{-- Block toolbar - floats above selected block --}}
			<x-ve-block-toolbar :show-move-controls="false">
				{{-- Drag handle (6-dot grip) --}}
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square cursor-grab active:cursor-grabbing"
					aria-label="{{ __( 'Drag to reorder' ) }}"
					draggable="true"
				>
					<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
						<circle cx="9" cy="5" r="1.5" />
						<circle cx="15" cy="5" r="1.5" />
						<circle cx="9" cy="12" r="1.5" />
						<circle cx="15" cy="12" r="1.5" />
						<circle cx="9" cy="19" r="1.5" />
						<circle cx="15" cy="19" r="1.5" />
					</svg>
				</button>

				{{-- Move up / down --}}
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square"
					x-on:click="if ( Alpine.store( 'editor' ) && focusedBlockId ) { Alpine.store( 'editor' ).moveBlockUp( focusedBlockId ); }"
					aria-label="{{ __( 'Move up' ) }}"
				>
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
					</svg>
				</button>
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square"
					x-on:click="if ( Alpine.store( 'editor' ) && focusedBlockId ) { Alpine.store( 'editor' ).moveBlockDown( focusedBlockId ); }"
					aria-label="{{ __( 'Move down' ) }}"
				>
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
					</svg>
				</button>

				<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

				{{-- Block type icon + transform dropdown --}}
				<div x-data="{
					transformOpen: false,
					blockIcons: {{ Js::from( $toolbarBlockIcons ) }},
					blockNames: {{ Js::from( $transformableBlocks ) }},

					get currentType() {
						if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return null;
						const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
						return block?.type ?? null;
					},
				}" class="relative">
					<button
						type="button"
						class="btn btn-ghost btn-xs gap-1 px-1.5"
						x-on:click="transformOpen = ! transformOpen"
						:aria-expanded="transformOpen"
						aria-label="{{ __( 'Change block type' ) }}"
					>
						<span class="w-4 h-4 flex items-center justify-center" x-html="blockIcons[ currentType ] || blockIcons[ Object.keys( blockIcons )[ 0 ] ]"></span>
						<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
						</svg>
					</button>

					<div
						x-show="transformOpen"
						x-on:click.outside="transformOpen = false"
						x-transition
						class="absolute left-0 top-full mt-1 w-48 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50 max-h-64 overflow-y-auto"
						role="menu"
					>
						<template x-for="( name, type ) in blockNames" :key="type">
							<button
								type="button"
								class="flex w-full items-center gap-2 px-3 py-1.5 text-sm hover:bg-base-200"
								:class="type === currentType ? 'bg-primary/10 text-primary' : ''"
								role="menuitem"
								x-on:click="transformOpen = false"
							>
								<span class="w-4 h-4 flex items-center justify-center shrink-0" x-html="blockIcons[ type ]"></span>
								<span x-text="name"></span>
							</button>
						</template>
					</div>
				</div>

				{{-- Heading level selector (visible only for heading blocks) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'heading';
				})()">
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
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<button
							type="button"
							class="btn btn-ghost btn-xs gap-0.5 px-1.5 font-bold"
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
				</template>

				{{-- List type toggle (visible only for list blocks) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'list';
				})()">
					<div
						x-data="{
							get currentListType() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'unordered';
								const block = Alpine.store( 'editor' ).getBlock( blockId );
								return block?.attributes?.type ?? 'unordered';
							},

							toggleListType() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
								const newType = 'ordered' === this.currentListType ? 'unordered' : 'ordered';
								Alpine.store( 'editor' ).updateBlock( blockId, { type: newType } );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<button
							type="button"
							class="btn btn-ghost btn-xs btn-square"
							:class="currentListType === 'unordered' ? 'bg-base-200 text-base-content' : ''"
							x-on:click="if ( 'unordered' !== currentListType ) toggleListType()"
							aria-label="{{ __( 'Unordered list' ) }}"
							title="{{ __( 'Unordered list' ) }}"
						>
							<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
							</svg>
						</button>
						<button
							type="button"
							class="btn btn-ghost btn-xs btn-square"
							:class="currentListType === 'ordered' ? 'bg-base-200 text-base-content' : ''"
							x-on:click="if ( 'ordered' !== currentListType ) toggleListType()"
							aria-label="{{ __( 'Ordered list' ) }}"
							title="{{ __( 'Ordered list' ) }}"
						>
							<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
								<text x="2" y="8" font-size="7" font-family="system-ui, sans-serif" font-weight="600">1.</text>
								<line x1="10" y1="6" x2="22" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
								<text x="2" y="15" font-size="7" font-family="system-ui, sans-serif" font-weight="600">2.</text>
								<line x1="10" y1="13" x2="22" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
								<text x="2" y="22" font-size="7" font-family="system-ui, sans-serif" font-weight="600">3.</text>
								<line x1="10" y1="20" x2="22" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
							</svg>
						</button>
					</div>
				</template>

				{{-- Quote citation toggle (visible only for quote blocks) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'quote';
				})()">
					<div
						x-data="{
							get showCitation() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return false;
								const block = Alpine.store( 'editor' ).getBlock( blockId );
								return block?.attributes?.showCitation ?? false;
							},

							toggleCitation() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
								const block = Alpine.store( 'editor' ).getBlock( blockId );
								const current = block?.attributes?.showCitation ?? false;
								Alpine.store( 'editor' ).updateBlock( blockId, { showCitation: ! current } );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<button
							type="button"
							class="btn btn-ghost btn-xs btn-square"
							:class="showCitation ? 'bg-base-200 text-base-content' : ''"
							x-on:click="toggleCitation()"
							:aria-label="showCitation ? '{{ __( 'visual-editor::ve.remove_citation' ) }}' : '{{ __( 'visual-editor::ve.add_citation' ) }}'"
							:title="showCitation ? '{{ __( 'visual-editor::ve.remove_citation' ) }}' : '{{ __( 'visual-editor::ve.add_citation' ) }}'"
						>
							<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
							</svg>
						</button>
					</div>
				</template>

				{{-- Gallery Add button (visible only for gallery blocks) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'gallery';
				})()">
					<div
						x-data="{
							addImages() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId ) return;
								Livewire.dispatch( 'open-ve-media-picker', { context: blockId + ':gallery-add' } );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<button
							type="button"
							class="btn btn-ghost btn-xs gap-1 px-1.5"
							x-on:click="addImages()"
							aria-label="{{ __( 'visual-editor::ve.gallery_add_images' ) }}"
							title="{{ __( 'visual-editor::ve.gallery_add_images' ) }}"
						>
							<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
							</svg>
							<span class="text-xs">{{ __( 'visual-editor::ve.add_images' ) }}</span>
						</button>
					</div>
				</template>

				{{-- Group block toolbar controls (justification + orientation) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'group';
				})()">
					<div
						x-data="{
							get block() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
								return Alpine.store( 'editor' ).getBlock( blockId );
							},
							get flexDirection() { return this.block?.attributes?.flexDirection || 'column'; },
							get justifyContent() { return this.block?.attributes?.justifyContent || 'flex-start'; },
							get isRowOrStack() {
								const v = this.block?.attributes?._groupVariation;
								if ( v ) return 'row' === v || 'stack' === v;
								return 'row' === this.flexDirection;
							},
							get isGroupVariation() {
								const v = this.block?.attributes?._groupVariation;
								if ( v ) return 'group' === v;
								return 'column' === this.flexDirection && 'nowrap' === ( this.block?.attributes?.flexWrap || 'nowrap' );
							},
							setJustify( value ) {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, { justifyContent: value } );
							},
							setDirection( value ) {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, { flexDirection: value } );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						{{-- Justification buttons --}}
						<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.justify_content' ) }}">
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-start' ? 'bg-base-200' : ''" x-on:click="setJustify( 'flex-start' )" :aria-pressed="( justifyContent === 'flex-start' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_start' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
									<line x1="4" y1="4" x2="4" y2="20" />
									<rect x="8" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" />
									<rect x="14" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
								</svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'center' ? 'bg-base-200' : ''" x-on:click="setJustify( 'center' )" :aria-pressed="( justifyContent === 'center' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_center' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
									<line x1="12" y1="4" x2="12" y2="20" stroke-dasharray="2 2" />
									<rect x="5" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" />
									<rect x="15" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
								</svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-end' ? 'bg-base-200' : ''" x-on:click="setJustify( 'flex-end' )" :aria-pressed="( justifyContent === 'flex-end' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_end' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
									<line x1="20" y1="4" x2="20" y2="20" />
									<rect x="6" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
									<rect x="12" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" />
								</svg>
							</button>
							{{-- Space-between: only for Row/Stack --}}
							<template x-if="'row' === flexDirection || ( 'column' === flexDirection && block?.attributes?.flexWrap === 'nowrap' && block?.innerBlocks?.length > 0 )">
								<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'space-between' ? 'bg-base-200' : ''" x-on:click="setJustify( 'space-between' )" :aria-pressed="( justifyContent === 'space-between' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_space_between' ) }}">
									<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<line x1="4" y1="4" x2="4" y2="20" />
										<line x1="20" y1="4" x2="20" y2="20" />
										<rect x="7" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
										<rect x="13" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" />
									</svg>
								</button>
							</template>
						</div>

						{{-- Orientation toggle: only for Row/Stack (not default Group) --}}
						<template x-if="block?.innerBlocks?.length > 0">
							<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.orientation' ) }}">
								<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>
								<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'row' === flexDirection ? 'bg-base-200' : ''" x-on:click="setDirection( 'row' )" :title="'{{ __( 'visual-editor::ve.orientation_horizontal' ) }}'">
									<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
									</svg>
								</button>
								<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'column' === flexDirection ? 'bg-base-200' : ''" x-on:click="setDirection( 'column' )" :title="'{{ __( 'visual-editor::ve.orientation_vertical' ) }}'">
									<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
									</svg>
								</button>
							</div>
						</template>
					</div>
				</template>

				{{-- Columns block toolbar controls (vertical alignment) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'columns';
				})()">
					<div
						x-data="{
							get block() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
								return Alpine.store( 'editor' ).getBlock( blockId );
							},
							get vAlign() { return this.block?.attributes?.verticalAlignment || 'top'; },
							setVAlign( value ) {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, { verticalAlignment: value } );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.vertical_alignment' ) }}">
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'top' ? 'bg-base-200' : ''" x-on:click="setVAlign( 'top' )" title="{{ __( 'visual-editor::ve.top' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><rect x="8" y="7" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'center' ? 'bg-base-200' : ''" x-on:click="setVAlign( 'center' )" title="{{ __( 'visual-editor::ve.center' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="12" x2="20" y2="12" stroke-dasharray="2 2" /><rect x="8" y="10" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'bottom' ? 'bg-base-200' : ''" x-on:click="setVAlign( 'bottom' )" title="{{ __( 'visual-editor::ve.bottom' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="13" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'stretch' ? 'bg-base-200' : ''" x-on:click="setVAlign( 'stretch' )" title="{{ __( 'visual-editor::ve.stretch' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="7" width="8" height="10" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
						</div>
					</div>
				</template>

				{{-- Column block toolbar controls (width input + vertical alignment) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'column';
				})()">
					<div
						x-data="{
							get block() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
								return Alpine.store( 'editor' ).getBlock( blockId );
							},
							get colWidth() { return this.block?.attributes?.width || ''; },
							get vAlign() { return this.block?.attributes?.verticalAlignment || 'top'; },
							updateAttr( attrs ) {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						{{-- Width input --}}
						<input
							type="text"
							class="input input-bordered input-xs w-16 text-center text-xs"
							:value="colWidth"
							x-on:change="updateAttr( { width: $event.target.value } )"
							placeholder="auto"
							title="{{ __( 'visual-editor::ve.column_width' ) }}"
						/>

						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.vertical_alignment' ) }}">
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'top' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'top' } )" title="{{ __( 'visual-editor::ve.top' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><rect x="8" y="7" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'center' } )" title="{{ __( 'visual-editor::ve.center' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="12" x2="20" y2="12" stroke-dasharray="2 2" /><rect x="8" y="10" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'bottom' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'bottom' } )" title="{{ __( 'visual-editor::ve.bottom' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="13" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'stretch' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'stretch' } )" title="{{ __( 'visual-editor::ve.stretch' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="7" width="8" height="10" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
						</div>
					</div>
				</template>

				{{-- Grid Item toolbar: Vertical alignment (identical to Column) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block?.type === 'grid-item';
				})()">
					<div
						x-data="{
							get block() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
								return Alpine.store( 'editor' ).getBlock( blockId );
							},
							get vAlign() { return this.block?.attributes?.verticalAlignment || 'stretch'; },
							updateAttr( attrs ) {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<div class="flex items-center" role="group" aria-label="{{ __( 'visual-editor::ve.vertical_alignment' ) }}">
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'start' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'start' } )" title="{{ __( 'visual-editor::ve.top' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><rect x="8" y="7" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'center' } )" title="{{ __( 'visual-editor::ve.center' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="12" x2="20" y2="12" stroke-dasharray="2 2" /><rect x="8" y="10" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'end' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'end' } )" title="{{ __( 'visual-editor::ve.bottom' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="13" width="8" height="4" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
							<button type="button" class="btn btn-ghost btn-xs btn-square" :class="vAlign === 'stretch' ? 'bg-base-200' : ''" x-on:click="updateAttr( { verticalAlignment: 'stretch' } )" title="{{ __( 'visual-editor::ve.stretch' ) }}">
								<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="20" y2="4" /><line x1="4" y1="20" x2="20" y2="20" /><rect x="8" y="7" width="8" height="10" rx="1" fill="currentColor" stroke="none" /></svg>
							</button>
						</div>
					</div>
				</template>

				{{-- Block alignment dropdown (visible only for blocks that support align) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					if ( ! block ) return false;
					const _bas = {{ Js::from( $blockAlignSupports ) }};
					const supports = _bas[ block.type ];
					return supports && supports.length > 0;
				})()">
					<div
						x-data="{
							alignOpen: false,
							blockAlignSupports: {{ Js::from( $blockAlignSupports ) }},
							alignIcons: {
								none: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><rect x=\'4\' y=\'4\' width=\'16\' height=\'16\' rx=\'1\' stroke=\'currentColor\' stroke-width=\'2\' /></svg>',
								left: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><rect x=\'1\' y=\'5\' width=\'12\' height=\'14\' rx=\'1\' fill=\'currentColor\' /><line x1=\'23\' y1=\'5\' x2=\'23\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /></svg>',
								center: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><line x1=\'1\' y1=\'5\' x2=\'1\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /><rect x=\'6\' y=\'5\' width=\'12\' height=\'14\' rx=\'1\' fill=\'currentColor\' /><line x1=\'23\' y1=\'5\' x2=\'23\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /></svg>',
								right: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><line x1=\'1\' y1=\'5\' x2=\'1\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /><rect x=\'11\' y=\'5\' width=\'12\' height=\'14\' rx=\'1\' fill=\'currentColor\' /></svg>',
								wide: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><line x1=\'1\' y1=\'5\' x2=\'1\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /><rect x=\'3\' y=\'8\' width=\'18\' height=\'8\' rx=\'1\' fill=\'currentColor\' /><line x1=\'23\' y1=\'5\' x2=\'23\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /></svg>',
								full: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><rect x=\'1\' y=\'5\' width=\'22\' height=\'14\' rx=\'1\' fill=\'currentColor\' /></svg>',
							},
							alignLabels: {
								none: '{{ __( 'None' ) }}',
								left: '{{ __( 'Align left' ) }}',
								center: '{{ __( 'Align center' ) }}',
								right: '{{ __( 'Align right' ) }}',
								wide: '{{ __( 'Wide width' ) }}',
								full: '{{ __( 'Full width' ) }}',
							},

							get supportedAligns() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return [ 'none' ];
								const block = Alpine.store( 'editor' ).getBlock( blockId );
								if ( ! block ) return [ 'none' ];
								let options = this.blockAlignSupports[ block.type ] || [];
								if ( options.indexOf( 'none' ) === -1 ) {
									options = [ 'none', ...options ];
								}
								return options;
							},

							get currentAlign() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'none';
								const block = Alpine.store( 'editor' ).getBlock( blockId );
								return block?.attributes?.align ?? 'none';
							},

							setAlign( value ) {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
								Alpine.store( 'editor' ).updateBlock( blockId, { align: value } );
								this.alignOpen = false;
								if ( Alpine.store( 'announcer' ) ) {
									Alpine.store( 'announcer' ).announce( '{{ __( 'Block alignment changed to' ) }} ' + ( this.alignLabels[ value ] || value ) );
								}
							},
						}"
						class="relative flex items-center"
					>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

						<button
							type="button"
							class="btn btn-ghost btn-xs gap-0.5 px-1.5"
							x-on:click="alignOpen = ! alignOpen"
							:aria-expanded="alignOpen"
							aria-haspopup="listbox"
							aria-label="{{ __( 'Block alignment' ) }}"
							title="{{ __( 'Block alignment' ) }}"
						>
							<span class="flex items-center justify-center" x-html="alignIcons[ currentAlign ] || alignIcons.none"></span>
							<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
							</svg>
						</button>

						<div
							x-show="alignOpen"
							x-on:click.outside="alignOpen = false"
							x-transition
							class="absolute left-0 top-full mt-1 w-44 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50"
							role="listbox"
							aria-label="{{ __( 'Block alignment' ) }}"
						>
							<template x-for="opt in supportedAligns" :key="opt">
								<button
									type="button"
									class="flex w-full items-center gap-2 px-3 py-1.5 text-sm hover:bg-base-200"
									:class="opt === currentAlign ? 'bg-primary/10 text-primary' : ''"
									role="option"
									:aria-selected="opt === currentAlign ? 'true' : 'false'"
									x-on:click="setAlign( opt )"
								>
									<span class="flex items-center justify-center shrink-0" x-html="alignIcons[ opt ] || alignIcons.none"></span>
									<span x-text="alignLabels[ opt ] || opt"></span>
								</button>
							</template>
						</div>
					</div>
				</template>

				{{-- Text alignment and formatting controls (hidden for container blocks) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					return block && ! [ 'columns', 'column', 'group', 'gallery', 'image', 'video', 'file', 'audio' ].includes( block.type );
				})()">
					<div class="contents">
						<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

						{{-- Text alignment controls --}}
						<div
							x-data="{
								get currentAlignment() {
									const blockId = Alpine.store( 'selection' )?.focused;
									if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'left';
									const block = Alpine.store( 'editor' ).getBlock( blockId );
									return block?.attributes?.alignment ?? 'left';
								},
								setAlignment( value ) {
									const blockId = Alpine.store( 'selection' )?.focused;
									if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
									Alpine.store( 'editor' ).updateBlock( blockId, { alignment: value } );
								},
							}"
							class="flex items-center"
						>
							<button
								type="button"
								class="btn btn-ghost btn-xs btn-square"
								:class="currentAlignment === 'left' ? 'bg-base-200 text-base-content' : ''"
								x-on:click="setAlignment( 'left' )"
								aria-label="{{ __( 'Align left' ) }}"
								title="{{ __( 'Align left' ) }}"
							>
								<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
									<path stroke-linecap="round" d="M3 6h18M3 12h12M3 18h16" />
								</svg>
							</button>
							<button
								type="button"
								class="btn btn-ghost btn-xs btn-square"
								:class="currentAlignment === 'center' ? 'bg-base-200 text-base-content' : ''"
								x-on:click="setAlignment( 'center' )"
								aria-label="{{ __( 'Align center' ) }}"
								title="{{ __( 'Align center' ) }}"
							>
								<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
									<path stroke-linecap="round" d="M3 6h18M6 12h12M4 18h16" />
								</svg>
							</button>
							<button
								type="button"
								class="btn btn-ghost btn-xs btn-square"
								:class="currentAlignment === 'right' ? 'bg-base-200 text-base-content' : ''"
								x-on:click="setAlignment( 'right' )"
								aria-label="{{ __( 'Align right' ) }}"
								title="{{ __( 'Align right' ) }}"
							>
								<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
									<path stroke-linecap="round" d="M3 6h18M9 12h12M5 18h16" />
								</svg>
							</button>
						</div>

						<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

						{{-- Text formatting controls --}}
						<x-ve-toolbar-button
							:label="__( 'Bold' )"
							icon="o-bold"
							:tooltip="__( 'Bold' )"
							shortcut="Ctrl+B"
							x-on:click="document.execCommand( 'bold', false, null )"
						/>
						<x-ve-toolbar-button
							:label="__( 'Italic' )"
							icon="o-italic"
							:tooltip="__( 'Italic' )"
							shortcut="Ctrl+I"
							x-on:click="document.execCommand( 'italic', false, null )"
						/>
						<x-ve-toolbar-button
							:label="__( 'Link' )"
							icon="o-link"
							:tooltip="__( 'Link' )"
							shortcut="Ctrl+K"
							x-on:click="
								const url = prompt( '{{ __( 'Enter URL:' ) }}' );
								if ( url ) { document.execCommand( 'createLink', false, url ); }
							"
						/>
					</div>
				</template>

				{{-- Custom toolbar HTML from blocks that declare hasCustomToolbar() --}}
				@foreach ( $customToolbarHtml as $toolbarType => $toolbarHtml )
					<template x-if="(() => {
						if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
						const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
						return block?.type === {{ Js::from( $toolbarType ) }};
					})()">
						<div class="relative flex items-center">
							<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>
							{!! $toolbarHtml !!}
						</div>
					</template>
				@endforeach
			</x-ve-block-toolbar>

			{{-- Slash command inserter: appears when user types "/" in a paragraph --}}
			<x-ve-slash-command-inserter :blocks="$inserterBlocks" />

			{{-- Block renderer registry Alpine store --}}
			<script>
				document.addEventListener( 'alpine:init', () => {
					Alpine.store( 'blockRenderers', {
						renderers: {},
						metadata: {{ Js::from( $blockMetadata ) }},

						register( type, config ) {
							this.renderers[ type ] = config;
						},

						hasRenderer( type ) {
							return !! this.renderers[ type ];
						},

						getHtml( block, context ) {
							const renderer = this.renderers[ block.type ];
							if ( renderer ) {
								return renderer.render( block, context );
							}
							return null;
						},

						getMeta( type ) {
							return this.metadata[ type ] || null;
						},

						isContainer( type ) {
							const meta = this.metadata[ type ];
							return meta && meta.supportsInnerBlocks;
						},

						getOrientation( type ) {
							const meta = this.metadata[ type ];
							return meta ? ( meta.innerBlocksOrientation || 'vertical' ) : 'vertical';
						},

						getAllowedChildren( type ) {
							const meta = this.metadata[ type ];
							return meta ? meta.allowedChildren : null;
						},

						renderInnerBlocks( block, options = {} ) {
							const orientation  = options.orientation || 'vertical';
							const containerCls = options.containerClass || '';
							const containerSty = options.containerStyle || '';
							const placeholder  = options.placeholder || '';
							const renderChild  = options.renderChild || null;
							const context      = options.context || {};

							const orientClass = 'horizontal' === orientation
								? 'flex flex-row flex-wrap gap-2'
								: 'flex flex-col';

							let html = '';

							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								html += '<div class=\'ve-inner-blocks ' + orientClass + ( containerCls ? ' ' + containerCls : '' ) + '\''
									+ ( containerSty ? ' style=\'' + containerSty + '\'' : '' )
									+ ' data-ve-inner-blocks data-parent-id=\'' + block.id + '\''
									+ ' data-orientation=\'' + orientation + '\'>';

								block.innerBlocks.forEach( ( inner, idx ) => {
									html += this._renderInsertionPoint( block.id, idx );

									if ( renderChild ) {
										html += renderChild( inner, idx );
									} else {
										html += this._renderInnerBlockWrapper( block.id, inner, context );
									}
								} );

								html += this._renderInsertionPoint( block.id, block.innerBlocks.length );
								html += '</div>';
							} else {
								html += '<div class=\'ve-inner-blocks ' + orientClass + ( containerCls ? ' ' + containerCls : '' ) + '\''
									+ ( containerSty ? ' style=\'' + containerSty + '\'' : '' )
									+ ' data-ve-inner-blocks data-parent-id=\'' + block.id + '\''
									+ ' data-orientation=\'' + orientation + '\'>'
									+ '<div class=\'ve-inner-blocks-placeholder\''
									+ ' contenteditable=\'true\''
									+ ( placeholder ? ' data-placeholder=\'' + placeholder + '\'' : '' )
									+ ' data-ve-enter-new-block=\'true\''
									+ '></div></div>';
							}

							return html;
						},

						_renderInsertionPoint( parentId, index ) {
							return '<div class=\'ve-inner-insertion-point relative group/inner-insert py-0.5\'>'
								+ '<div class=\'flex justify-center\'>'
								+ '<button type=\'button\''
								+ ' class=\'w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity text-xs\''
								+ ' data-ve-inner-insert'
								+ ' data-parent-id=\'' + parentId + '\''
								+ ' data-insert-index=\'' + index + '\''
								+ '>+</button>'
								+ '</div></div>';
						},

						_renderInnerBlockWrapper( parentId, inner, context ) {
							const existingWrapper = document.querySelector( '[data-inner-block-id=\'' + inner.id + '\']' );
							let innerText = inner.attributes?.text || '';
							if ( existingWrapper ) {
								const contentEl = existingWrapper.querySelector( '[contenteditable]' ) || existingWrapper;
								innerText = contentEl.innerHTML;
							}

							let html = '<div class=\'ve-inner-block-wrapper relative group/inner-block\''
								+ ' data-block-id=\'' + inner.id + '\''
								+ ' data-inner-block-id=\'' + inner.id + '\''
								+ ' data-parent-id=\'' + parentId + '\''
								+ ' tabindex=\'-1\''
								+ '>'
								+ '<div class=\'ve-inner-block-drag-handle absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing opacity-0 group-hover/inner-block:opacity-50 hover:!opacity-100 transition-opacity\''
								+ ' draggable=\'true\''
								+ ' data-ve-inner-drag-handle'
								+ ' data-inner-drag-id=\'' + inner.id + '\''
								+ ' data-parent-id=\'' + parentId + '\''
								+ '>'
								+ '<svg class=\'w-3 h-3\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
								+ '<circle cx=\'9\' cy=\'7\' r=\'1.5\'/><circle cx=\'15\' cy=\'7\' r=\'1.5\'/>'
								+ '<circle cx=\'9\' cy=\'12\' r=\'1.5\'/><circle cx=\'15\' cy=\'12\' r=\'1.5\'/>'
								+ '<circle cx=\'9\' cy=\'17\' r=\'1.5\'/><circle cx=\'15\' cy=\'17\' r=\'1.5\'/>'
								+ '</svg></div>';

							const innerRendererStore = Alpine.store( 'blockRenderers' );
							if ( innerRendererStore && innerRendererStore.hasRenderer( inner.type ) ) {
								html += innerRendererStore.getHtml( inner, context );
								html += '</div>';
							} else if ( 'heading' === inner.type ) {
								const innerLevel = inner.attributes?.level || 'h2';
								const innerTag   = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( innerLevel ) ? innerLevel : 'h2';
								const sizeMap    = { h1: 'text-4xl font-extrabold', h2: 'text-3xl font-bold', h3: 'text-2xl font-bold', h4: 'text-xl font-semibold', h5: 'text-lg font-semibold', h6: 'text-base font-semibold' };
								const innerSize  = sizeMap[ innerTag ] || sizeMap.h2;
								html += '<' + innerTag
									+ ' class=\'ve-inner-block-content ve-block ve-block-heading ve-block-editing ' + innerSize + '\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + ( context.headingPlaceholder || '' ) + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ ' data-ve-slash-command=\'true\''
									+ '>' + innerText + '</' + innerTag + '></div>';
							} else {
								html += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || 'paragraph' ) + ' ve-block-editing\''
									+ ' contenteditable=\'true\''
									+ ' data-placeholder=\'' + ( context.paragraphPlaceholder || '' ) + '\''
									+ ' data-ve-enter-new-block=\'true\''
									+ ' data-ve-slash-command=\'true\''
									+ '>' + innerText + '</div></div>';
							}

							return html;
						},
					} );

					const br = Alpine.store( 'blockRenderers' );

					br.register( 'group', {
						render( block, context ) {
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

							const hasExplicitVariation = bgColor || textColor || 'column' !== flexDirection;
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

							const orientation = 'row' === flexDirection ? 'horizontal' : 'vertical';
							const innerHtml   = br.renderInnerBlocks( block, {
								orientation: orientation,
								placeholder: {{ Js::from( __( 'visual-editor::ve.block_group_placeholder' ) ) }},
								context: context,
							} );

							return '<' + tag + ' class=\'ve-block ve-block-group ve-block-editing\' style=\'' + inlineStyle + '\'>'
								+ innerHtml
								+ '</' + tag + '>';
						},
					} );

					br.register( 'columns', {
						render( block, context ) {
							const gap          = block.attributes?.gap || 'medium';
							const vAlign       = block.attributes?.verticalAlignment || 'top';
							const isStacked    = block.attributes?.isStacked || false;
							const columnsCount = parseInt( block.attributes?.columns ) || 2;

							const gapMap   = { none: '0', small: '0.5rem', medium: '1rem', large: '2rem' };
							const alignMap = { top: 'flex-start', center: 'center', bottom: 'flex-end', stretch: 'stretch' };

							let inlineStyle = 'display:flex;gap:' + ( gapMap[ gap ] || '1rem' ) + ';align-items:' + ( alignMap[ vAlign ] || 'flex-start' ) + ';';
							if ( isStacked ) {
								inlineStyle += 'flex-direction:column;';
							} else {
								inlineStyle += 'flex-direction:row;flex-wrap:wrap;';
							}

							const hasInnerBlocks = block.innerBlocks && block.innerBlocks.length > 0;

							if ( ! hasInnerBlocks ) {
								return '<div class=\'ve-block ve-block-columns ve-block-editing\' style=\'' + inlineStyle + '\' data-columns=\'' + columnsCount + '\'>'
									+ '<div class=\'ve-columns-layout-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full\'>'
									+ '<p class=\'text-sm text-base-content/60\'>' + {{ Js::from( __( 'visual-editor::ve.columns_layout' ) ) }} + '</p>'
									+ '<div class=\'grid grid-cols-3 gap-2 w-full max-w-sm\'>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'100\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div class=\'flex-1 bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>100</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'50-50\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div class=\'flex-1 bg-base-content/20 rounded\'></div><div class=\'flex-1 bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>50 / 50</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'33-66\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:2\' class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>33 / 66</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'66-33\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div style=\'flex:2\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>66 / 33</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'33-33-33\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div class=\'flex-1 bg-base-content/20 rounded\'></div><div class=\'flex-1 bg-base-content/20 rounded\'></div><div class=\'flex-1 bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>33 / 33 / 33</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-columns-layout=\'25-50-25\'>'
									+ '<div class=\'flex gap-0.5 w-full h-6\'><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:2\' class=\'bg-base-content/20 rounded\'></div><div style=\'flex:1\' class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>25 / 50 / 25</span>'
									+ '</button>'
									+ '</div>'
									+ '<button type=\'button\' class=\'text-xs text-primary hover:underline\' data-ve-set-columns-layout=\'skip\'>'
									+ {{ Js::from( __( 'visual-editor::ve.columns_skip' ) ) }}
									+ '</button>'
									+ '</div>'
									+ '</div>';
							}

							let innerHtml = '<div class=\'ve-inner-blocks flex\' style=\'' + ( isStacked ? 'flex-direction:column;' : 'flex-direction:row;flex-wrap:nowrap;' ) + 'gap:' + ( gapMap[ gap ] || '1rem' ) + ';align-items:' + ( alignMap[ vAlign ] || 'flex-start' ) + ';\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'' + ( isStacked ? 'vertical' : 'horizontal' ) + '\'>';
							block.innerBlocks.forEach( ( col ) => {
								if ( br.hasRenderer( 'column' ) ) {
									innerHtml += br.getHtml( { ...col, attributes: { ...col.attributes, _parentId: block.id } }, context );
								}
							} );

							innerHtml += '<button type=\'button\''
								+ ' class=\'ve-add-column-btn flex items-center justify-center rounded-lg border-2 border-dashed border-base-300 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer\''
								+ ' style=\'min-width:40px;min-height:60px;flex:0 0 40px;align-self:stretch;\''
								+ ' data-ve-add-column'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' title=\'' + {{ Js::from( __( 'visual-editor::ve.add_column' ) ) }} + '\''
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
					} );

					br.register( 'column', {
						render( block, context ) {
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

							const pid       = block.attributes?._parentId || '';
							const innerHtml = br.renderInnerBlocks( block, {
								orientation: 'vertical',
								containerClass: 'gap-2',
								placeholder: {{ Js::from( __( 'visual-editor::ve.block_column_placeholder' ) ) }},
								context: context,
							} );

							return '<div class=\'ve-block ve-block-column ve-block-editing\''
								+ ' style=\'' + colStyle + '\''
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ innerHtml
								+ '</div>';
						},
					} );

					br.register( 'grid', {
						render( block, context ) {
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

							const gapMap    = { none: '0', small: '0.5rem', medium: '1rem', large: '2rem' };
							const gapValue    = gapMap[ gap ] || '1rem';
							const rowGapValue = rowGap ? ( gapMap[ rowGap ] || gapValue ) : gapValue;

							let inlineStyle = 'display:grid;grid-template-columns:repeat(' + columns + ',1fr);grid-template-rows:' + templateRows + ';';
							inlineStyle += 'column-gap:' + gapValue + ';row-gap:' + rowGapValue + ';';
							inlineStyle += 'align-items:' + alignItems + ';justify-items:' + justifyItems + ';';

							const hasInnerBlocks = block.innerBlocks && block.innerBlocks.length > 0;

							if ( ! hasInnerBlocks ) {
								return '<div class=\'ve-block ve-block-grid ve-block-editing\' style=\'' + inlineStyle + '\' data-columns=\'' + columns + '\'>'
									+ '<div class=\'ve-grid-layout-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full\' style=\'grid-column:1/-1;\'>'
									+ '<p class=\'text-sm text-base-content/60\'>' + {{ Js::from( __( 'visual-editor::ve.grid_layout' ) ) }} + '</p>'
									+ '<div class=\'grid grid-cols-3 gap-2 w-full max-w-sm\'>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'2\'>'
									+ '<div class=\'grid grid-cols-2 gap-0.5 w-full h-6\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>2 col</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'3\'>'
									+ '<div class=\'grid grid-cols-3 gap-0.5 w-full h-6\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>3 col</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'4\'>'
									+ '<div class=\'grid grid-cols-4 gap-0.5 w-full h-6\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>4 col</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'2x2\'>'
									+ '<div class=\'grid grid-cols-2 grid-rows-2 gap-0.5 w-full h-10\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>2 × 2</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'3x2\'>'
									+ '<div class=\'grid grid-cols-3 grid-rows-2 gap-0.5 w-full h-10\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>3 × 2</span>'
									+ '</button>'
									+ '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\' data-ve-set-grid-layout=\'3x3\'>'
									+ '<div class=\'grid grid-cols-3 grid-rows-3 gap-0.5 w-full h-12\'><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div><div class=\'bg-base-content/20 rounded\'></div></div>'
									+ '<span class=\'text-xs font-medium\'>3 × 3</span>'
									+ '</button>'
									+ '</div>'
									+ '<button type=\'button\' class=\'text-xs text-primary hover:underline\' data-ve-set-grid-layout=\'custom\'>'
									+ {{ Js::from( __( 'visual-editor::ve.grid_custom' ) ) }}
									+ '</button>'
									+ '</div>'
									+ '</div>';
							}

							let innerHtml = '<div class=\'ve-inner-blocks\' style=\'display:contents;\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'horizontal\'>';
							block.innerBlocks.forEach( ( item ) => {
								if ( br.hasRenderer( 'grid-item' ) ) {
									innerHtml += br.getHtml( { ...item, attributes: { ...item.attributes, _parentId: block.id } }, context );
								}
							} );
							innerHtml += '</div>';

							innerHtml += '<button type=\'button\''
								+ ' class=\'ve-add-grid-item-btn flex items-center justify-center rounded-lg border-2 border-dashed border-base-300 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer\''
								+ ' style=\'min-height:60px;\''
								+ ' data-ve-add-grid-item'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' title=\'' + {{ Js::from( __( 'visual-editor::ve.add_grid_item' ) ) }} + '\''
								+ '>'
								+ '<svg class=\'w-5 h-5 text-base-content/40\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4.5v15m7.5-7.5h-15\' />'
								+ '</svg>'
								+ '</button>';

							return '<div class=\'ve-block ve-block-grid ve-block-editing\' style=\'' + inlineStyle + '\' data-columns=\'' + columns + '\'>'
								+ innerHtml
								+ '</div>';
						},
					} );

					br.register( 'grid-item', {
						render( block, context ) {
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
							const pid = block.attributes?._parentId || '';

							let itemStyle = '';
							if ( columnSpan > 1 ) { itemStyle += 'grid-column:span ' + columnSpan + ';'; }
							if ( rowSpan > 1 ) { itemStyle += 'grid-row:span ' + rowSpan + ';'; }
							if ( 'stretch' !== verticalAlignment ) { itemStyle += 'align-self:' + verticalAlignment + ';'; }

							const innerHtml = br.renderInnerBlocks( block, {
								orientation: 'vertical',
								containerClass: 'gap-2',
								placeholder: {{ Js::from( __( 'visual-editor::ve.grid_item_placeholder' ) ) }},
								context: context,
							} );

							return '<div class=\'ve-block ve-block-grid-item ve-block-editing\''
								+ ( itemStyle ? ' style=\'' + itemStyle + '\'' : '' )
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ innerHtml
								+ '</div>';
						},
					} );
				} );
			</script>

			{{-- Dynamic canvas: renders blocks from the Alpine store reactively --}}
			@include( 'visual-editor::components._editor-canvas-content' )
		</x-slot:canvas>

		{{-- ============================================================ --}}
		{{-- RIGHT SIDEBAR (Inspector) --}}
		{{-- ============================================================ --}}
		<x-slot:sidebar>
			<x-ve-editor-sidebar>
				<x-slot:settingsPanel>
					@include( 'visual-editor::components._editor-inspector-settings' )
				</x-slot:settingsPanel>

				<x-slot:stylesPanel>
					@include( 'visual-editor::components._editor-inspector-styles' )
				</x-slot:stylesPanel>

				<x-slot:documentPanel>
					{{ $documentPanel ?? '' }}
				</x-slot:documentPanel>
			</x-ve-editor-sidebar>
		</x-slot:sidebar>

		<x-slot:statusbar>
			<x-ve-status-bar />
		</x-slot:statusbar>
	</x-ve-editor-layout>
</div>
