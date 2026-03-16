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

	/* Table block canvas styles */
	.ve-block-table table {
		width: 100%;
		border-collapse: collapse;
	}
	.ve-block-table th,
	.ve-block-table td {
		padding: 0.5rem 0.75rem;
		min-width: 3rem;
		min-height: 1.5rem;
		vertical-align: top;
		outline: none;
	}
	.ve-block-table.ve-table-bordered th,
	.ve-block-table.ve-table-bordered td {
		border: 1px solid var(--ve-table-border-color, oklch(var(--bc) / 0.2));
	}
	.ve-block-table.ve-table-striped tbody tr:nth-child(even) {
		background-color: var(--ve-table-stripe-color, oklch(var(--bc) / 0.04));
	}
	.ve-block-table th {
		font-weight: 600;
	}
	.ve-block-table thead:not([style*="background-color"]) th {
		background-color: oklch(var(--bc) / 0.06);
	}
	.ve-block-table caption {
		caption-side: bottom;
		padding: 0.5rem;
		font-size: 0.875rem;
		color: oklch(var(--bc) / 0.6);
	}
	.ve-block-table caption:empty::before {
		content: attr(data-placeholder);
		color: oklch(var(--bc) / 0.4);
		pointer-events: none;
	}
	.ve-block-table [contenteditable]:empty::before {
		content: attr(data-placeholder);
		color: oklch(var(--bc) / 0.3);
		pointer-events: none;
	}
	.ve-table-toolbar {
		display: flex;
		gap: 0.5rem;
		padding: 0.5rem 0;
		justify-content: center;
	}
	.ve-table-toolbar button {
		display: inline-flex;
		align-items: center;
		gap: 0.25rem;
		font-size: 0.75rem;
		padding: 0.25rem 0.75rem;
		border-radius: 0.25rem;
		border: 1px solid oklch(var(--bc) / 0.2);
		background: oklch(var(--b1));
		cursor: pointer;
		transition: background-color 0.15s;
	}
	.ve-table-toolbar button:hover {
		background: oklch(var(--bc) / 0.05);
	}

	/* Table gutter (row/column action buttons) */
	.ve-table-gutter {
		padding: 0 !important;
		border: none !important;
		background: transparent !important;
		width: 0;
		overflow: visible;
		position: relative;
	}
	.ve-table-col-actions-row {
		display: flex;
		height: 0;
		padding: 0;
		line-height: 0;
	}
	.ve-table-col-actions {
		display: flex;
		justify-content: center;
		gap: 2px;
		padding-bottom: 4px;
		opacity: 0;
		transition: opacity 0.15s;
	}
	.ve-block-table:hover .ve-table-col-actions,
	.ve-block-table:focus-within .ve-table-col-actions {
		opacity: 1;
	}
	.ve-table-row-actions {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 2px;
		padding-left: 4px;
		opacity: 0;
		transition: opacity 0.15s;
	}
	tr:hover > .ve-table-gutter .ve-table-row-actions,
	tr:focus-within > .ve-table-gutter .ve-table-row-actions,
	.ve-block-table:hover .ve-table-row-actions,
	.ve-block-table:focus-within .ve-table-row-actions {
		opacity: 1;
	}
	.ve-table-action-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 18px;
		height: 18px;
		border-radius: 3px;
		border: 1px solid oklch(var(--bc) / 0.15);
		background: oklch(var(--b1));
		color: oklch(var(--bc) / 0.5);
		cursor: pointer;
		transition: all 0.15s;
		padding: 0;
	}
	.ve-table-action-btn:hover {
		background: oklch(var(--p) / 0.1);
		border-color: oklch(var(--p) / 0.3);
		color: oklch(var(--p));
	}
	.ve-table-action-btn-danger:hover {
		background: oklch(var(--er) / 0.1);
		border-color: oklch(var(--er) / 0.3);
		color: oklch(var(--er));
	}

	/* Table layout picker */
	.ve-table-layout-picker {
		min-height: 200px;
	}

	/* Details block canvas styles */
	.ve-block-details {
		border: 1px solid oklch(var(--bc) / 0.2);
		border-radius: 0.25rem;
	}
	.ve-block-details.ve-details-borderless {
		border: none;
	}
	.ve-block-details.ve-details-minimal {
		border: none;
		border-bottom: 1px solid oklch(var(--bc) / 0.2);
		border-radius: 0;
	}
	.ve-block-details .ve-details-summary-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.75rem 1rem;
		cursor: pointer;
		font-weight: 600;
		list-style: none;
	}
	.ve-block-details .ve-details-summary-row::-webkit-details-marker {
		display: none;
	}
	.ve-block-details .ve-details-summary {
		outline: none;
		user-select: text;
	}
	.ve-block-details .ve-details-summary:empty::before {
		content: attr(data-placeholder);
		color: oklch(var(--bc) / 0.4);
		pointer-events: none;
		font-weight: normal;
	}
	.ve-block-details .ve-details-icon {
		flex-shrink: 0;
		width: 1rem;
		height: 1rem;
		color: oklch(var(--bc) / 0.5);
		transition: transform 0.2s;
	}
	.ve-block-details[open] .ve-details-icon-chevron {
		transform: rotate(90deg);
	}
	.ve-block-details .ve-plus-vertical {
		transition: opacity 0.2s;
	}
	.ve-block-details[open] .ve-plus-vertical {
		opacity: 0;
	}
	.ve-block-details .ve-details-content {
		padding: 0 1rem 0.75rem;
	}
	.ve-block-details .ve-details-content .ve-inner-blocks-placeholder:empty::before {
		content: attr(data-placeholder);
		color: oklch(var(--bc) / 0.4);
		pointer-events: none;
	}

	/* Contenteditable placeholder — show when block is empty and not focused */
	[data-placeholder].ve-is-empty:not(:focus)::before {
		content: attr(data-placeholder);
		opacity: 0.4;
		font-style: italic;
		pointer-events: none;
	}

	/* Contenteditable placeholder for nested elements (e.g. li inside contenteditable ul/ol) */
	[contenteditable] > [data-placeholder].ve-is-empty::before {
		content: attr(data-placeholder);
		opacity: 0.4;
		font-style: italic;
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

	/* Button block canvas styles */
	.ve-block-button.ve-block-editing {
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		border-radius: 0.375rem;
		font-weight: 500;
		line-height: 1.25;
		cursor: text;
		text-decoration: none;
		transition: box-shadow 0.15s ease;
		min-height: 2.25rem;
	}

	/* Button size variants */
	.ve-block-editing.ve-button-sm { padding: 0.375rem 0.75rem; font-size: 0.875rem; }
	.ve-block-editing.ve-button-md { padding: 0.5rem 1rem; font-size: 1rem; }
	.ve-block-editing.ve-button-lg { padding: 0.625rem 1.25rem; font-size: 1.125rem; }
	.ve-block-editing.ve-button-xl { padding: 0.75rem 1.5rem; font-size: 1.25rem; }

	/* Button variant styles */
	.ve-block-editing.ve-button-filled {
		background-color: oklch(var(--p));
		color: oklch(var(--pc));
	}
	.ve-block-editing.ve-button-outline {
		background-color: transparent;
		border: 2px solid oklch(var(--p));
		color: oklch(var(--p));
	}
	.ve-block-editing.ve-button-ghost {
		background-color: transparent;
		color: oklch(var(--bc));
		border: 1px dashed oklch(var(--bc) / 0.3);
	}

	/* Button text contenteditable — always show a visible area */
	.ve-block-button.ve-block-editing .ve-button-text {
		outline: none;
		min-width: 4rem;
		display: inline-block;
	}

	/* Focus ring on the button text span */
	.ve-block-button.ve-block-editing .ve-button-text:focus {
		outline: 2px solid oklch(var(--pc) / 0.6);
		outline-offset: 2px;
		border-radius: 2px;
	}
	.ve-block-editing.ve-button-outline .ve-button-text:focus,
	.ve-block-editing.ve-button-ghost .ve-button-text:focus {
		outline-color: oklch(var(--p) / 0.6);
	}

	/* Button text placeholder — show when empty */
	.ve-block-button.ve-block-editing .ve-button-text:empty::before {
		content: attr(data-placeholder);
		opacity: 0.6;
		font-style: italic;
		pointer-events: none;
	}

	/* Button icon styling */
	.ve-button-icon {
		display: inline-flex;
		align-items: center;
		flex-shrink: 0;
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
	:default-inner-blocks-map="$defaultInnerBlocksMap"
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
				{{-- Parent block navigation (visible only for child blocks) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					return !! Alpine.store( 'editor' ).getParentBlock( Alpine.store( 'selection' ).focused );
				})()">
					<div
						x-data="{
							parentBlockIcons: {{ Js::from( $toolbarBlockIcons ) }},
							parentBlockNames: {{ Js::from( $blockNames ) }},

							get parentBlock() {
								const blockId = Alpine.store( 'selection' )?.focused;
								if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
								return Alpine.store( 'editor' ).getParentBlock( blockId );
							},

							selectParent() {
								if ( this.parentBlock ) {
									Alpine.store( 'selection' ).select( this.parentBlock.id );
								}
							},
						}"
						class="flex items-center"
					>
						<button
							type="button"
							class="btn btn-ghost btn-xs btn-square"
							x-on:click="selectParent()"
							:aria-label="'{{ __( 'visual-editor::ve.select_parent_block', [ 'name' => ':name' ] ) }}'.replace( ':name', parentBlockNames[ parentBlock?.type ] || parentBlock?.type || '' )"
							:title="parentBlockNames[ parentBlock?.type ] || parentBlock?.type || ''"
						>
							<span class="w-4 h-4 flex items-center justify-center" x-html="parentBlockIcons[ parentBlock?.type ] || ''"></span>
						</button>
						<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>
					</div>
				</template>

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

					get availableTransforms() {
						if ( ! this.currentType || ! Alpine.store( 'editor' ) ) return [];
						return Alpine.store( 'editor' ).getTransformsForBlock( this.currentType );
					},
				}" class="relative">
					<button
						type="button"
						class="btn btn-ghost btn-xs gap-1 px-1.5"
						x-on:click="if ( availableTransforms.length > 0 ) { transformOpen = ! transformOpen; }"
						:aria-expanded="transformOpen"
						:disabled="availableTransforms.length === 0"
						aria-label="{{ __( 'Change block type' ) }}"
					>
						<span class="w-4 h-4 flex items-center justify-center" x-html="blockIcons[ currentType ] || blockIcons[ Object.keys( blockIcons )[ 0 ] ]"></span>
						<svg x-show="availableTransforms.length > 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
						</svg>
					</button>

					<div
						x-show="transformOpen && availableTransforms.length > 0"
						x-on:click.outside="transformOpen = false"
						x-transition
						class="absolute left-0 top-full mt-1 w-48 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50 max-h-64 overflow-y-auto"
						role="menu"
					>
						<template x-for="targetType in availableTransforms" :key="targetType">
							<button
								type="button"
								class="flex w-full items-center gap-2 px-3 py-1.5 text-sm hover:bg-base-200"
								:class="targetType === currentType ? 'bg-primary/10 text-primary' : ''"
								role="menuitem"
								x-on:click="
									Alpine.store( 'editor' ).transformBlock( Alpine.store( 'selection' ).focused, targetType );
									transformOpen = false;
								"
							>
								<span class="w-4 h-4 flex items-center justify-center shrink-0" x-html="blockIcons[ targetType ]"></span>
								<span x-text="blockNames[ targetType ] || targetType"></span>
							</button>
						</template>
					</div>
				</div>

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

				{{-- Text alignment controls (shown for blocks that declare textAlignment support) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					if ( ! block ) return false;
					const meta = Alpine.store( 'blockRenderers' ).getMeta( block.type );
					return meta?.textAlignment === true;
				})()">
					<div class="contents">
						<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

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
					</div>
				</template>

				{{-- Text formatting controls (shown for blocks that declare textFormatting support) --}}
				<template x-if="(() => {
					if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
					const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
					if ( ! block ) return false;
					const meta = Alpine.store( 'blockRenderers' ).getMeta( block.type );
					return meta?.textFormatting === true;
				})()">
					<div
						class="contents"
						x-data="veInlineLinkControl"
						x-on:ve-inline-link-apply.stop="applyInlineLink( $event.detail )"
					>
						<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

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

						<div class="relative">
							<x-ve-toolbar-button
								:label="__( 'Link' )"
								icon="o-link"
								:tooltip="__( 'Link' )"
								shortcut="Ctrl+K"
								x-on:click="openLinkPopover()"
							/>
							<x-ve-link-popover event-name="ve-inline-link-apply" />
						</div>
					</div>
				</template>

				{{-- Custom toolbar HTML from blocks that declare hasCustomToolbar() --}}
				@foreach ( $customToolbarHtml as $toolbarType => $toolbarHtml )
					<template x-if="(() => {
						if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
						const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
						return block?.type === {{ Js::from( $toolbarType ) }};
					})()">
						{!! $toolbarHtml !!}
					</template>
				@endforeach
			</x-ve-block-toolbar>

			{{-- Slash command inserter: appears when user types "/" in a paragraph --}}
			<x-ve-slash-command-inserter :blocks="$inserterBlocks" />

			{{-- Block renderer registry Alpine store --}}
			<script>
				document.addEventListener( 'alpine:init', () => {

					/**
					 * Inline link control for the text-formatting toolbar.
					 *
					 * Registered as Alpine.data so the complex logic (regex,
					 * HTML string building) lives in a script tag instead of
					 * an x-data attribute where it breaks the HTML parser.
					 */
					Alpine.data( 'veInlineLinkControl', () => ( {
						linkPopoverOpen: false,
						linkPopoverUrl: '',
						linkPopoverNewTab: false,
						linkPopoverNofollow: false,
						linkPopoverSponsored: false,
						_savedSelection: null,

						_saveSelection() {
							const sel = window.getSelection();
							if ( sel && sel.rangeCount > 0 ) {
								this._savedSelection = sel.getRangeAt( 0 ).cloneRange();
							}
						},

						_restoreSelection() {
							if ( ! this._savedSelection ) return;
							const sel = window.getSelection();
							sel.removeAllRanges();
							sel.addRange( this._savedSelection );
						},

						_getActiveLink() {
							const sel = window.getSelection();
							if ( ! sel || ! sel.rangeCount ) return null;
							let node = sel.anchorNode;
							while ( node && node.nodeName !== 'A' ) {
								node = node.parentElement;
							}
							return node;
						},

						openLinkPopover() {
							this._saveSelection();
							const existingLink = this._getActiveLink();
							if ( existingLink ) {
								this.linkPopoverUrl       = existingLink.href || '';
								this.linkPopoverNewTab    = existingLink.target === '_blank';
								const rel                 = ( existingLink.rel || '' ).toLowerCase();
								this.linkPopoverNofollow  = rel.includes( 'nofollow' );
								this.linkPopoverSponsored = rel.includes( 'sponsored' );
							} else {
								this.linkPopoverUrl       = '';
								this.linkPopoverNewTab    = false;
								this.linkPopoverNofollow  = false;
								this.linkPopoverSponsored = false;
							}
							this.linkPopoverOpen = true;
						},

						_buildRelString( detail ) {
							const parts = [];
							if ( detail.newTab ) parts.push( 'noopener' );
							if ( detail.nofollow ) parts.push( 'nofollow' );
							if ( detail.sponsored ) parts.push( 'sponsored' );
							return parts.join( ' ' );
						},

						_syncContentToStore( el ) {
							const contentEl = el.closest( '[contenteditable]' );
							const blockEl   = el.closest( '[data-block-id]' );
							if ( contentEl && blockEl ) {
								Alpine.store( 'editor' ).updateBlock(
									blockEl.getAttribute( 'data-block-id' ),
									{ text: contentEl.innerHTML },
								);
							}
						},

						applyInlineLink( detail ) {
							this._restoreSelection();

							if ( ! detail.url ) {
								document.execCommand( 'unlink', false, null );
								return;
							}

							const rel          = this._buildRelString( detail );
							const existingLink = this._getActiveLink();

							if ( existingLink ) {
								existingLink.href = detail.url;
								if ( detail.newTab ) {
									existingLink.target = '_blank';
								} else {
									existingLink.removeAttribute( 'target' );
								}
								if ( rel ) {
									existingLink.rel = rel;
								} else {
									existingLink.removeAttribute( 'rel' );
								}
								this._syncContentToStore( existingLink );
							} else {
								const sel  = window.getSelection();
								const text = sel.toString() || detail.url;
								const safe = detail.url.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' );

								const a       = document.createElement( 'a' );
								a.href        = detail.url;
								a.textContent = text;
								if ( detail.newTab ) a.target = '_blank';
								if ( rel ) a.rel = rel;

								document.execCommand(
									'insertHTML', false,
									a.outerHTML,
								);
							}
						},
					} ) );

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
								html += '<div class=\'ve-inner-block-content ve-block ve-block-' + ( inner.type || Alpine.store( 'editor' ).defaultBlockType ) + ' ve-block-editing\''
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

					// JS-side CSS sanitizers matching the PHP-side veSanitizeCssColor/veSanitizeCssDimension.
					const veSanitizeCssColor = ( value ) => {
						if ( ! value ) { return ''; }
						return /^(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|(?:rgb|rgba|hsl|hsla)\([\d\s,./%]+\)|[a-zA-Z-]+)$/.test( value.trim() ) ? value.trim() : '';
					};
					const veSanitizeCssDimension = ( value ) => {
						if ( ! value ) { return ''; }
						return /^(-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc)?|auto|inherit|initial|unset)$/.test( value.trim() ) ? value.trim() : '';
					};
					const veSanitizeCssValue = ( value ) => {
						if ( ! value ) { return ''; }
						return /^[a-zA-Z0-9\s.\-%()/]+$/.test( value.trim() ) ? value.trim() : '';
					};
					// Escape a string for safe insertion into HTML attribute or text contexts.
					const veEscapeHtml = ( value ) => {
						if ( ! value ) { return ''; }
						return String( value )
							.replace( /&/g, '&amp;' )
							.replace( /"/g, '&quot;' )
							.replace( /'/g, '&#x27;' )
							.replace( /</g, '&lt;' )
							.replace( />/g, '&gt;' );
					};

					br.register( 'group', {
						render( block, context ) {
							const flexDirection  = block.attributes?.flexDirection || 'column';
							const flexWrap       = block.attributes?.flexWrap || 'nowrap';
							const justifyContent = block.attributes?.justifyContent || 'flex-start';
							const textColor      = veSanitizeCssColor( block.attributes?.textColor || '' );
							const bgColor        = veSanitizeCssColor( block.attributes?.backgroundColor || '' );
							const gap            = veSanitizeCssDimension( block.attributes?.gap || '' );
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

					br.register( 'buttons', {
						render( block, context ) {
							const justification = block.attributes?.justification || 'left';
							const orientation   = block.attributes?.orientation || 'horizontal';
							const flexWrap      = block.attributes?.flexWrap !== false;

							const justifyMap = { left: 'flex-start', center: 'center', right: 'flex-end', 'space-between': 'space-between' };
							const direction  = 'vertical' === orientation ? 'column' : 'row';

							let inlineStyle = 'display:flex;flex-direction:' + direction + ';justify-content:' + ( justifyMap[ justification ] || 'flex-start' ) + ';gap:0.5rem;';
							if ( flexWrap && 'horizontal' === orientation ) {
								inlineStyle += 'flex-wrap:wrap;';
							}

							let innerHtml = '<div class=\'ve-inner-blocks flex\' style=\'' + inlineStyle + '\' data-ve-inner-blocks data-parent-id=\'' + block.id + '\' data-orientation=\'' + ( 'vertical' === orientation ? 'vertical' : 'horizontal' ) + '\'>';

							if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
								block.innerBlocks.forEach( ( inner ) => {
									if ( br.hasRenderer( inner.type ) ) {
										innerHtml += br.getHtml( { ...inner, attributes: { ...inner.attributes, _parentId: block.id } }, context );
									}
								} );
							}

							innerHtml += '<button type=\'button\''
								+ ' class=\'ve-add-button-btn flex items-center justify-center rounded-lg border-2 border-dashed border-base-300 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer\''
								+ ' style=\'min-width:40px;min-height:36px;padding:0 12px;align-self:stretch;\''
								+ ' data-ve-add-inner-block'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' data-block-type=\'button\''
								+ ' title=\'' + {{ Js::from( __( 'visual-editor::ve.add_block' ) ) }} + '\''
								+ '>'
								+ '<svg class=\'w-4 h-4 text-base-content/40\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4.5v15m7.5-7.5h-15\' />'
								+ '</svg>'
								+ '</button>';

							innerHtml += '</div>';

							return '<div class=\'ve-block ve-block-buttons ve-block-editing\' data-justification=\'' + justification + '\' data-orientation=\'' + orientation + '\'>'
								+ innerHtml
								+ '</div>';
						},
					} );

					br.register( 'button', {
						render( block, context ) {
							// Read text from existing DOM element if present (preserves
							// user's typed content without creating a reactive dependency).
							let text = block.attributes?.text || '';
							const existingEl = document.querySelector( '[data-block-id=\'' + block.id + '\'] .ve-button-text' );
							if ( existingEl ) {
								text = existingEl.innerHTML;
							}
							const icon         = block.attributes?.icon || '';
							const iconPosition = block.attributes?.iconPosition || 'left';
							const color        = veSanitizeCssColor( block.attributes?.color || '' );
							const bgColor      = veSanitizeCssColor( block.attributes?.backgroundColor || '' );
							const size         = block.attributes?.size || 'md';
							const variant      = block.attributes?.variant || 'filled';
							const borderRadius = veSanitizeCssDimension( block.attributes?.borderRadius || '' );
							const width        = block.attributes?.width || 'auto';
							const pid          = block.attributes?._parentId || '';

							let inlineStyle = '';
							if ( color ) { inlineStyle += 'color:' + color + ';'; }
							if ( bgColor ) { inlineStyle += 'background-color:' + bgColor + ';'; }
							if ( borderRadius ) { inlineStyle += 'border-radius:' + borderRadius + ';'; }

							const widthMap = { '25': '25%', '50': '50%', '75': '75%', '100': '100%' };
							if ( widthMap[ width ] ) { inlineStyle += 'width:' + widthMap[ width ] + ';'; }

							let classes = 've-block ve-block-button ve-block-editing ve-button-' + size + ' ve-button-' + variant;

							let iconHtml = '';
							if ( icon ) {
								iconHtml = '<span class=\'ve-button-icon ve-button-icon-' + iconPosition + '\'>' + icon + '</span>';
							}

							return '<div class=\'' + classes + '\''
								+ ( inlineStyle ? ' style=\'' + inlineStyle + '\'' : '' )
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ ( icon && 'left' === iconPosition ? iconHtml : '' )
								+ '<span class=\'ve-button-text\''
								+ ' contenteditable=\'true\''
								+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.button_text' ) ) }} + '\''
								+ '>' + text + '</span>'
								+ ( icon && 'right' === iconPosition ? iconHtml : '' )
								+ '</div>';
						},
					} );

					br.register( 'tabs', {
						render( block, context ) {
							let tabs           = block.attributes?.tabs || [];
							const tabPosition  = block.attributes?.tabPosition || 'top';
							const tabStyle     = block.attributes?.tabStyle || 'default';
							const tabSize      = block.attributes?.tabSize || 'md';
							const fullWidth    = block.attributes?.fullWidth || false;
							const tabTextColor = veSanitizeCssColor( block.attributes?.tabTextColor || '' );
							const activeColor  = veSanitizeCssColor( block.attributes?.activeTabColor || '' );
							const contentBg    = veSanitizeCssColor( block.attributes?.contentBackground || '' );
							const innerBlocks  = block.innerBlocks || [];

							// Auto-populate tabs metadata from inner blocks if empty or mismatched.
							if ( tabs.length < innerBlocks.length ) {
								const store = Alpine.store( 'editor' );
								const newTabs = [ ...tabs ];
								for ( let i = tabs.length; i < innerBlocks.length; i++ ) {
									newTabs.push( { id: i, label: {{ Js::from( __( 'visual-editor::ve.tabs_tab_label_placeholder' ) ) }} + ' ' + ( i + 1 ), icon: '' } );
								}
								tabs = newTabs;
								if ( store ) { store.updateBlock( block.id, { tabs: newTabs } ); }
							} else if ( tabs.length > innerBlocks.length ) {
								const store = Alpine.store( 'editor' );
								tabs = tabs.slice( 0, innerBlocks.length );
								if ( store ) { store.updateBlock( block.id, { tabs } ); }
							}

							const isVertical = [ 'left', 'right' ].includes( tabPosition );

							let wrapperClasses = 've-block ve-block-tabs ve-block-editing';
							if ( isVertical ) {
								wrapperClasses += ' ve-tabs-vertical ve-tabs-' + tabPosition;
							}

							let wrapperStyle = '';
							if ( isVertical ) {
								wrapperStyle += 'display:flex;';
								if ( 'right' === tabPosition ) { wrapperStyle += 'flex-direction:row-reverse;'; }
							}

							let tabStyleClass = 'default' !== tabStyle ? 'tabs-' + tabStyle : '';
							let tabSizeClass  = 'md' !== tabSize ? 'tabs-' + tabSize : '';
							let tabsClasses   = ( 'tabs ' + tabStyleClass + ' ' + tabSizeClass ).trim();
							if ( fullWidth ) { tabsClasses += ' w-full'; }
							if ( isVertical ) { tabsClasses += ' flex-col'; }

							let tabListStyle = '';
							if ( tabTextColor ) { tabListStyle += 'color:' + tabTextColor + ';'; }
							if ( activeColor ) { tabListStyle += '--ve-tabs-active-color:' + activeColor + ';'; }

							let contentStyle = 'flex:1;';
							if ( contentBg ) { contentStyle += 'background-color:' + contentBg + ';'; }

							// Tab buttons — use generic data-ve-show-panel for switching
							let tabButtons = '';
							tabs.forEach( ( tab, idx ) => {
								const label = tab.label || ( {{ Js::from( __( 'visual-editor::ve.tabs_tab_label_placeholder' ) ) }} + ' ' + ( idx + 1 ) );
								const icon  = tab.icon || '';
								const isActive = 0 === idx;

								// Preserve label from DOM.
								const existingLabel = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] .ve-tab-label-editable[data-attr-index=\'' + idx + '\']' );
								const labelText = existingLabel ? existingLabel.innerHTML : label;

								tabButtons += '<button type=\'button\' class=\'tab' + ( isActive ? ' tab-active' : '' ) + '\''
									+ ' data-ve-show-panel=\'' + idx + '\''
									+ ' role=\'tab\''
									+ '>'
									+ ( icon ? '<span class=\'ve-tab-icon\'>' + icon + '</span>' : '' )
									+ '<span class=\'ve-tab-label-editable\' contenteditable=\'true\''
									+ ' data-ve-sync-parent-array'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-attr-name=\'tabs\''
									+ ' data-attr-index=\'' + idx + '\''
									+ ' data-attr-field=\'label\''
									+ '>' + labelText + '</span>'
									+ '</button>';
							} );

							// Add tab button — uses existing generic data-ve-add-inner-block
							tabButtons += '<button type=\'button\''
								+ ' class=\'tab text-base-content/40 hover:text-base-content transition-colors\''
								+ ' data-ve-add-inner-block'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' data-block-type=\'tab-panel\''
								+ ' title=\'' + {{ Js::from( __( 'visual-editor::ve.tabs_add_tab' ) ) }} + '\''
								+ '>'
								+ '<svg class=\'w-4 h-4\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4.5v15m7.5-7.5h-15\' />'
								+ '</svg>'
								+ '</button>';

							// Tab panels — use generic data-ve-panel for switching
							let panelsHtml = '<div class=\'ve-tabs-content\' style=\'' + contentStyle + '\'>';

							innerBlocks.forEach( ( inner, idx ) => {
								const isActive = 0 === idx;
								panelsHtml += '<div data-ve-panel=\'' + idx + '\'' + ( ! isActive ? ' style=\'display:none;\'' : '' ) + '>';
								panelsHtml += br.getHtml( { ...inner, attributes: { ...inner.attributes, _parentId: block.id } }, context );
								panelsHtml += '</div>';
							} );

							panelsHtml += '</div>';

							// data-ve-panel-group enables the generic panel switcher
							return '<div class=\'' + wrapperClasses + '\'' + ( wrapperStyle ? ' style=\'' + wrapperStyle + '\'' : '' ) + ' data-ve-panel-group data-tab-position=\'' + tabPosition + '\'>'
								+ '<div class=\'' + tabsClasses + '\' role=\'tablist\'' + ( tabListStyle ? ' style=\'' + tabListStyle + '\'' : '' ) + '>'
								+ tabButtons
								+ '</div>'
								+ panelsHtml
								+ '</div>';
						},
					} );

					br.register( 'tab-panel', {
						render( block, context ) {
							const pid = block.attributes?._parentId || '';

							const innerHtml = br.renderInnerBlocks( block, {
								orientation: 'vertical',
								placeholder: {{ Js::from( __( 'visual-editor::ve.tabs_tab_placeholder' ) ) }},
								context: context,
							} );

							return '<div class=\'ve-block ve-block-tab-panel ve-block-editing\''
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ innerHtml
								+ '</div>';
						},
					} );

					br.register( 'accordion', {
						render( block, context ) {
							let sections        = block.attributes?.sections || [];
							const allowMultiple = block.attributes?.allowMultiple || false;
							const iconStyle     = block.attributes?.iconStyle || 'chevron';
							const iconPosition  = block.attributes?.iconPosition || 'right';
							const bordered      = block.attributes?.bordered !== false;
							const accStyle      = block.attributes?.accordionStyle || 'default';
							const headerBg      = veSanitizeCssColor( block.attributes?.headerBackground || '' );
							const contentBg     = veSanitizeCssColor( block.attributes?.contentBackground || '' );
							const borderColor   = veSanitizeCssColor( block.attributes?.borderColor || '' );
							const activeHdr     = veSanitizeCssColor( block.attributes?.activeHeaderColor || '' );
							const innerBlocks   = block.innerBlocks || [];

							// Auto-populate sections metadata from inner blocks if empty or mismatched.
							if ( sections.length < innerBlocks.length ) {
								const store = Alpine.store( 'editor' );
								const newSections = [ ...sections ];
								for ( let i = sections.length; i < innerBlocks.length; i++ ) {
									newSections.push( { id: i, title: {{ Js::from( __( 'visual-editor::ve.accordion_section_title_placeholder' ) ) }} + ' ' + ( i + 1 ), isOpen: 0 === i, headingLevel: 'h3' } );
								}
								sections = newSections;
								if ( store ) { store.updateBlock( block.id, { sections: newSections } ); }
							}

							let classes = 've-block ve-block-accordion ve-block-editing'
								+ ' ve-accordion-' + accStyle
								+ ' ve-accordion-icon-' + iconStyle
								+ ' ve-accordion-icon-' + iconPosition;
							if ( bordered ) { classes += ' ve-accordion-bordered'; }

							let cssVars = '';
							if ( headerBg ) { cssVars += '--ve-accordion-header-bg:' + headerBg + ';'; }
							if ( contentBg ) { cssVars += '--ve-accordion-content-bg:' + contentBg + ';'; }
							if ( borderColor ) { cssVars += '--ve-accordion-border-color:' + borderColor + ';'; }
							if ( activeHdr ) { cssVars += '--ve-accordion-active-header-color:' + activeHdr + ';'; }

							// Icon SVG
							let iconSvg = '';
							if ( 'chevron' === iconStyle ) {
								iconSvg = '<svg class=\'ve-accordion-icon w-4 h-4 shrink-0 transition-transform\' style=\'transform:rotate(90deg);\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m9 5 7 7-7 7\'/>'
									+ '</svg>';
							} else if ( 'plus-minus' === iconStyle ) {
								iconSvg = '<svg class=\'ve-accordion-icon w-4 h-4 shrink-0\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
									+ '<path class=\'ve-plus-vertical\' stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4v16\'/>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M4 12h16\'/>'
									+ '</svg>';
							} else if ( 'caret' === iconStyle ) {
								iconSvg = '<svg class=\'ve-accordion-icon w-4 h-4 shrink-0 transition-transform\' style=\'transform:rotate(90deg);\' viewBox=\'0 0 24 24\' fill=\'currentColor\'>'
									+ '<path d=\'M7 10l5 5 5-5z\'/>'
									+ '</svg>';
							}

							let html = '<div class=\'' + classes + '\'' + ( cssVars ? ' style=\'' + cssVars + '\'' : '' ) + '>';

							innerBlocks.forEach( ( inner, idx ) => {
								const section  = sections[ idx ] || {};
								const title    = section.title || ( {{ Js::from( __( 'visual-editor::ve.accordion_section_title_placeholder' ) ) }} + ' ' + ( idx + 1 ) );
								const hLevel   = section.headingLevel || 'h3';
								const hTag     = [ 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( hLevel ) ? hLevel : 'h3';

								// Preserve title from DOM.
								const existingTitle = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] .ve-accordion-title-editable[data-attr-index=\'' + idx + '\']' );
								const titleText = existingTitle ? existingTitle.innerHTML : title;

								let headerStyle = 'cursor:pointer;padding:0.75rem 1rem;';
								if ( headerBg ) { headerStyle += 'background-color:' + headerBg + ';'; }
								if ( bordered ) { headerStyle += 'border:1px solid ' + ( borderColor || 'oklch(var(--bc)/0.2)' ) + ';'; }

								let contentStyle = 'padding:0.75rem 1rem;';
								if ( contentBg ) { contentStyle += 'background-color:' + contentBg + ';'; }
								if ( bordered ) { contentStyle += 'border:1px solid ' + ( borderColor || 'oklch(var(--bc)/0.2)' ) + ';border-top:none;'; }

								// Use generic data-ve-section / data-ve-toggle-section / data-ve-section-content
								html += '<div class=\'ve-accordion-section\' data-ve-section>';

								// Header with icon — uses generic data-ve-toggle-section
								const sectionIsOpen = section.isOpen !== false;
								html += '<' + hTag + ' class=\'ve-accordion-header flex items-center gap-2\''
									+ ' style=\'' + headerStyle + '\''
									+ ' role=\'button\' tabindex=\'0\''
									+ ' aria-expanded=\'' + ( sectionIsOpen ? 'true' : 'false' ) + '\''
									+ ' data-ve-toggle-section'
									+ '>';
								if ( iconSvg && 'left' === iconPosition ) { html += sectionIsOpen ? iconSvg : iconSvg.replace( "transform:rotate(90deg);", "transform:none;" ); }
								html += '<span class=\'ve-accordion-title-editable flex-1\' contenteditable=\'true\''
									+ ' data-ve-sync-parent-array'
									+ ' data-parent-id=\'' + block.id + '\''
									+ ' data-attr-name=\'sections\''
									+ ' data-attr-index=\'' + idx + '\''
									+ ' data-attr-field=\'title\''
									+ '>' + titleText + '</span>';
								if ( iconSvg && 'right' === iconPosition ) { html += sectionIsOpen ? iconSvg : iconSvg.replace( "transform:rotate(90deg);", "transform:none;" ); }
								html += '</' + hTag + '>';

								// Content with inner blocks — uses generic data-ve-section-content
								html += '<div class=\'ve-accordion-content\' style=\'' + contentStyle + ( sectionIsOpen ? '' : 'display:none;' ) + '\' role=\'region\' data-ve-section-content>';
								html += br.getHtml( { ...inner, attributes: { ...inner.attributes, _parentId: block.id } }, context );
								html += '</div>';
								html += '</div>';
							} );

							// Add section button — uses existing generic data-ve-add-inner-block
							html += '<button type=\'button\''
								+ ' class=\'flex items-center justify-center w-full rounded-lg border-2 border-dashed border-base-300 hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer py-3\''
								+ ' data-ve-add-inner-block'
								+ ' data-parent-id=\'' + block.id + '\''
								+ ' data-block-type=\'accordion-section\''
								+ ' title=\'' + {{ Js::from( __( 'visual-editor::ve.accordion_add_section' ) ) }} + '\''
								+ '>'
								+ '<svg class=\'w-4 h-4 text-base-content/40\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
								+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4.5v15m7.5-7.5h-15\' />'
								+ '</svg>'
								+ '</button>';

							html += '</div>';
							return html;
						},
					} );

					br.register( 'accordion-section', {
						render( block, context ) {
							const pid = block.attributes?._parentId || '';

							const innerHtml = br.renderInnerBlocks( block, {
								orientation: 'vertical',
								placeholder: {{ Js::from( __( 'visual-editor::ve.accordion_section_placeholder' ) ) }},
								context: context,
							} );

							return '<div class=\'ve-block ve-block-accordion-section ve-block-editing\''
								+ ' data-block-id=\'' + block.id + '\''
								+ ( pid ? ' data-parent-id=\'' + pid + '\'' : '' )
								+ ' tabindex=\'-1\''
								+ '>'
								+ innerHtml
								+ '</div>';
						},
					} );

					br.register( 'details', {
						render( block, context ) {
							const summary     = block.attributes?.summary || '';
							const isOpen      = block.attributes?.isOpenByDefault || false;
							const icon        = block.attributes?.icon || 'chevron';
							const iconPos     = block.attributes?.iconPosition || 'left';
							const borderStyleRaw    = block.attributes?.borderStyle || 'default';
							const allowedBorderStyles = [ 'default', 'card', 'minimal', 'borderless' ];
							const borderStyle = allowedBorderStyles.includes( borderStyleRaw ) ? borderStyleRaw : 'default';
							const summaryBg   = veSanitizeCssColor( block.attributes?.summaryBackgroundColor || '' );
							const contentBg   = veSanitizeCssColor( block.attributes?.contentBackgroundColor || '' );
							const textColor   = veSanitizeCssColor( block.attributes?.textColor || '' );
							const bgColor     = veSanitizeCssColor( block.attributes?.backgroundColor || '' );
							const fontSize    = veSanitizeCssDimension( block.attributes?.fontSize || '' );

							// Preserve summary text from DOM to avoid losing focus.
							const existingSummary = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] .ve-details-summary' );
							const summaryText     = existingSummary ? existingSummary.innerHTML : summary;

							let wrapperStyle = '';
							if ( textColor ) { wrapperStyle += 'color:' + textColor + ';'; }
							if ( bgColor ) { wrapperStyle += 'background-color:' + bgColor + ';'; }
							if ( fontSize ) { wrapperStyle += 'font-size:' + fontSize + ';'; }

							let summaryStyle = '';
							if ( summaryBg ) { summaryStyle += 'background-color:' + summaryBg + ';'; }

							let contentStyle = '';
							if ( contentBg ) { contentStyle += 'background-color:' + contentBg + ';'; }

							// Icon SVG
							let iconSvg = '';
							if ( 'chevron' === icon ) {
								iconSvg = '<svg class=\'ve-details-icon ve-details-icon-chevron\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m9 5 7 7-7 7\'/>'
									+ '</svg>';
							} else if ( 'plus-minus' === icon ) {
								iconSvg = '<svg class=\'ve-details-icon ve-details-icon-plus-minus\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'>'
									+ '<path class=\'ve-plus-vertical\' stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 4v16\'/>'
									+ '<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M4 12h16\'/>'
									+ '</svg>';
							}

							let borderClass = 've-details-' + borderStyle;

							let html = '<details class=\'ve-block ve-block-details ve-block-editing ' + borderClass + '\''
								+ ( isOpen ? ' open' : '' )
								+ ( wrapperStyle ? ' style=\'' + wrapperStyle + '\'' : '' )
								+ '>';

							// Summary element
							html += '<summary class=\'ve-details-summary-row\''
								+ ( summaryStyle ? ' style=\'' + summaryStyle + '\'' : '' )
								+ '>';

							if ( iconSvg && 'left' === iconPos ) {
								html += iconSvg;
							}

							html += '<span class=\'ve-details-summary flex-1\''
								+ ' contenteditable=\'true\''
								+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.block_details_summary_placeholder' ) ) }} + '\''
								+ '>' + summaryText + '</span>';

							if ( iconSvg && 'right' === iconPos ) {
								html += iconSvg;
							}

							html += '</summary>';

							// Content area with inner blocks
							html += '<div class=\'ve-details-content\''
								+ ( contentStyle ? ' style=\'' + contentStyle + '\'' : '' )
								+ '>';

							html += br.renderInnerBlocks( block, {
								orientation: 'vertical',
								placeholder: {{ Js::from( __( 'visual-editor::ve.block_details_content_placeholder' ) ) }},
								context: context,
							} );

							html += '</div>';
							html += '</details>';

							return html;
						},
					} );

					br.register( 'table', {
						render( block, context ) {
							const rows          = block.attributes?.rows || null;
							const hasHeaderRow  = block.attributes?.hasHeaderRow || false;
							const hasHeaderCol  = block.attributes?.hasHeaderColumn || false;
							const hasFooterRow  = block.attributes?.hasFooterRow || false;
							const caption       = block.attributes?.caption || '';
							const striped       = block.attributes?.striped || false;
							const bordered      = block.attributes?.bordered !== false;
							const fixedLayout   = block.attributes?.fixedLayout || false;
							const headerBg      = veSanitizeCssColor( block.attributes?.headerBackgroundColor || '' );
							const stripeBg      = veSanitizeCssColor( block.attributes?.stripeColor || '' );
							const borderColor   = veSanitizeCssColor( block.attributes?.borderColor || '' );
							const textColor     = veSanitizeCssColor( block.attributes?.textColor || '' );
							const bgColor       = veSanitizeCssColor( block.attributes?.backgroundColor || '' );

							// === Layout picker (shown when rows is null) ===
							if ( ! rows ) {
								const newCell = () => ( { content: '', colSpan: 1, rowSpan: 1, alignment: 'left' } );
								const makeRow = ( cols ) => Array.from( { length: cols }, newCell );

								const presets = [
									{ key: '2x2', label: {{ Js::from( __( 'visual-editor::ve.table_layout_2x2' ) ) }}, bodyRows: 2, cols: 2, header: false, footer: false,
									  visual: '<div class=\'grid gap-px\' style=\'grid-template-columns:1fr 1fr;width:100%;\'>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '</div>' },
									{ key: '3x3', label: {{ Js::from( __( 'visual-editor::ve.table_layout_3x3' ) ) }}, bodyRows: 3, cols: 3, header: false, footer: false,
									  visual: '<div class=\'grid gap-px\' style=\'grid-template-columns:1fr 1fr 1fr;width:100%;\'>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '</div>' },
									{ key: '4x4', label: {{ Js::from( __( 'visual-editor::ve.table_layout_4x4' ) ) }}, bodyRows: 4, cols: 4, header: false, footer: false,
									  visual: '<div class=\'grid gap-px\' style=\'grid-template-columns:1fr 1fr 1fr 1fr;width:100%;\'>'
										+ '<div class=\'h-2.5 bg-base-content/20 rounded-sm\'></div>'.repeat( 16 )
										+ '</div>' },
									{ key: '2x3h', label: {{ Js::from( __( 'visual-editor::ve.table_layout_2x3_header' ) ) }}, bodyRows: 2, cols: 3, header: true, footer: false,
									  visual: '<div class=\'grid gap-px\' style=\'grid-template-columns:1fr 1fr 1fr;width:100%;\'>'
										+ '<div class=\'h-3 bg-base-content/40 rounded-sm\'></div><div class=\'h-3 bg-base-content/40 rounded-sm\'></div><div class=\'h-3 bg-base-content/40 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '</div>' },
									{ key: '3x3h', label: {{ Js::from( __( 'visual-editor::ve.table_layout_3x3_header' ) ) }}, bodyRows: 3, cols: 3, header: true, footer: false,
									  visual: '<div class=\'grid gap-px\' style=\'grid-template-columns:1fr 1fr 1fr;width:100%;\'>'
										+ '<div class=\'h-3 bg-base-content/40 rounded-sm\'></div><div class=\'h-3 bg-base-content/40 rounded-sm\'></div><div class=\'h-3 bg-base-content/40 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '<div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div><div class=\'h-3 bg-base-content/20 rounded-sm\'></div>'
										+ '</div>' },
									{ key: '3x4hf', label: {{ Js::from( __( 'visual-editor::ve.table_layout_3x4_hf' ) ) }}, bodyRows: 3, cols: 4, header: true, footer: true,
									  visual: '<div class=\'grid gap-px\' style=\'grid-template-columns:1fr 1fr 1fr 1fr;width:100%;\'>'
										+ '<div class=\'h-2.5 bg-base-content/40 rounded-sm\'></div>'.repeat( 4 )
										+ '<div class=\'h-2.5 bg-base-content/20 rounded-sm\'></div>'.repeat( 12 )
										+ '<div class=\'h-2.5 bg-base-content/30 rounded-sm\'></div>'.repeat( 4 )
										+ '</div>' },
								];

								let html = '<div class=\'ve-block ve-block-table ve-block-editing\'>'
									+ '<div class=\'ve-table-layout-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full\'>'
									+ '<p class=\'text-sm text-base-content/60\'>' + {{ Js::from( __( 'visual-editor::ve.table_layout_picker' ) ) }} + '</p>'
									+ '<div class=\'grid grid-cols-3 gap-2 w-full max-w-md\'>';

								presets.forEach( ( preset ) => {
									html += '<button type=\'button\' class=\'ve-layout-btn flex flex-col items-center gap-1.5 rounded-lg border border-base-300 px-3 py-3 hover:border-primary hover:bg-primary/5 transition-colors\''
										+ ' data-ve-set-table-layout=\'' + preset.key + '\''
										+ ' data-body-rows=\'' + preset.bodyRows + '\''
										+ ' data-cols=\'' + preset.cols + '\''
										+ ' data-header=\'' + ( preset.header ? '1' : '0' ) + '\''
										+ ' data-footer=\'' + ( preset.footer ? '1' : '0' ) + '\''
										+ '>'
										+ preset.visual
										+ '<span class=\'text-xs font-medium\'>' + preset.label + '</span>'
										+ '</button>';
								} );

								html += '</div>';

								// Custom table builder
								html += '<div class=\'ve-table-custom-builder flex flex-col items-center gap-3 w-full max-w-md pt-2 border-t border-base-300\'>'
									+ '<p class=\'text-xs font-medium text-base-content/60\'>' + {{ Js::from( __( 'visual-editor::ve.table_layout_custom' ) ) }} + '</p>'
									+ '<div class=\'flex items-center gap-4\'>'
									+ '<label class=\'flex items-center gap-2 text-xs\'>'
									+ '<span>' + {{ Js::from( __( 'visual-editor::ve.table_custom_columns' ) ) }} + '</span>'
									+ '<input type=\'number\' min=\'1\' max=\'20\' value=\'3\' class=\'input input-bordered input-xs w-16 text-center\' data-ve-table-custom-cols />'
									+ '</label>'
									+ '<label class=\'flex items-center gap-2 text-xs\'>'
									+ '<span>' + {{ Js::from( __( 'visual-editor::ve.table_custom_rows' ) ) }} + '</span>'
									+ '<input type=\'number\' min=\'1\' max=\'50\' value=\'3\' class=\'input input-bordered input-xs w-16 text-center\' data-ve-table-custom-rows />'
									+ '</label>'
									+ '</div>'
									+ '<div class=\'flex items-center gap-4\'>'
									+ '<label class=\'flex items-center gap-2 text-xs cursor-pointer\'>'
									+ '<input type=\'checkbox\' class=\'checkbox checkbox-xs\' data-ve-table-custom-header />'
									+ '<span>' + {{ Js::from( __( 'visual-editor::ve.table_custom_header_row' ) ) }} + '</span>'
									+ '</label>'
									+ '<label class=\'flex items-center gap-2 text-xs cursor-pointer\'>'
									+ '<input type=\'checkbox\' class=\'checkbox checkbox-xs\' data-ve-table-custom-footer />'
									+ '<span>' + {{ Js::from( __( 'visual-editor::ve.table_custom_footer_row' ) ) }} + '</span>'
									+ '</label>'
									+ '</div>'
									+ '<button type=\'button\' class=\'btn btn-primary btn-xs\' data-ve-set-table-layout=\'custom\'>'
									+ {{ Js::from( __( 'visual-editor::ve.table_custom_create' ) ) }}
									+ '</button>'
									+ '</div>';

								html += '</div></div>';
								return html;
							}

							// === Table rendering ===
							let tableClasses = 've-block ve-block-table ve-block-editing';
							if ( striped ) { tableClasses += ' ve-table-striped'; }
							if ( bordered ) { tableClasses += ' ve-table-bordered'; }

							// Wrapper div styles (CSS custom properties, color, background).
							let wrapperStyle = '';
							if ( textColor ) { wrapperStyle += 'color:' + textColor + ';'; }
							if ( bgColor ) { wrapperStyle += 'background-color:' + bgColor + ';'; }
							if ( borderColor ) { wrapperStyle += '--ve-table-border-color:' + borderColor + ';'; }
							if ( stripeBg ) { wrapperStyle += '--ve-table-stripe-color:' + stripeBg + ';'; }

							// Actual <table> element styles (table-layout only works on <table>).
							let tableElStyle = '';
							if ( fixedLayout ) { tableElStyle += 'table-layout:fixed;'; }

							// Preserve existing cell content from DOM to avoid losing focus.
							const existingTable = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] table' );
							const existingCells = {};
							if ( existingTable ) {
								existingTable.querySelectorAll( '[data-row][data-col]' ).forEach( ( cell ) => {
									existingCells[ cell.getAttribute( 'data-row' ) + '-' + cell.getAttribute( 'data-col' ) ] = cell.innerHTML;
								} );
							}

							// Preserve caption from DOM.
							const existingCaption = document.querySelector( '[data-block-id=\'' + CSS.escape( block.id ) + '\'] caption' );
							const captionText     = existingCaption ? existingCaption.innerHTML : caption;

							const totalRows = rows.length;
							const numCols   = rows[ 0 ] ? rows[ 0 ].length : 2;

							// Helper: render a single cell.
							const renderCell = ( rowIdx, colIdx, cell, isHeader, scope ) => {
								const key       = rowIdx + '-' + colIdx;
								const cellText  = existingCells.hasOwnProperty( key ) ? existingCells[ key ] : ( cell.content || '' );
								const cellAlignRaw = cell.alignment || 'left';
								const cellAlign = [ 'left', 'center', 'right', 'justify' ].includes( cellAlignRaw ) ? cellAlignRaw : 'left';
								const colSpan   = cell.colSpan || 1;
								const rowSpan   = cell.rowSpan || 1;
								const tag       = isHeader ? 'th' : 'td';
								let attrs       = ' contenteditable=\'true\''
									+ ' data-row=\'' + rowIdx + '\' data-col=\'' + colIdx + '\''
									+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.table_cell_placeholder' ) ) }} + '\''
									+ ' style=\'text-align:' + cellAlign + ';\'';
								if ( scope ) { attrs += ' scope=\'' + scope + '\''; }
								if ( colSpan > 1 ) { attrs += ' colspan=\'' + colSpan + '\''; }
								if ( rowSpan > 1 ) { attrs += ' rowspan=\'' + rowSpan + '\''; }
								return '<' + tag + attrs + '>' + cellText + '</' + tag + '>';
							};

							// Helper: render a delete-row button in the gutter.
							const renderRowBtn = ( rowIdx, section ) => {
								return '<td class=\'ve-table-gutter\' contenteditable=\'false\'>'
									+ '<div class=\'ve-table-row-actions\'>'
									+ '<button type=\'button\' class=\'ve-table-action-btn\' data-ve-table-action=\'insert-row-above\' data-action-row=\'' + rowIdx + '\' data-action-section=\'' + section + '\' title=\'' + {{ Js::from( __( 'visual-editor::ve.table_insert_row_above' ) ) }} + '\'>'
									+ '<svg viewBox=\'0 0 20 20\' fill=\'currentColor\' class=\'w-3 h-3\'><path d=\'M10 5a.75.75 0 0 1 .75.75v3.5h3.5a.75.75 0 0 1 0 1.5h-3.5v3.5a.75.75 0 0 1-1.5 0v-3.5h-3.5a.75.75 0 0 1 0-1.5h3.5v-3.5A.75.75 0 0 1 10 5Z\'/></svg>'
									+ '</button>'
									+ ( totalRows > 1 ? '<button type=\'button\' class=\'ve-table-action-btn ve-table-action-btn-danger\' data-ve-table-action=\'delete-row\' data-action-row=\'' + rowIdx + '\' title=\'' + {{ Js::from( __( 'visual-editor::ve.table_delete_row' ) ) }} + '\'>'
									+ '<svg viewBox=\'0 0 20 20\' fill=\'currentColor\' class=\'w-3 h-3\'><path d=\'M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z\'/></svg>'
									+ '</button>' : '' )
									+ '</div></td>';
							};

							let html = '<div class=\'' + tableClasses + '\''
								+ ( wrapperStyle ? ' style=\'' + wrapperStyle + '\'' : '' )
								+ '>';

							// Column action controls (outside table for valid HTML)
							html += '<div class=\'ve-table-col-actions-row\' contenteditable=\'false\'>';
							for ( let c = 0; c < numCols; c++ ) {
								html += '<div class=\'ve-table-col-actions\'>'
									+ '<button type=\'button\' class=\'ve-table-action-btn\' data-ve-table-action=\'insert-col-left\' data-action-col=\'' + c + '\' title=\'' + {{ Js::from( __( 'visual-editor::ve.table_insert_column_left' ) ) }} + '\'>'
									+ '<svg viewBox=\'0 0 20 20\' fill=\'currentColor\' class=\'w-3 h-3\'><path d=\'M10 5a.75.75 0 0 1 .75.75v3.5h3.5a.75.75 0 0 1 0 1.5h-3.5v3.5a.75.75 0 0 1-1.5 0v-3.5h-3.5a.75.75 0 0 1 0-1.5h3.5v-3.5A.75.75 0 0 1 10 5Z\'/></svg>'
									+ '</button>'
									+ ( numCols > 1 ? '<button type=\'button\' class=\'ve-table-action-btn ve-table-action-btn-danger\' data-ve-table-action=\'delete-col\' data-action-col=\'' + c + '\' title=\'' + {{ Js::from( __( 'visual-editor::ve.table_delete_column' ) ) }} + '\'>'
									+ '<svg viewBox=\'0 0 20 20\' fill=\'currentColor\' class=\'w-3 h-3\'><path d=\'M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z\'/></svg>'
									+ '</button>' : '' )
									+ '</div>';
							}
							html += '</div>';

							html += '<table' + ( tableElStyle ? ' style=\'' + tableElStyle + '\'' : '' ) + '>';

							// Caption (must be first child of table per HTML spec)
							html += '<caption contenteditable=\'true\''
								+ ' data-placeholder=\'' + {{ Js::from( __( 'visual-editor::ve.table_caption_placeholder' ) ) }} + '\''
								+ '>' + captionText + '</caption>';

							// Header row (row index 0 when hasHeaderRow is true)
							if ( hasHeaderRow && totalRows > 0 ) {
								let headStyle = '';
								if ( headerBg ) { headStyle = ' style=\'background-color:' + headerBg + ';\''; }
								html += '<thead class=\'ve-table-header\'' + headStyle + '><tr>';
								const row = rows[ 0 ];
								if ( row ) {
									row.forEach( ( cell, colIdx ) => {
										html += renderCell( 0, colIdx, cell, true, 'col' );
									} );
								}
								html += renderRowBtn( 0, 'header' );
								html += '</tr></thead>';
							}

							// Tbody
							html += '<tbody>';
							const bodyStart = hasHeaderRow ? 1 : 0;
							const bodyEnd   = hasFooterRow && totalRows > 1 ? totalRows - 1 : totalRows;
							for ( let rowIdx = bodyStart; rowIdx < bodyEnd; rowIdx++ ) {
								html += '<tr>';
								const row = rows[ rowIdx ];
								if ( row ) {
									row.forEach( ( cell, colIdx ) => {
										const isHdrCol = hasHeaderCol && 0 === colIdx;
										html += renderCell( rowIdx, colIdx, cell, isHdrCol, isHdrCol ? 'row' : null );
									} );
								}
								html += renderRowBtn( rowIdx, 'body' );
								html += '</tr>';
							}
							html += '</tbody>';

							// Footer row (last row when hasFooterRow is true)
							if ( hasFooterRow && totalRows > 1 ) {
								const footerRowIdx = totalRows - 1;
								html += '<tfoot><tr>';
								const row = rows[ footerRowIdx ];
								if ( row ) {
									row.forEach( ( cell, colIdx ) => {
										const isHdrCol = hasHeaderCol && 0 === colIdx;
										html += renderCell( footerRowIdx, colIdx, cell, isHdrCol, isHdrCol ? 'row' : null );
									} );
								}
								html += renderRowBtn( footerRowIdx, 'footer' );
								html += '</tr></tfoot>';
							}

							html += '</table>';

							// Bottom toolbar for quick add row/column
							html += '<div class=\'ve-table-toolbar\'>'
								+ '<button type=\'button\' data-ve-table-action=\'add-row\'>'
								+ '<svg viewBox=\'0 0 20 20\' fill=\'currentColor\' class=\'w-3.5 h-3.5\'><path d=\'M10 5a.75.75 0 0 1 .75.75v3.5h3.5a.75.75 0 0 1 0 1.5h-3.5v3.5a.75.75 0 0 1-1.5 0v-3.5h-3.5a.75.75 0 0 1 0-1.5h3.5v-3.5A.75.75 0 0 1 10 5Z\'/></svg> '
								+ {{ Js::from( __( 'visual-editor::ve.table_add_row' ) ) }}
								+ '</button>'
								+ '<button type=\'button\' data-ve-table-action=\'add-column\'>'
								+ '<svg viewBox=\'0 0 20 20\' fill=\'currentColor\' class=\'w-3.5 h-3.5\'><path d=\'M10 5a.75.75 0 0 1 .75.75v3.5h3.5a.75.75 0 0 1 0 1.5h-3.5v3.5a.75.75 0 0 1-1.5 0v-3.5h-3.5a.75.75 0 0 1 0-1.5h3.5v-3.5A.75.75 0 0 1 10 5Z\'/></svg> '
								+ {{ Js::from( __( 'visual-editor::ve.table_add_column' ) ) }}
								+ '</button>'
								+ '</div>';

							html += '</div>';

							return html;
						},
					} );

					// ── Shared: extract iframe src from oEmbed HTML ──
					const veExtractIframeSrc = ( html, url ) => {
						// Standard iframe src extraction.
						const iframeMatch = html.match( /<iframe[^>]+src=["']([^"']+)["']/ );
						if ( iframeMatch ) return iframeMatch[1];

						// Bluesky: extract AT URI from data-bluesky-uri and build embed URL.
						const bskyMatch = html.match( /data-bluesky-uri=["']at:\/\/([^"']+)["']/ );
						if ( bskyMatch ) return 'https://embed.bsky.app/embed/' + encodeURIComponent( bskyMatch[1] );

						// Fallback: try to construct embed URL from the original URL for known platforms.
						if ( url ) {
							const bskyUrl = url.match( /bsky\.app\/profile\/([^/]+)\/post\/([^/?]+)/ );
							if ( bskyUrl ) return 'https://embed.bsky.app/embed/' + encodeURIComponent( bskyUrl[1] ) + '/app.bsky.feed.post/' + encodeURIComponent( bskyUrl[2] );
						}

						return null;
					};

					// ── Shared: resolve embed via API ─────────────────
					const veResolveEmbed = async ( blockId, url, extraAttrs = {} ) => {
						try {
							const r = await fetch( '/api/visual-editor/embed/resolve', {
								method: 'POST',
								headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
								body: JSON.stringify( { url } ),
							} );
							const j = await r.json();
							if ( j.success && j.data ) {
								// Guard against stale responses: only apply if the
								// block still exists and its URL hasn't changed.
								const current = Alpine.store( 'editor' )?.getBlock( blockId );
								if ( ! current ) return false;
								if ( current.attributes?.url && current.attributes.url !== url ) return false;
								Alpine.store( 'editor' ).updateBlock( blockId, Object.assign( {
									url:          url,
									html:         j.data.html || '',
									title:        j.data.title || '',
									description:  j.data.description || '',
									thumbnailUrl: j.data.thumbnailUrl || j.data.thumbnail_url || '',
									providerName: j.data.provider_name || j.data.providerName || '',
									providerUrl:  j.data.provider_url || j.data.providerUrl || '',
									_source:      j.data._source || '',
									platform:     j.platform || '',
								}, extraAttrs ) );
								return true;
							}
						} catch ( e ) { /* resolve failed */ }
						return false;
					};

					// Enter key handler for embed URL inputs
					document.addEventListener( 'keydown', async ( e ) => {
						if ( 'Enter' !== e.key ) return;
						const input = e.target.closest( '[data-ve-url-input]' );
						if ( input ) {
							e.preventDefault();
							const wrapper = input.closest( '.ve-block' );
							const btn = wrapper?.querySelector( '[data-ve-resolve-embed]' );
							if ( btn ) btn.click();
							return;
						}
						const mapInput = e.target.closest( '[data-ve-map-address]' );
						if ( mapInput ) {
							e.preventDefault();
							const wrapper = mapInput.closest( '.ve-block' );
							const btn = wrapper?.querySelector( '[data-ve-map-search]' );
							if ( btn ) btn.click();
						}
					} );

					// Custom HTML textarea input handler (per-block debounce).
					const veHtmlTimers = {};
					document.addEventListener( 'input', ( e ) => {
						const textarea = e.target.closest( '.ve-custom-html-textarea[data-ve-block-id]' );
						if ( ! textarea ) return;
						const bid = textarea.getAttribute( 'data-ve-block-id' );
						clearTimeout( veHtmlTimers[ bid ] );
						veHtmlTimers[ bid ] = setTimeout( () => {
							delete veHtmlTimers[ bid ];
							Alpine.store( 'editor' ).updateBlock( bid, { content: textarea.value } );
						}, 500 );
					} );

					// ── Embed block renderer ──────────────────────────
					br.register( 'embed', {
						render( block ) {
							const url          = block.attributes?.url || '';
							const html         = block.attributes?.html || '';
							const source       = block.attributes?._source || '';
							const title        = block.attributes?.title || '';
							const description  = block.attributes?.description || '';
							const thumbnailUrl = block.attributes?.thumbnailUrl || '';
							const caption      = block.attributes?.caption || '';
							const aspectRatio  = block.attributes?.aspectRatio || '16:9';
							const responsive   = block.attributes?.responsive !== false;

							const aspectMap  = { '16:9': '56.25%', '4:3': '75%', '1:1': '100%' };
							const paddingTop = aspectMap[ aspectRatio ] || '56.25%';
							const blockId    = block.id;

							if ( ! url ) {
								return '<div class="ve-block ve-block-embed ve-block-editing">'
									+ '<div class="ve-embed-placeholder flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 px-6 py-10">'
									+ '<svg class="w-10 h-10 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>'
									+ '<p class="ve-resolve-hint text-sm text-base-content/60">' + {{ Js::from( __( 'visual-editor::ve.embed_placeholder' ) ) }} + '</p>'
									+ '<p class="ve-resolve-error text-sm text-warning" style="display:none">' + {{ Js::from( __( 'visual-editor::ve.embed_resolve_failed' ) ) }} + '</p>'
									+ '<div class="flex w-full max-w-md gap-2">'
									+ '<input type="url" class="input input-bordered input-sm flex-1" data-ve-url-input placeholder="' + {{ Js::from( __( 'visual-editor::ve.embed_url_placeholder' ) ) }} + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.embed_url_placeholder' ) ) }} + '" />'
									+ '<button type="button" class="btn btn-primary btn-sm" data-ve-resolve-embed="' + blockId + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.embed_resolve' ) ) }} + '">'
									+ '<span class="ve-resolve-label">' + {{ Js::from( __( 'visual-editor::ve.embed_resolve' ) ) }} + '</span>'
									+ '<span class="ve-resolve-spinner loading loading-spinner loading-xs" style="display:none"></span>'
									+ '</button></div></div></div>';
							}

							if ( html && 'oembed' === source ) {
								// Extract iframe src from oEmbed HTML to render directly (avoids sandbox issues).
								const iframeSrc = veExtractIframeSrc( html, url );
								let iframeStyle = responsive
									? 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;'
									: 'width: 100%; height: 300px; border: 0;';
								let wrapperStyle = responsive
									? 'position: relative; padding-top: ' + paddingTop + '; overflow: hidden;'
									: '';

								let iframeTag;
								if ( iframeSrc ) {
									iframeTag = '<iframe src="' + iframeSrc + '" class="ve-embed-iframe" title="' + veEscapeHtml( title || 'Embedded content' ) + '" style="' + iframeStyle + '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>';
								} else {
									// Non-iframe embeds (blockquotes, tweets, etc.) are rendered
									// via srcdoc inside a sandboxed iframe.  allow-scripts and
									// allow-same-origin are both required so the provider's JS
									// (e.g. Twitter widget) can execute and resize the frame.
									// allow-popups lets links open in a new tab.  Server-side
									// OEmbedService validates providers and the HTML is escaped
									// into the srcdoc attribute to prevent injection.
									const escapedHtml = html.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
									iframeTag = '<iframe srcdoc="' + escapedHtml + '" sandbox="allow-scripts allow-same-origin allow-popups" class="ve-embed-iframe" title="' + veEscapeHtml( title || 'Embedded content' ) + '" style="' + iframeStyle + '" loading="lazy"></iframe>';
								}

								return '<div class="ve-block ve-block-embed ve-block-editing"><figure class="ve-embed-figure">'
									+ '<div class="ve-embed-responsive-wrapper" style="' + wrapperStyle + '">'
									+ iframeTag
									+ '</div>'
									+ ( caption ? '<figcaption class="ve-embed-caption text-center text-sm text-base-content/60 mt-2">' + veEscapeHtml( caption ) + '</figcaption>' : '' )
									+ '</figure></div>';
							}

							if ( title && 'opengraph' === source ) {
								return '<div class="ve-block ve-block-embed ve-block-editing">'
									+ '<div class="ve-embed-fallback-card rounded-lg border border-base-300 bg-base-100 overflow-hidden">'
									+ ( thumbnailUrl ? '<div class="ve-embed-thumbnail aspect-video bg-base-200 overflow-hidden"><img src="' + veEscapeHtml( thumbnailUrl ) + '" alt="' + veEscapeHtml( title ) + '" class="w-full h-full object-cover" loading="lazy" /></div>' : '' )
									+ '<div class="p-4"><h4 class="font-semibold text-sm">' + veEscapeHtml( title ) + '</h4>'
									+ ( description ? '<p class="text-xs text-base-content/60 mt-1 line-clamp-2">' + veEscapeHtml( description ) + '</p>' : '' )
									+ '<p class="text-xs text-base-content/40 mt-2 truncate">' + veEscapeHtml( url ) + '</p></div></div></div>';
							}

							return '<div class="ve-block ve-block-embed ve-block-editing">'
								+ '<div class="ve-embed-error flex flex-col items-center justify-center gap-3 rounded-lg border border-warning/30 bg-warning/5 px-6 py-10">'
								+ '<p class="text-sm text-base-content/60">' + {{ Js::from( __( 'visual-editor::ve.embed_resolve_failed' ) ) }} + '</p>'
								+ '<p class="text-xs text-base-content/40 truncate max-w-md">' + veEscapeHtml( url ) + '</p></div></div>';
						},
					} );

					// ── Social Embed block renderer ───────────────────
					br.register( 'social-embed', {
						render( block ) {
							const url          = block.attributes?.url || '';
							const html         = block.attributes?.html || '';
							const source       = block.attributes?._source || '';
							const platform     = block.attributes?.platform || '';
							const title        = block.attributes?.title || '';
							const description  = block.attributes?.description || '';
							const thumbnailUrl = block.attributes?.thumbnailUrl || '';
							const maxWidth     = block.attributes?.maxWidth || '550px';
							const align        = block.attributes?.align || 'center';
							const blockId      = block.id;

							const alignMap  = { left: 'items-start', center: 'items-center', right: 'items-end' };
							const alignCls  = alignMap[ align ] || 'items-center';
							const platforms = { twitter: 'Twitter/X', instagram: 'Instagram', facebook: 'Facebook', tiktok: 'TikTok', reddit: 'Reddit', bluesky: 'Bluesky' };
							const label     = platforms[ platform ] || '';

							if ( ! url ) {
								return '<div class="ve-block ve-block-social-embed ve-block-editing flex flex-col ' + alignCls + '">'
									+ '<div class="ve-social-placeholder flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 px-6 py-10 w-full">'
									+ '<svg class="w-10 h-10 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>'
									+ '<p class="ve-resolve-hint text-sm text-base-content/60">' + {{ Js::from( __( 'visual-editor::ve.social_placeholder' ) ) }} + '</p>'
									+ '<p class="ve-resolve-error text-sm text-warning" style="display:none">' + {{ Js::from( __( 'visual-editor::ve.embed_resolve_failed' ) ) }} + '</p>'
									+ '<div class="flex w-full max-w-md gap-2">'
									+ '<input type="url" class="input input-bordered input-sm flex-1" data-ve-url-input placeholder="' + {{ Js::from( __( 'visual-editor::ve.social_url_placeholder' ) ) }} + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.social_url_placeholder' ) ) }} + '" />'
									+ '<button type="button" class="btn btn-primary btn-sm" data-ve-resolve-embed="' + blockId + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.embed_resolve' ) ) }} + '">'
									+ '<span class="ve-resolve-label">' + {{ Js::from( __( 'visual-editor::ve.embed_resolve' ) ) }} + '</span>'
									+ '<span class="ve-resolve-spinner loading loading-spinner loading-xs" style="display:none"></span>'
									+ '</button></div></div></div>';
							}

							if ( html && 'oembed' === source ) {
								const iframeSrc = veExtractIframeSrc( html, url );
								let iframeTag;
								if ( iframeSrc ) {
									iframeTag = '<iframe src="' + iframeSrc + '" class="ve-social-iframe" title="Social post from ' + veEscapeHtml( label || 'social media' ) + '" style="width: 100%; border: 0; min-height: 200px;" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>';
								} else {
									const escapedHtml = html.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
									iframeTag = '<iframe srcdoc="' + escapedHtml + '" sandbox="allow-scripts allow-same-origin allow-popups" class="ve-social-iframe" title="Social post from ' + veEscapeHtml( label || 'social media' ) + '" style="width: 100%; border: 0; min-height: 200px;" loading="lazy"></iframe>';
								}
								return '<div class="ve-block ve-block-social-embed ve-block-editing flex flex-col ' + alignCls + '">'
									+ '<div style="max-width: ' + veSanitizeCssDimension( maxWidth ) + '; width: 100%;">'
									+ ( label ? '<div class="inline-flex items-center gap-1 rounded-full bg-base-200 px-2 py-0.5 text-xs font-medium text-base-content/70 mb-2">' + veEscapeHtml( label ) + '</div>' : '' )
									+ iframeTag
									+ '</div></div>';
							}

							if ( title && 'opengraph' === source ) {
								return '<div class="ve-block ve-block-social-embed ve-block-editing flex flex-col ' + alignCls + '">'
									+ '<div class="rounded-lg border border-base-300 bg-base-100 overflow-hidden" style="max-width: ' + veSanitizeCssDimension( maxWidth ) + '; width: 100%;">'
									+ ( label ? '<div class="flex items-center gap-2 px-4 py-2 border-b border-base-200"><span class="text-xs font-medium text-base-content/70">' + veEscapeHtml( label ) + '</span></div>' : '' )
									+ ( thumbnailUrl ? '<div class="aspect-video bg-base-200 overflow-hidden"><img src="' + veEscapeHtml( thumbnailUrl ) + '" alt="' + veEscapeHtml( title ) + '" class="w-full h-full object-cover" loading="lazy" /></div>' : '' )
									+ '<div class="p-4"><h4 class="font-semibold text-sm">' + veEscapeHtml( title ) + '</h4>'
									+ ( description ? '<p class="text-xs text-base-content/60 mt-1 line-clamp-3">' + veEscapeHtml( description ) + '</p>' : '' )
									+ '<p class="text-xs text-base-content/40 mt-2 truncate">' + veEscapeHtml( url ) + '</p></div></div></div>';
							}

							return '<div class="ve-block ve-block-social-embed ve-block-editing flex flex-col ' + alignCls + '">'
								+ '<div class="flex items-center justify-center gap-2 rounded-lg border border-warning/30 bg-warning/5 px-6 py-10 w-full">'
								+ '<p class="text-sm text-base-content/60">' + {{ Js::from( __( 'visual-editor::ve.embed_resolve_failed' ) ) }} + '</p></div></div>';
						},
					} );

					// ── Map Embed block renderer ──────────────────────
					br.register( 'map-embed', {
						render( block ) {
							const provider    = block.attributes?.provider || 'openstreetmap';
							const latitude    = block.attributes?.latitude || '';
							const longitude   = block.attributes?.longitude || '';
							const zoom        = Math.max( 1, Math.min( 20, parseInt( block.attributes?.zoom ) || 13 ) );
							const mapType     = block.attributes?.mapType || 'roadmap';
							const address     = block.attributes?.address || '';
							const markerLabel = block.attributes?.markerLabel || '';
							const interactive = block.attributes?.interactive !== false;
							const height      = block.attributes?.height || '400px';
							const blockId     = block.id;

							const hasCoords = '' !== latitude && '' !== longitude && ! isNaN( parseFloat( latitude ) ) && ! isNaN( parseFloat( longitude ) );

							if ( ! hasCoords ) {
								return '<div class="ve-block ve-block-map-embed ve-block-editing">'
									+ '<div class="ve-map-placeholder flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 px-6 py-10" style="min-height: ' + veSanitizeCssDimension( height ) + ';">'
									+ '<svg class="w-10 h-10 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>'
									+ '<p class="text-sm text-base-content/60">' + {{ Js::from( __( 'visual-editor::ve.map_placeholder' ) ) }} + '</p>'
									+ '<p class="ve-map-error text-sm text-warning" style="display:none">' + {{ Js::from( __( 'visual-editor::ve.map_not_found' ) ) }} + '</p>'
									+ '<div class="flex w-full max-w-md gap-2">'
									+ '<input type="text" class="input input-bordered input-sm flex-1" data-ve-map-address placeholder="' + {{ Js::from( __( 'visual-editor::ve.map_address_placeholder' ) ) }} + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.map_address_placeholder' ) ) }} + '" />'
									+ '<button type="button" class="btn btn-primary btn-sm" data-ve-map-search="' + blockId + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.map_search' ) ) }} + '">'
									+ '<span class="ve-resolve-label">' + {{ Js::from( __( 'visual-editor::ve.map_search' ) ) }} + '</span>'
									+ '<span class="ve-resolve-spinner loading loading-spinner loading-xs" style="display:none"></span>'
									+ '</button></div>'
									+ '<div class="flex w-full max-w-md gap-2 mt-1">'
									+ '<input type="text" class="input input-bordered input-sm flex-1" data-ve-map-lat placeholder="' + {{ Js::from( __( 'visual-editor::ve.map_latitude' ) ) }} + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.map_latitude' ) ) }} + '" />'
									+ '<input type="text" class="input input-bordered input-sm flex-1" data-ve-map-lng placeholder="' + {{ Js::from( __( 'visual-editor::ve.map_longitude' ) ) }} + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.map_longitude' ) ) }} + '" />'
									+ '<button type="button" class="btn btn-ghost btn-sm" data-ve-map-set-coords="' + blockId + '" aria-label="' + {{ Js::from( __( 'visual-editor::ve.map_apply_coordinates' ) ) }} + '">'
									+ '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>'
									+ '</button></div></div></div>';
							}

							const lat = parseFloat( latitude );
							const lng = parseFloat( longitude );
							let iframeSrc = '';

							if ( 'openstreetmap' === provider ) {
								// Calculate a bounding box from center + zoom.
								// At zoom 13 the span is ~0.05 degrees; halve per zoom level.
								const span = 180 / Math.pow( 2, zoom );
								const bbox = ( lng - span ) + ',' + ( lat - span / 2 ) + ',' + ( lng + span ) + ',' + ( lat + span / 2 );
								iframeSrc = 'https://www.openstreetmap.org/export/embed.html?bbox=' + bbox + '&layer=mapnik&marker=' + lat + ',' + lng;
							} else {
								const typeMap = { roadmap: 'm', satellite: 'k', terrain: 'p', hybrid: 'h' };
								const query = lat + ',' + lng;
								iframeSrc = 'https://maps.google.com/maps?q=' + encodeURIComponent( query ) + '&z=' + zoom + '&output=embed&t=' + ( typeMap[ mapType ] || 'm' );
							}

							if ( interactive && iframeSrc ) {
								// Use a unique name based on coordinates so the browser
								// treats each coordinate change as a new iframe context.
								const iframeKey = 've-map-' + lat + '-' + lng + '-' + zoom;
								return '<div class="ve-block ve-block-map-embed ve-block-editing" style="height: ' + veSanitizeCssDimension( height ) + '; overflow: hidden;">'
									+ '<iframe src="' + iframeSrc + '" name="' + iframeKey + '" sandbox="allow-scripts allow-same-origin" class="ve-map-iframe" title="' + veEscapeHtml( markerLabel || 'Map' ) + '" style="width: 100%; height: 100%; border: 0;"></iframe></div>';
							}

							return '<div class="ve-block ve-block-map-embed ve-block-editing" style="height: ' + veSanitizeCssDimension( height ) + '; overflow: hidden;">'
								+ '<div class="ve-map-static flex items-center justify-center bg-base-200 w-full h-full rounded"><div class="text-center">'
								+ '<p class="text-xs text-base-content/60">' + veEscapeHtml( address || ( latitude + ', ' + longitude ) ) + '</p>'
								+ ( markerLabel ? '<p class="text-xs font-medium text-base-content/80 mt-1">' + veEscapeHtml( markerLabel ) + '</p>' : '' )
								+ '</div></div></div>';
						},
					} );

					// ── Custom HTML block renderer ────────────────────
					{{-- Dynamic blocks: Latest Posts, Table of Contents, Search --}}

					br.register( 'latest-posts', {
						render( block ) {
							const displayTemplate   = block.attributes?.displayTemplate || 'list';
							const numberOfPosts     = parseInt( block.attributes?.numberOfPosts || 5, 10 );
							const orderBy           = block.attributes?.orderBy || 'date';
							const order             = block.attributes?.order || 'desc';
							const offset            = parseInt( block.attributes?.offset || 0, 10 );
							const showFeaturedImage = block.attributes?.showFeaturedImage !== false;
							const showExcerpt       = block.attributes?.showExcerpt !== false;
							const showDate          = block.attributes?.showDate !== false;
							const showAuthor        = block.attributes?.showAuthor === true;
							const excerptLength     = parseInt( block.attributes?.excerptLength || 25, 10 );
							const gap               = veSanitizeCssDimension( block.attributes?.gap || '1rem' ) || '1rem';
							const imageAspectRatio  = block.attributes?.imageAspectRatio || '16/9';

							const colData = block.attributes?.columns || { mode: 'global', global: 3 };
							const desktopCols = ( 'responsive' === colData.mode )
								? ( colData.desktop || 3 )
								: ( colData.global || colData.desktop || 3 );
							const tabletCols  = ( 'responsive' === colData.mode ) ? ( colData.tablet || 2 ) : desktopCols;
							const mobileCols  = ( 'responsive' === colData.mode ) ? ( colData.mobile || 1 ) : desktopCols;

							const sampleExcerpt = {{ Js::from( __( 'visual-editor::ve.sample_post_excerpt' ) ) }};
							const sampleAuthor  = {{ Js::from( __( 'visual-editor::ve.sample_author' ) ) }};
							const postTitleBase = {{ Js::from( __( 'visual-editor::ve.sample_post_title', [ 'number' => '__NUM__' ] ) ) }};

							const sampleTitles = [
								'Getting Started with Laravel',
								'Advanced Eloquent Techniques',
								'Building APIs with Sanctum',
								'Tailwind CSS Best Practices',
								'Livewire Components Deep Dive',
								'Testing with Pest',
								'Database Migrations Guide',
								'Queue Workers Explained',
								'Blade Templates Mastery',
								'Deploy to Production',
							];

							const totalSamplePosts = numberOfPosts + offset;
							let allPosts = [];
							for ( let i = 1; i <= totalSamplePosts; i++ ) {
								const d = new Date();
								d.setDate( d.getDate() - i );
								allPosts.push( {
									num: i,
									title: sampleTitles[ ( i - 1 ) % sampleTitles.length ] || postTitleBase.replace( '__NUM__', i ),
									excerpt: sampleExcerpt.split( ' ' ).slice( 0, excerptLength ).join( ' ' ),
									date: d,
									dateStr: d.toLocaleDateString( 'en-US', { month: 'short', day: 'numeric', year: 'numeric' } ),
									author: sampleAuthor,
								} );
							}

							if ( 'title' === orderBy ) {
								allPosts.sort( ( a, b ) => a.title.localeCompare( b.title ) );
							} else if ( 'modified' === orderBy ) {
								allPosts.sort( ( a, b ) => b.date - a.date );
							} else if ( 'random' === orderBy ) {
								for ( let i = allPosts.length - 1; i > 0; i-- ) {
									const j = Math.floor( Math.random() * ( i + 1 ) );
									[ allPosts[ i ], allPosts[ j ] ] = [ allPosts[ j ], allPosts[ i ] ];
								}
							} else {
								allPosts.sort( ( a, b ) => b.date - a.date );
							}

							if ( 'asc' === order && 'random' !== orderBy ) {
								allPosts.reverse();
							}

							const posts = allPosts.slice( offset, offset + numberOfPosts );

							if ( 0 === posts.length ) {
								return '<div class="ve-block ve-block-latest-posts ve-block-editing ve-block-dynamic-preview">'
									+ '<p style="color:#9ca3af;text-align:center;padding:2rem;">' + {{ Js::from( __( 'visual-editor::ve.no_posts_found' ) ) }} + '</p></div>';
							}

							const metaHtml = ( post ) => {
								let parts = [];
								if ( showDate ) { parts.push( '<time>' + veEscapeHtml( post.dateStr ) + '</time>' ); }
								if ( showAuthor ) { parts.push( '<span>' + veEscapeHtml( post.author ) + '</span>' ); }
								return ( showDate || showAuthor )
									? '<div style="font-size:0.85em;color:#6b7280;margin-top:0.25rem;">' + parts.join( ' · ' ) + '</div>'
									: '';
							};

							const excerptHtml = ( post ) => {
								return showExcerpt
									? '<p style="margin:0.5rem 0 0;color:#374151;font-size:0.9em;line-height:1.5;">' + veEscapeHtml( post.excerpt ) + '</p>'
									: '';
							};

							const imageHtml = ( style ) => {
								return showFeaturedImage
									? '<div style="aspect-ratio:' + imageAspectRatio + ';background:#e5e7eb;border-radius:4px;' + style + '"></div>'
									: '';
							};

							const safeBlockId = block.id.replace( /[^a-zA-Z0-9_-]/g, '' );
							const gridId = 've-latest-posts-' + safeBlockId;

							let itemsHtml = '';

							if ( 'list' === displayTemplate ) {
								posts.forEach( ( post ) => {
									itemsHtml += '<li style="display:flex;gap:1rem;align-items:flex-start;">'
										+ imageHtml( 'width:150px;min-width:150px;' )
										+ '<div>'
										+ '<span style="font-weight:600;font-size:1.1em;">' + veEscapeHtml( post.title ) + '</span>'
										+ metaHtml( post )
										+ excerptHtml( post )
										+ '</div></li>';
								} );
								return '<div class="ve-block ve-block-latest-posts ve-block-editing ve-block-dynamic-preview">'
									+ '<nav aria-label="' + {{ Js::from( __( 'visual-editor::ve.latest_posts' ) ) }} + '">'
									+ '<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:' + gap + ';">'
									+ itemsHtml + '</ul></nav></div>';
							}

							if ( 'grid' === displayTemplate || 'cards' === displayTemplate ) {
								posts.forEach( ( post ) => {
									const cardStyle = 'cards' === displayTemplate ? 'border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;' : '';
									const bodyPad   = 'cards' === displayTemplate ? 'padding:1rem;' : '';
									const imgMb     = 'grid' === displayTemplate ? 'margin-bottom:0.75rem;' : '';

									itemsHtml += '<article style="' + cardStyle + '">'
										+ imageHtml( imgMb )
										+ '<div style="' + bodyPad + '">'
										+ '<span style="font-weight:600;display:block;">' + veEscapeHtml( post.title ) + '</span>'
										+ metaHtml( post )
										+ excerptHtml( post )
										+ '</div></article>';
								} );

								const responsiveCss = '<style>'
									+ '#' + gridId + '{display:grid;grid-template-columns:repeat(' + desktopCols + ',1fr);gap:' + gap + ';}'
									+ '@media(max-width:1024px){#' + gridId + '{grid-template-columns:repeat(' + tabletCols + ',1fr);}}'
									+ '@media(max-width:640px){#' + gridId + '{grid-template-columns:repeat(' + mobileCols + ',1fr);}}'
									+ '</style>';

								return '<div class="ve-block ve-block-latest-posts ve-block-editing ve-block-dynamic-preview">'
									+ '<nav aria-label="' + {{ Js::from( __( 'visual-editor::ve.latest_posts' ) ) }} + '">'
									+ responsiveCss
									+ '<div id="' + gridId + '">'
									+ itemsHtml + '</div></nav></div>';
							}

							return '<div class="ve-block ve-block-latest-posts ve-block-editing ve-block-dynamic-preview">'
								+ '<p style="color:#9ca3af;text-align:center;padding:2rem;">' + {{ Js::from( __( 'visual-editor::ve.latest_posts' ) ) }} + '</p></div>';
						},
					} );

					br.register( 'table-of-contents', {
						render( block ) {
							const headingLevels = block.attributes?.headingLevels || [ 2, 3 ];
							const listStyle     = block.attributes?.listStyle || 'numbered';
							const hierarchical  = block.attributes?.hierarchical !== false;
							const tocTitle      = block.attributes?.title ?? {{ Js::from( __( 'visual-editor::ve.table_of_contents' ) ) }};
							const collapsible   = block.attributes?.collapsible === true;

							const editorBlocks = Alpine.store( 'editor' )?.blocks || [];

							const collectHeadings = ( blocks ) => {
								let headings = [];
								blocks.forEach( ( b ) => {
									if ( 'heading' === b.type ) {
										const levelStr = b.attributes?.level || 'h2';
										const level    = parseInt( levelStr.replace( 'h', '' ), 10 ) || 2;
										const text     = b.attributes?.text || '';
										if ( text && headingLevels.includes( level ) ) {
											const id = text.toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' );
											headings.push( { level, text, id } );
										}
									}
									if ( b.innerBlocks && b.innerBlocks.length > 0 ) {
										headings = headings.concat( collectHeadings( b.innerBlocks ) );
									}
								} );
								return headings;
							};

							let headings = collectHeadings( editorBlocks );

							if ( 0 === headings.length ) {
								headings = [
									{ level: 2, text: {{ Js::from( __( 'visual-editor::ve.sample_heading_introduction' ) ) }}, id: 'introduction' },
									{ level: 3, text: {{ Js::from( __( 'visual-editor::ve.sample_heading_overview' ) ) }}, id: 'overview' },
									{ level: 3, text: {{ Js::from( __( 'visual-editor::ve.sample_heading_getting_started' ) ) }}, id: 'getting-started' },
									{ level: 2, text: {{ Js::from( __( 'visual-editor::ve.sample_heading_features' ) ) }}, id: 'features' },
									{ level: 3, text: {{ Js::from( __( 'visual-editor::ve.sample_heading_configuration' ) ) }}, id: 'configuration' },
									{ level: 2, text: {{ Js::from( __( 'visual-editor::ve.sample_heading_conclusion' ) ) }}, id: 'conclusion' },
								].filter( ( h ) => headingLevels.includes( h.level ) );
							}

							const tag       = 'numbered' === listStyle ? 'ol' : 'ul';
							const listCss   = 'plain' === listStyle ? 'list-style:none;padding-left:0;' : 'padding-left:1.5rem;';
							const minLevel  = headings.length > 0 ? Math.min( ...headings.map( ( h ) => h.level ) ) : 2;

							let listItems = '';
							headings.forEach( ( h ) => {
								const indent = hierarchical ? ( h.level - minLevel ) * 1.5 : 0;
								const ml     = indent > 0 ? 'margin-left:' + indent + 'rem;' : '';
								listItems += '<li style="' + ml + '">'
									+ '<a href="#' + h.id + '" style="color:#2563eb;text-decoration:none;">' + veEscapeHtml( h.text ) + '</a>'
									+ '</li>';
							} );

							let titleHtml = '';
							if ( tocTitle ) {
								titleHtml = collapsible
									? '<summary style="font-weight:600;font-size:1.1em;cursor:pointer;margin-bottom:0.75rem;">' + veEscapeHtml( tocTitle ) + '</summary>'
									: '<h2 style="font-weight:600;font-size:1.1em;margin:0 0 0.75rem;">' + veEscapeHtml( tocTitle ) + '</h2>';
							}

							const innerHtml = titleHtml + '<' + tag + ' style="' + listCss + '">' + listItems + '</' + tag + '>';

							const wrapStart = collapsible ? '<details open>' : '';
							const wrapEnd   = collapsible ? '</details>' : '';

							return '<div class="ve-block ve-block-table-of-contents ve-block-editing ve-block-dynamic-preview">'
								+ '<nav aria-label="' + {{ Js::from( __( 'visual-editor::ve.table_of_contents' ) ) }} + '" style="border:1px solid #e5e7eb;border-radius:8px;padding:1.25rem;">'
								+ wrapStart + innerHtml + wrapEnd
								+ '</nav></div>';
						},
					} );

					br.register( 'search', {
						render( block ) {
							const placeholder    = veEscapeHtml( block.attributes?.placeholder || {{ Js::from( __( 'visual-editor::ve.search_placeholder' ) ) }} );
							const buttonText     = veEscapeHtml( block.attributes?.buttonText || {{ Js::from( __( 'visual-editor::ve.search' ) ) }} );
							const buttonPosition = block.attributes?.buttonPosition || 'outside';
							const showLabel      = block.attributes?.showLabel !== false;
							const label          = veEscapeHtml( block.attributes?.label || {{ Js::from( __( 'visual-editor::ve.search' ) ) }} );
							const displayStyle   = block.attributes?.displayStyle || 'inline';
							const isInline       = 'inline' === displayStyle;
							const hasButton      = 'none' !== buttonPosition;
							const isInside       = 'inside' === buttonPosition;

							let labelHtml = '';
							if ( showLabel ) {
								labelHtml = '<label style="display:block;font-weight:500;margin-bottom:0.5rem;font-size:0.9em;">' + label + '</label>';
							}

							const inputRadius = isInside && hasButton
								? '6px'
								: ( isInline && hasButton ? '6px 0 0 6px' : '6px' );
							const inputHtml = '<input type="search" placeholder="' + placeholder + '" disabled'
								+ ' style="width:100%;padding:0.625rem 0.875rem;border:1px solid #d1d5db;border-radius:' + inputRadius + ';font-size:0.95em;background:#fff;color:#374151;outline:none;box-sizing:border-box;"'
								+ ( ! showLabel ? ' aria-label="' + label + '"' : '' )
								+ ' />';

							let insideBtnHtml = '';
							if ( isInside && hasButton ) {
								insideBtnHtml = '<button type="button" disabled'
									+ ' style="position:absolute;right:4px;top:50%;transform:translateY(-50%);padding:0.375rem 0.75rem;background:#2563eb;color:#fff;border:none;border-radius:4px;font-size:0.85em;cursor:default;"'
									+ '>' + buttonText + '</button>';
							}

							let outsideBtnHtml = '';
							if ( ! isInside && hasButton ) {
								const btnRadius = isInline ? '0 6px 6px 0' : '6px';
								outsideBtnHtml = '<button type="button" disabled'
									+ ' style="padding:0.625rem 1.25rem;background:#2563eb;color:#fff;border:none;border-radius:' + btnRadius + ';font-size:0.95em;cursor:default;white-space:nowrap;"'
									+ '>' + buttonText + '</button>';
							}

							const wrapperStyle = isInline
								? 'display:flex;align-items:stretch;'
								: 'display:flex;flex-direction:column;gap:0.5rem;';

							return '<div class="ve-block ve-block-search ve-block-editing ve-block-dynamic-preview">'
								+ '<div role="search" aria-label="' + label + '" style="max-width:600px;">'
								+ labelHtml
								+ '<div style="' + wrapperStyle + '">'
								+ '<div style="position:relative;flex:1;">' + inputHtml + insideBtnHtml + '</div>'
								+ outsideBtnHtml
								+ '</div></div></div>';
						},
					} );

					@php
						$veFormData = [];
						if ( class_exists( \ArtisanPackUI\Forms\Models\Form::class ) ) {
							try {
								$veForms = \ArtisanPackUI\Forms\Models\Form::with( 'fields' )
									->orderBy( 'name' )
									->get();
								foreach ( $veForms as $veForm ) {
									$veFormData[ (string) $veForm->id ] = [
										'name'             => $veForm->name,
										'submitButtonText' => $veForm->submit_button_text ?: __( 'visual-editor::ve.form_submit_default' ),
										'fields'           => $veForm->fields->sortBy( 'sort_order' )->map( fn ( $f ) => [
											'type'        => $f->type,
											'label'       => $f->label,
											'placeholder' => $f->placeholder ?? '',
											'helpText'    => $f->help_text ?? '',
											'isRequired'  => (bool) $f->is_required,
											'width'       => $f->width ?? 'full',
											'options'     => $f->options ?? [],
											'isLayout'    => $f->isLayoutField(),
										] )->values()->all(),
									];
								}
							} catch ( \Throwable $e ) {
								// Table may not exist.
							}
						}
					@endphp
					br.register( 'form', {
						render( block ) {
							const formId       = block.attributes?.formId || '';
							const displayStyle = block.attributes?.displayStyle || 'embedded';
							const showLabels   = block.attributes?.showLabels !== false;
							const layout       = block.attributes?.layout || 'stacked';
							const columns      = Math.max( 1, Math.min( 4, parseInt( block.attributes?.columns ) || 2 ) );
							const rawSpacing   = block.attributes?.fieldSpacing || '1rem';
							const fieldSpacing = /^\d+(\.\d+)?\s*(px|rem|em|%|vh|vw)$/.test( rawSpacing ) ? rawSpacing : '1rem';
							const blockId      = block.id;

							const allForms    = {{ Js::from( $veFormData ) }};
							const formData    = formId ? allForms[ String( formId ) ] : null;

							if ( ! formId || ! formData ) {
								let optionsHtml = '<option value="">' + {{ Js::from( __( 'visual-editor::ve.form_select_a_form' ) ) }} + '</option>';
								Object.keys( allForms ).forEach( ( key ) => {
									optionsHtml += '<option value="' + veEscapeHtml( key ) + '">' + veEscapeHtml( allForms[ key ].name ) + '</option>';
								} );

								return '<div class="ve-block ve-block-form ve-block-editing ve-block-dynamic-preview">'
									+ '<div style="padding:2rem;text-align:center;background:#f3f4f6;border:1px dashed #9ca3af;border-radius:8px;color:#6b7280;">'
									+ '<svg style="width:2rem;height:2rem;margin:0 auto 0.5rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">'
									+ '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />'
									+ '</svg>'
									+ '<p style="font-weight:600;margin:0 0 0.75rem;">' + {{ Js::from( __( 'visual-editor::ve.form_select_form' ) ) }} + '</p>'
									+ '<div style="display:flex;gap:0.5rem;justify-content:center;align-items:stretch;max-width:400px;margin:0 auto;">'
									+ '<select data-ve-form-select style="flex:1;padding:0.5rem 0.75rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.9em;background:#fff;color:#374151;box-sizing:border-box;">'
									+ optionsHtml
									+ '</select>'
									+ '<button type="button" data-ve-form-select-btn="' + veEscapeHtml( blockId ) + '"'
									+ ' style="padding:0.5rem 1rem;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:0.9em;font-weight:500;cursor:pointer;white-space:nowrap;">'
									+ {{ Js::from( __( 'visual-editor::ve.form_select_button' ) ) }}
									+ '</button>'
									+ '</div></div></div>';
							}

							// Build static form preview.
							const isGrid   = 'grid' === layout;
							const isInline = 'inline' === layout;
							const isModal  = 'modal' === displayStyle;
							const isSlide  = 'slide-over' === displayStyle;

							const submitText  = block.attributes?.submitButtonText || formData.submitButtonText;
							const btnColor    = block.attributes?.submitButtonColor || 'primary';
							const btnSize     = block.attributes?.submitButtonSize || 'md';

							const colorMap = {
								primary: 'background:#2563eb;color:#fff;',
								secondary: 'background:#6b7280;color:#fff;',
								accent: 'background:#8b5cf6;color:#fff;',
								success: 'background:#16a34a;color:#fff;',
								warning: 'background:#d97706;color:#fff;',
								error: 'background:#dc2626;color:#fff;',
								info: 'background:#0891b2;color:#fff;',
							};
							const sizeMap = {
								sm: 'padding:0.375rem 0.75rem;font-size:0.85em;',
								md: 'padding:0.625rem 1.25rem;font-size:0.95em;',
								lg: 'padding:0.75rem 1.5rem;font-size:1.05em;',
							};
							const btnStyle = ( colorMap[ btnColor ] || colorMap.primary )
								+ ( sizeMap[ btnSize ] || sizeMap.md )
								+ 'border:none;border-radius:6px;cursor:default;font-weight:500;';

							let containerStyle;
							if ( isGrid ) {
								containerStyle = 'display:grid;grid-template-columns:repeat(' + columns + ',1fr);gap:' + fieldSpacing + ';';
							} else if ( isInline ) {
								containerStyle = 'display:flex;flex-wrap:wrap;gap:' + fieldSpacing + ';align-items:flex-end;';
							} else {
								containerStyle = 'display:flex;flex-direction:column;gap:' + fieldSpacing + ';';
							}

							let fieldsHtml = '';
							const inputStyle = 'width:100%;padding:0.5rem 0.75rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.95em;background:#fff;color:#374151;box-sizing:border-box;';

							formData.fields.forEach( ( field ) => {
								if ( field.isLayout ) {
									const spanStyle = isGrid ? 'grid-column:1/-1;' : ( isInline ? 'flex-basis:100%;' : '' );
									if ( 'heading' === field.type ) {
										fieldsHtml += '<div style="' + spanStyle + '"><h3 style="font-weight:600;font-size:1.1em;margin:0;">' + veEscapeHtml( field.label ) + '</h3></div>';
									} else if ( 'divider' === field.type ) {
										fieldsHtml += '<div style="' + spanStyle + '"><hr style="border:none;border-top:1px solid #e5e7eb;margin:0.5rem 0;" /></div>';
									} else if ( 'paragraph' === field.type ) {
										fieldsHtml += '<div style="' + spanStyle + '"><p style="font-size:0.9em;color:#6b7280;margin:0;">' + veEscapeHtml( field.helpText || field.label ) + '</p></div>';
									}
									return;
								}

								let widthStyle = '';
								if ( isGrid ) {
									widthStyle = 'full' === field.width ? 'grid-column:1/-1;' : ( 'half' === field.width ? 'grid-column:span ' + Math.max( 1, Math.round( columns * 0.5 ) ) + ';' : ( 'third' === field.width ? 'grid-column:span ' + Math.max( 1, Math.round( columns / 3 ) ) + ';' : ( 'two-thirds' === field.width ? 'grid-column:span ' + Math.max( 1, Math.round( columns * 2 / 3 ) ) + ';' : 'grid-column:1/-1;' ) ) );
								} else if ( isInline ) {
									widthStyle = 'full' === field.width ? 'flex:1 1 100%;' : ( 'half' === field.width ? 'flex:0 0 calc(50% - ' + fieldSpacing + ');' : ( 'third' === field.width ? 'flex:0 0 calc(33.333% - ' + fieldSpacing + ');' : ( 'two-thirds' === field.width ? 'flex:0 0 calc(66.666% - ' + fieldSpacing + ');' : 'flex:1 1 100%;' ) ) );
								}

								let labelHtml = '';
								if ( showLabels && field.label ) {
									labelHtml = '<label style="display:block;font-weight:500;margin-bottom:0.375rem;font-size:0.9em;">'
										+ veEscapeHtml( field.label )
										+ ( field.isRequired ? ' <span style="color:#dc2626;">*</span>' : '' )
										+ '</label>';
								}

								let inputHtml = '';
								const ph = veEscapeHtml( field.placeholder );

								if ( [ 'text', 'email', 'phone', 'number', 'url', 'date', 'time' ].indexOf( field.type ) !== -1 ) {
									inputHtml = '<input type="' + field.type + '" placeholder="' + ph + '" disabled style="' + inputStyle + '" />';
								} else if ( 'textarea' === field.type ) {
									inputHtml = '<textarea rows="3" placeholder="' + ph + '" disabled style="' + inputStyle + 'resize:vertical;"></textarea>';
								} else if ( 'select' === field.type || 'select_multiple' === field.type ) {
									let opts = '<option>' + ( ph || {{ Js::from( __( 'visual-editor::ve.form_select_option' ) ) }} ) + '</option>';
									( field.options || [] ).forEach( ( o ) => { opts += '<option>' + veEscapeHtml( o.label || o ) + '</option>'; } );
									const multiAttr = 'select_multiple' === field.type ? ' multiple' : '';
									inputHtml = '<select disabled' + multiAttr + ' style="' + inputStyle + '">' + opts + '</select>';
								} else if ( 'checkbox' === field.type || 'toggle' === field.type ) {
									inputHtml = '<div style="display:flex;align-items:center;gap:0.5rem;">'
										+ '<input type="checkbox" disabled style="width:1rem;height:1rem;" />'
										+ ( field.helpText ? '<span style="font-size:0.9em;color:#374151;">' + veEscapeHtml( field.helpText ) + '</span>' : '' )
										+ '</div>';
								} else if ( 'checkbox_group' === field.type ) {
									let cbs = '';
									( field.options || [] ).forEach( ( o ) => {
										cbs += '<div style="display:flex;align-items:center;gap:0.5rem;">'
											+ '<input type="checkbox" disabled style="width:1rem;height:1rem;" />'
											+ '<span style="font-size:0.9em;">' + veEscapeHtml( o.label || o ) + '</span></div>';
									} );
									inputHtml = '<div style="display:flex;flex-direction:column;gap:0.375rem;">' + cbs + '</div>';
								} else if ( 'radio' === field.type ) {
									let rbs = '';
									( field.options || [] ).forEach( ( o ) => {
										rbs += '<div style="display:flex;align-items:center;gap:0.5rem;">'
											+ '<input type="radio" disabled style="width:1rem;height:1rem;" />'
											+ '<span style="font-size:0.9em;">' + veEscapeHtml( o.label || o ) + '</span></div>';
									} );
									inputHtml = '<div style="display:flex;flex-direction:column;gap:0.375rem;">' + rbs + '</div>';
								} else if ( 'file' === field.type ) {
									inputHtml = '<div style="padding:1rem;border:1px dashed #d1d5db;border-radius:6px;text-align:center;color:#9ca3af;font-size:0.9em;">'
										+ {{ Js::from( __( 'visual-editor::ve.form_file_upload_placeholder' ) ) }} + '</div>';
								} else {
									inputHtml = '<input type="text" placeholder="' + ph + '" disabled style="' + inputStyle + '" />';
								}

								let helpHtml = '';
								if ( field.helpText && [ 'checkbox', 'toggle' ].indexOf( field.type ) === -1 ) {
									helpHtml = '<p style="font-size:0.8em;color:#9ca3af;margin:0.25rem 0 0;">' + veEscapeHtml( field.helpText ) + '</p>';
								}

								fieldsHtml += '<div style="' + widthStyle + '">' + labelHtml + inputHtml + helpHtml + '</div>';
							} );

							let html = '';

							if ( isModal || isSlide ) {
								const triggerLabel = isModal
									? {{ Js::from( __( 'visual-editor::ve.form_modal_trigger_preview' ) ) }}
									: {{ Js::from( __( 'visual-editor::ve.form_slide_over_trigger_preview' ) ) }};
								html += '<div style="text-align:center;padding:1.5rem;">'
									+ '<p style="font-size:0.85em;color:#6b7280;margin:0 0 0.75rem;">' + triggerLabel + '</p>'
									+ '<button type="button" disabled style="' + btnStyle + '">' + veEscapeHtml( submitText ) + '</button>'
									+ '</div>';
							}

							const wrapStyle = ( isModal || isSlide )
								? 'border:1px dashed #d1d5db;border-radius:8px;padding:1rem;margin-top:0.5rem;'
								: '';

							let contentLabel = '';
							if ( isModal || isSlide ) {
								const clabel = isModal
									? {{ Js::from( __( 'visual-editor::ve.form_modal_content_label' ) ) }}
									: {{ Js::from( __( 'visual-editor::ve.form_slide_over_content_label' ) ) }};
								contentLabel = '<p style="font-size:0.75em;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.75rem;">' + clabel + '</p>';
							}

							html += '<div style="' + wrapStyle + '">'
								+ contentLabel
								+ '<div style="' + containerStyle + '">' + fieldsHtml + '</div>'
								+ '<div style="margin-top:' + fieldSpacing + ';">'
								+ '<button type="button" disabled style="' + btnStyle + '">' + veEscapeHtml( submitText ) + '</button>'
								+ '</div></div>';

							return '<div class="ve-block ve-block-form ve-block-editing ve-block-dynamic-preview">' + html + '</div>';
						},
					} );

					br.register( 'custom-html', {
						render( block ) {
							const htmlContent = block.attributes?.content || '';
							const preview     = block.attributes?.preview || false;
							const sanitize    = block.attributes?.sanitize !== false;
							const blockId     = block.id;

							let warning = '';
							if ( ! sanitize ) {
								warning = '<div class="ve-custom-html-warning flex items-center gap-2 rounded-t-lg bg-warning/10 border border-warning/30 px-3 py-2" role="alert">'
									+ '<svg class="w-4 h-4 text-warning shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>'
									+ '<span class="text-xs text-warning">' + {{ Js::from( __( 'visual-editor::ve.custom_html_unsanitized_warning' ) ) }} + '</span></div>';
							}

							const borderTopFix = ! sanitize ? ' rounded-t-none border-t-0' : '';

							if ( preview ) {
								const escapedHtml = htmlContent.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
								return '<div class="ve-block ve-block-custom-html ve-block-editing">'
									+ warning
									+ '<div class="ve-custom-html-preview rounded-lg border border-base-300 overflow-hidden' + borderTopFix + '">'
									+ '<div class="flex items-center justify-between bg-base-200 px-3 py-1 border-b border-base-300"><span class="text-xs font-medium text-base-content/60">' + {{ Js::from( __( 'visual-editor::ve.custom_html_preview' ) ) }} + '</span></div>'
									+ '<iframe srcdoc="' + escapedHtml + '" sandbox="allow-scripts" class="ve-custom-html-iframe" title="' + {{ Js::from( __( 'visual-editor::ve.custom_html_preview_title' ) ) }} + '" style="width: 100%; min-height: 150px; border: 0;" loading="lazy"></iframe>'
									+ '</div></div>';
							}

							const escapedForAttr = htmlContent.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
							return '<div class="ve-block ve-block-custom-html ve-block-editing">'
								+ warning
								+ '<div class="ve-custom-html-editor rounded-lg border border-base-300 overflow-hidden' + borderTopFix + '">'
								+ '<div class="flex items-center justify-between bg-base-200 px-3 py-1 border-b border-base-300"><span class="text-xs font-medium text-base-content/60">HTML</span></div>'
								+ '<textarea class="ve-custom-html-textarea w-full font-mono text-sm p-3 bg-base-100 min-h-[150px] resize-y focus:outline-none" data-ve-block-id="' + blockId + '"'
								+ ' aria-label="' + {{ Js::from( __( 'visual-editor::ve.custom_html_editor_label' ) ) }} + '"'
								+ ' placeholder="' + {{ Js::from( __( 'visual-editor::ve.custom_html_placeholder' ) ) }} + '"'
								+ ' spellcheck="false"'
								+ '>' + escapedForAttr + '</textarea></div></div>';
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
