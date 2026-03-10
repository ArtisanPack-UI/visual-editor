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

								let attrs = 'href="' + safe + '"';
								if ( detail.newTab ) attrs += ' target="_blank"';
								if ( rel ) attrs += ' rel="' + rel + '"';

								document.execCommand(
									'insertHTML', false,
									'<a ' + attrs + '>' + text + '</a>',
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
							const color        = block.attributes?.color || '';
							const bgColor      = block.attributes?.backgroundColor || '';
							const size         = block.attributes?.size || 'md';
							const variant      = block.attributes?.variant || 'filled';
							const borderRadius = block.attributes?.borderRadius || '';
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
