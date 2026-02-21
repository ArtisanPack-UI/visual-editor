{{--
 * Editor Sidebar Component
 *
 * The right-hand sidebar shell that hosts the block inserter panel
 * and block inspector panels with block/document tabs.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		activeTab: {{ Js::from( $activeTab ) }},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col h-full border-l border-base-300 bg-base-100 overflow-hidden' ] ) }}
	role="complementary"
	aria-label="{{ $label ?? __( 'visual-editor::ve.editor_sidebar' ) }}"
>
	{{-- Tab switcher --}}
	@if ( $showTabs )
		<div class="flex border-b border-base-300" role="tablist" aria-label="{{ __( 'visual-editor::ve.editor_sidebar' ) }}">
			<button
				type="button"
				id="{{ $uuid }}-block-tab"
				class="flex-1 px-4 py-2 text-sm font-medium text-center transition-colors"
				:class="'block' === activeTab ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
				x-on:click="activeTab = 'block'; $nextTick( () => $el.focus() )"
				x-on:keydown.arrow-right.prevent="activeTab = 'document'; $nextTick( () => document.getElementById( '{{ $uuid }}-document-tab' ).focus() )"
				x-on:keydown.arrow-left.prevent="activeTab = 'document'; $nextTick( () => document.getElementById( '{{ $uuid }}-document-tab' ).focus() )"
				x-on:keydown.home.prevent="activeTab = 'block'; $nextTick( () => $el.focus() )"
				x-on:keydown.end.prevent="activeTab = 'document'; $nextTick( () => document.getElementById( '{{ $uuid }}-document-tab' ).focus() )"
				role="tab"
				:aria-selected="'block' === activeTab"
				:tabindex="'block' === activeTab ? 0 : -1"
				aria-controls="{{ $uuid }}-block-panel"
			>
				{{ __( 'visual-editor::ve.block_tab' ) }}
			</button>
			<button
				type="button"
				id="{{ $uuid }}-document-tab"
				class="flex-1 px-4 py-2 text-sm font-medium text-center transition-colors"
				:class="'document' === activeTab ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
				x-on:click="activeTab = 'document'; $nextTick( () => $el.focus() )"
				x-on:keydown.arrow-left.prevent="activeTab = 'block'; $nextTick( () => document.getElementById( '{{ $uuid }}-block-tab' ).focus() )"
				x-on:keydown.arrow-right.prevent="activeTab = 'block'; $nextTick( () => document.getElementById( '{{ $uuid }}-block-tab' ).focus() )"
				x-on:keydown.home.prevent="activeTab = 'block'; $nextTick( () => document.getElementById( '{{ $uuid }}-block-tab' ).focus() )"
				x-on:keydown.end.prevent="activeTab = 'document'; $nextTick( () => $el.focus() )"
				role="tab"
				:aria-selected="'document' === activeTab"
				:tabindex="'document' === activeTab ? 0 : -1"
				aria-controls="{{ $uuid }}-document-panel"
			>
				{{ __( 'visual-editor::ve.document_tab' ) }}
			</button>
		</div>
	@endif

	{{-- Block settings panel --}}
	<div
		id="{{ $uuid }}-block-panel"
		x-show="'block' === activeTab"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		tabindex="0"
		aria-labelledby="{{ $uuid }}-block-tab"
	>
		{{ $blockPanel ?? '' }}
	</div>

	{{-- Document settings panel --}}
	<div
		id="{{ $uuid }}-document-panel"
		x-show="'document' === activeTab"
		class="flex-1 overflow-y-auto"
		role="tabpanel"
		tabindex="0"
		aria-labelledby="{{ $uuid }}-document-tab"
	>
		{{ $documentPanel ?? '' }}
	</div>

	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.sidebar.rendered')
	@endif
</div>
