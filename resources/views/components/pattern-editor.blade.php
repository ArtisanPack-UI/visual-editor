{{--
 * Pattern Editor Component
 *
 * A full block editing experience for patterns. Mirrors the Template
 * Part Editor layout with pattern-specific features: a Pattern tab in
 * the right sidebar with name, slug, category, description, keywords,
 * status, and synced/standard toggle.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

{{-- Push editor styles (same CSS as the main editor) --}}
@once
@push( 'styles' )
<style>
	/* Contenteditable placeholder — show when block is empty and not focused */
	[data-placeholder].ve-is-empty:not(:focus)::before {
		content: attr(data-placeholder);
		opacity: 0.4;
		font-style: italic;
		pointer-events: none;
	}

	/* Contenteditable placeholder for nested elements */
	[contenteditable] > [data-placeholder].ve-is-empty:not(:focus)::before {
		content: attr(data-placeholder);
		opacity: 0.4;
		font-style: italic;
		pointer-events: none;
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
	document-status="draft"
	:show-sidebar="$showSidebar"
	:mode="$mode"
	:default-inner-blocks-map="$defaultInnerBlocksMap"
	:initial-meta="$initialMeta"
/>

{{-- Inject pattern settings into editor store --}}
@if ( ! empty( $patternSettings ) )
<div
	x-data="{
		init() {
			const settings = {{ Js::from( $patternSettings ) }};
			let attempts = 0;
			const maxAttempts = 50;
			const assign = () => {
				const store = Alpine.store( 'editor' );
				if ( store ) {
					store.patternSettings = settings;
				} else if ( ++attempts < maxAttempts ) {
					setTimeout( assign, 10 );
				}
			};
			assign();
		},
	}"
	class="hidden"
	aria-hidden="true"
></div>
@endif
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
					<a
						href="{{ route( 'visual-editor.patterns' ) }}"
						class="inline-flex items-center gap-1 text-sm text-base-content/60 hover:text-primary transition-colors"
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
						{{ __( 'visual-editor::ve.pattern_editor_back' ) }}
					</a>
					<span class="mx-1.5 text-base-content/30">/</span>
					<span class="text-sm font-medium text-base-content" x-data x-text="Alpine.store( 'editor' )?.patternSettings?.name || '{{ __( 'visual-editor::ve.pattern_editor_untitled' ) }}'"></span>
					{{-- Synced badge --}}
					<template x-if="Alpine.store( 'editor' )?.patternSettings?.isSynced">
						<span class="badge badge-xs badge-primary ml-1.5">{{ __( 'visual-editor::ve.pattern_editor_synced' ) }}</span>
					</template>
					{{ $toolbarCenter ?? '' }}
				</x-slot:center>
			</x-ve-top-toolbar>
		</x-slot:toolbar>

		{{-- ============================================================ --}}
		{{-- LEFT SIDEBAR --}}
		{{-- ============================================================ --}}
		<x-slot:leftSidebar>
			<x-ve-left-sidebar
				:custom-tabs="[
					[ 'slug' => 'blocks', 'label' => __( 'visual-editor::ve.blocks_tab' ) ],
					[ 'slug' => 'patterns', 'label' => __( 'visual-editor::ve.patterns_tab' ) ],
					[ 'slug' => 'structure', 'label' => __( 'visual-editor::ve.structure_tab' ) ],
				]"
				active-tab="blocks"
			>
				<x-slot:blocksPanel>
					<x-ve-block-inserter :blocks="$inserterBlocks" :icon-renderer="$iconRenderer" />
				</x-slot:blocksPanel>

				<x-slot:patternsPanel>
					<x-ve-pattern-browser :patterns="$patterns" />
				</x-slot:patternsPanel>

				<x-slot:structurePanel>
					<x-ve-template-structure-panel :block-names="$blockNames" />
				</x-slot:structurePanel>
			</x-ve-left-sidebar>
		</x-slot:leftSidebar>

		{{-- ============================================================ --}}
		{{-- CANVAS --}}
		{{-- ============================================================ --}}
		<x-slot:canvas>
			{{-- Block toolbar - floats above selected block --}}
			<x-ve-block-toolbar :show-move-controls="false">
				{{-- Parent block navigation --}}
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

				{{-- Drag handle --}}
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square cursor-grab active:cursor-grabbing"
					aria-label="{{ __( 'visual-editor::ve.drag_to_reorder' ) }}"
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
					aria-label="{{ __( 'visual-editor::ve.move_up' ) }}"
				>
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
					</svg>
				</button>
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-square"
					x-on:click="if ( Alpine.store( 'editor' ) && focusedBlockId ) { Alpine.store( 'editor' ).moveBlockDown( focusedBlockId ); }"
					aria-label="{{ __( 'visual-editor::ve.move_down' ) }}"
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
						aria-label="{{ __( 'visual-editor::ve.transform_block' ) }}"
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

				{{-- Block alignment, text alignment, and text formatting controls --}}
				@include( 'visual-editor::components._block-toolbar-controls' )

				{{-- Custom toolbar HTML from blocks --}}
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

			{{-- Slash command inserter --}}
			<x-ve-slash-command-inserter :blocks="$inserterBlocks" />

			{{-- Block renderer registry Alpine store --}}
			@include( 'visual-editor::components._block-renderers-script' )

			{{-- Dynamic canvas --}}
			@include( 'visual-editor::components._editor-canvas-content' )
		</x-slot:canvas>

		{{-- ============================================================ --}}
		{{-- RIGHT SIDEBAR (Block + Pattern tabs) --}}
		{{-- ============================================================ --}}
		<x-slot:sidebar>
			<x-ve-editor-sidebar
				:show-tabs="true"
				:second-tab-label="__( 'visual-editor::ve.pattern_editor_tab' )"
			>
				<x-slot:settingsPanel>
					@include( 'visual-editor::components._editor-inspector-settings' )
				</x-slot:settingsPanel>

				<x-slot:stylesPanel>
					@include( 'visual-editor::components._editor-inspector-styles' )
				</x-slot:stylesPanel>

				<x-slot:documentPanel>
					<div class="flex flex-col gap-6 p-2">
						{{-- Pattern Settings --}}
						@include( 'visual-editor::components._pattern-settings-panel' )

						{{ $documentPanel ?? '' }}
					</div>
				</x-slot:documentPanel>
			</x-ve-editor-sidebar>
		</x-slot:sidebar>

		<x-slot:statusbar>
			<x-ve-status-bar />
		</x-slot:statusbar>
	</x-ve-editor-layout>
</div>

@if ( function_exists( 'doAction' ) )
	@action('ap.visualEditor.patternEditor.rendered')
@endif
