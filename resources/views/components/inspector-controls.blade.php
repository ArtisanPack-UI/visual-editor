@php
	$block          = $block();
	$supportsPanels = $supportsPanels();
	$contentSchema  = $block ? $block->getContentSchema() : [];
	$styleSchema    = $block ? $block->getStyleSchema() : [];
	$advancedSchema = $block ? $block->getAdvancedSchema() : [];
@endphp

<div
	id="{{ $uuid }}"
	class="ve-inspector-controls"
	x-data="{
		activeTab: 'settings',
		setTab( tab ) {
			this.activeTab = tab;
		}
	}"
>
	@if ( $block )
		{{-- Tab Buttons --}}
		<div class="flex border-b border-base-300" role="tablist">
			<button
				type="button"
				role="tab"
				class="flex-1 px-3 py-2 text-sm font-medium transition-colors"
				:class="activeTab === 'settings'
					? 'border-b-2 border-primary text-primary'
					: 'text-base-content/60 hover:text-base-content'"
				:aria-selected="activeTab === 'settings'"
				x-on:click="setTab( 'settings' )"
			>
				{{ __( 'visual-editor::ve.settings_tab' ) }}
			</button>
			<button
				type="button"
				role="tab"
				class="flex-1 px-3 py-2 text-sm font-medium transition-colors"
				:class="activeTab === 'styles'
					? 'border-b-2 border-primary text-primary'
					: 'text-base-content/60 hover:text-base-content'"
				:aria-selected="activeTab === 'styles'"
				x-on:click="setTab( 'styles' )"
			>
				{{ __( 'visual-editor::ve.styles_tab' ) }}
			</button>
			<button
				type="button"
				role="tab"
				class="flex-1 px-3 py-2 text-sm font-medium transition-colors"
				:class="activeTab === 'advanced'
					? 'border-b-2 border-primary text-primary'
					: 'text-base-content/60 hover:text-base-content'"
				:aria-selected="activeTab === 'advanced'"
				x-on:click="setTab( 'advanced' )"
			>
				{{ __( 'visual-editor::ve.advanced_tab' ) }}
			</button>
		</div>

		{{-- Settings Tab --}}
		<div
			x-show="activeTab === 'settings'"
			role="tabpanel"
			class="space-y-4 p-4"
		>
			{{-- Custom inspector sections targeting settings --}}
			@if ( $block->hasCustomInspector() )
				{!! $block->renderInspector( [ 'content' => [], 'styles' => [] ] ) !!}
			@endif

			{{-- Auto-generated content schema fields --}}
			@foreach ( $contentSchema as $fieldName => $fieldSchema )
				<x-ve-inspector-field
					:name="$fieldName"
					:schema="$fieldSchema"
					:value="null"
					:block-id="$blockId"
				/>
			@endforeach
		</div>

		{{-- Styles Tab --}}
		<div
			x-show="activeTab === 'styles'"
			role="tabpanel"
			class="space-y-4 p-4"
		>
			{{-- Auto-generated supports panels --}}
			@foreach ( $supportsPanels as $panel )
				<x-ve-panel-body :title="$panel['label']">
					@foreach ( $panel['controls'] as $control )
						@if ( 'shadow' === $control['type'] )
							<x-ve-shadow-control :block-id="$blockId" />
						@elseif ( 'border' === $control['type'] )
							<x-ve-inspector-field
								:name="$control['field']"
								:schema="[ 'type' => 'border', 'label' => $control['label'] ?? '' ]"
								:value="null"
								:block-id="$blockId"
							/>
						@else
							<x-ve-inspector-field
								:name="$control['field']"
								:schema="[ 'type' => $control['type'], 'label' => $control['label'] ?? '' ]"
								:value="null"
								:block-id="$blockId"
							/>
						@endif
					@endforeach
				</x-ve-panel-body>
			@endforeach

			{{-- Auto-generated style schema fields --}}
			@foreach ( $styleSchema as $fieldName => $fieldSchema )
				<x-ve-inspector-field
					:name="$fieldName"
					:schema="$fieldSchema"
					:value="null"
					:block-id="$blockId"
				/>
			@endforeach
		</div>

		{{-- Advanced Tab --}}
		<div
			x-show="activeTab === 'advanced'"
			role="tabpanel"
			class="space-y-4 p-4"
		>
			@foreach ( $advancedSchema as $fieldName => $fieldSchema )
				<x-ve-inspector-field
					:name="$fieldName"
					:schema="$fieldSchema"
					:value="null"
					:block-id="$blockId"
				/>
			@endforeach
		</div>
	@else
		<div class="p-4 text-center text-sm text-base-content/60">
			{{ __( 'visual-editor::ve.block_settings' ) }}
		</div>
	@endif
</div>
