{{--
 * Left Sidebar Component
 *
 * Three-tab panel for Blocks, Patterns, and Layers.
 * Visibility is controlled by the editor store's showInserter property.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$tabs = [
		[ 'slug' => 'blocks', 'label' => __( 'visual-editor::ve.blocks_tab' ) ],
		[ 'slug' => 'patterns', 'label' => __( 'visual-editor::ve.patterns_tab' ) ],
		[ 'slug' => 'layers', 'label' => __( 'visual-editor::ve.layers_tab' ) ],
	];

	$tabs = function_exists( 'applyFilters' )
		? applyFilters( 'ap.visualEditor.leftSidebar.tabs', $tabs )
		: $tabs;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		activeTab: {{ Js::from( $activeTab ) }},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col h-full bg-base-100 overflow-hidden' ] ) }}
	role="complementary"
	aria-label="{{ $label ?? __( 'visual-editor::ve.left_sidebar' ) }}"
>
	{{-- Header with tabs and close button --}}
	<div class="flex items-center border-b border-base-300">
		<div class="flex flex-1" role="tablist">
			@foreach ( $tabs as $tab )
				<button
					type="button"
					class="flex-1 px-3 py-2 text-sm font-medium text-center transition-colors"
					:class="'{{ $tab['slug'] }}' === activeTab ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
					x-on:click="activeTab = '{{ $tab['slug'] }}'"
					role="tab"
					:aria-selected="'{{ $tab['slug'] }}' === activeTab"
					aria-controls="{{ $uuid }}-{{ $tab['slug'] }}-panel"
				>
					{{ $tab['label'] }}
				</button>
			@endforeach
		</div>

		{{-- Close button --}}
		<button
			type="button"
			class="btn btn-ghost btn-sm btn-square mr-1"
			x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).showInserter = false; }"
			:aria-label="{{ Js::from( __( 'visual-editor::ve.close_sidebar' ) ) }}"
		>
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
		</button>
	</div>

	{{-- Blocks panel --}}
	<div
		id="{{ $uuid }}-blocks-panel"
		x-show="'blocks' === activeTab"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		aria-label="{{ __( 'visual-editor::ve.blocks_tab' ) }}"
	>
		{{ $blocksPanel ?? '' }}
	</div>

	{{-- Patterns panel --}}
	<div
		id="{{ $uuid }}-patterns-panel"
		x-show="'patterns' === activeTab"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		aria-label="{{ __( 'visual-editor::ve.patterns_tab' ) }}"
	>
		{{ $patternsPanel ?? '' }}
	</div>

	{{-- Layers panel --}}
	<div
		id="{{ $uuid }}-layers-panel"
		x-show="'layers' === activeTab"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		aria-label="{{ __( 'visual-editor::ve.layers_tab' ) }}"
	>
		{{ $layersPanel ?? '' }}
	</div>
</div>
