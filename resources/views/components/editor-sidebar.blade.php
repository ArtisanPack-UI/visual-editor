{{--
 * Editor Sidebar Component
 *
 * The right-hand sidebar shell that hosts the block inserter panel
 * and block inspector panels with block/document tabs.
 * The block panel includes sub-tabs for Settings, Styles, and Advanced.
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
		activeBlockSubTab: {{ Js::from( $activeBlockSubTab ) }},
		_autoSelectSubTab() {
			// Wait two ticks: one for Alpine to update the x-show blocks
			// based on the new block type, another for DOM to settle.
			this.$nextTick( () => { this.$nextTick( () => {
				const settingsPanel = document.getElementById( '{{ $uuid }}-settings-subpanel' );
				if ( ! settingsPanel ) return;

				// Temporarily reveal the panel (without transition) to inspect content.
				const wasHidden = settingsPanel.style.display === 'none';
				if ( wasHidden ) settingsPanel.style.display = '';

				// Look for inspector fields inside the currently visible block type div.
				const hasFields = settingsPanel.querySelector( '.ve-inspector-field' );

				if ( wasHidden ) settingsPanel.style.display = 'none';

				this.activeBlockSubTab = hasFields ? 'settings' : 'styles';
			} ); } );
		},
	}"
	x-effect="if ( $store.selection?.focused ) { _autoSelectSubTab() }"
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
		class="flex-1 flex flex-col overflow-hidden"
		role="tabpanel"
		tabindex="0"
		aria-labelledby="{{ $uuid }}-block-tab"
	>
		{{-- Block sub-tab switcher: Settings / Styles --}}
		<div class="flex border-b border-base-300" role="tablist" aria-label="{{ __( 'visual-editor::ve.block_settings' ) }}">
			<button
				type="button"
				id="{{ $uuid }}-settings-subtab"
				class="flex-1 px-3 py-1.5 text-xs font-medium text-center transition-colors"
				:class="'settings' === activeBlockSubTab ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
				x-on:click="activeBlockSubTab = 'settings'; $nextTick( () => $el.focus() )"
				x-on:keydown.arrow-right.prevent="activeBlockSubTab = 'styles'; $nextTick( () => document.getElementById( '{{ $uuid }}-styles-subtab' ).focus() )"
				x-on:keydown.arrow-left.prevent="activeBlockSubTab = 'styles'; $nextTick( () => document.getElementById( '{{ $uuid }}-styles-subtab' ).focus() )"
				x-on:keydown.home.prevent="activeBlockSubTab = 'settings'; $nextTick( () => $el.focus() )"
				x-on:keydown.end.prevent="activeBlockSubTab = 'styles'; $nextTick( () => document.getElementById( '{{ $uuid }}-styles-subtab' ).focus() )"
				role="tab"
				:aria-selected="'settings' === activeBlockSubTab"
				:tabindex="'settings' === activeBlockSubTab ? 0 : -1"
				aria-controls="{{ $uuid }}-settings-subpanel"
			>
				{{ __( 'visual-editor::ve.settings_tab' ) }}
			</button>
			<button
				type="button"
				id="{{ $uuid }}-styles-subtab"
				class="flex-1 px-3 py-1.5 text-xs font-medium text-center transition-colors"
				:class="'styles' === activeBlockSubTab ? 'text-primary border-b-2 border-primary' : 'text-base-content/60 hover:text-base-content'"
				x-on:click="activeBlockSubTab = 'styles'; $nextTick( () => $el.focus() )"
				x-on:keydown.arrow-right.prevent="activeBlockSubTab = 'settings'; $nextTick( () => document.getElementById( '{{ $uuid }}-settings-subtab' ).focus() )"
				x-on:keydown.arrow-left.prevent="activeBlockSubTab = 'settings'; $nextTick( () => document.getElementById( '{{ $uuid }}-settings-subtab' ).focus() )"
				x-on:keydown.home.prevent="activeBlockSubTab = 'settings'; $nextTick( () => document.getElementById( '{{ $uuid }}-settings-subtab' ).focus() )"
				x-on:keydown.end.prevent="activeBlockSubTab = 'styles'; $nextTick( () => $el.focus() )"
				role="tab"
				:aria-selected="'styles' === activeBlockSubTab"
				:tabindex="'styles' === activeBlockSubTab ? 0 : -1"
				aria-controls="{{ $uuid }}-styles-subpanel"
			>
				{{ __( 'visual-editor::ve.styles_tab' ) }}
			</button>
		</div>

		{{-- Settings sub-panel --}}
		<div
			id="{{ $uuid }}-settings-subpanel"
			x-show="'settings' === activeBlockSubTab"
			class="flex-1 overflow-y-auto p-2"
			role="tabpanel"
			tabindex="0"
			aria-labelledby="{{ $uuid }}-settings-subtab"
		>
			{{ $settingsPanel ?? $blockPanel ?? '' }}
		</div>

		{{-- Styles sub-panel --}}
		<div
			id="{{ $uuid }}-styles-subpanel"
			x-show="'styles' === activeBlockSubTab"
			class="flex-1 overflow-y-auto p-2"
			role="tabpanel"
			tabindex="0"
			aria-labelledby="{{ $uuid }}-styles-subtab"
		>
			{{ $stylesPanel ?? '' }}
		</div>
	</div>

	{{-- Document settings panel --}}
	<div
		id="{{ $uuid }}-document-panel"
		x-show="'document' === activeTab"
		class="flex-1 overflow-y-auto p-2"
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
