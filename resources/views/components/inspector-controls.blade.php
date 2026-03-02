@php
	$block                 = $block();
	$supportsPanels        = $supportsPanels();
	$supportsCoveredFields = $supportsCoveredFields();
	$contentSchema         = $block ? $block->getContentSchema() : [];
	$styleSchema           = $block ? $block->getStyleSchema() : [];
	$advancedSchema        = $block ? $block->getAdvancedSchema() : [];

	// Remove style fields already rendered by supports panels to avoid duplication.
	$styleSchema = array_diff_key( $styleSchema, array_flip( $supportsCoveredFields ) );

	// Group content schema fields by their optional 'panel' key.
	$panelGroups     = [];
	$ungroupedFields = [];
	foreach ( $contentSchema as $fieldName => $fieldSchema ) {
		if ( isset( $fieldSchema['panel'] ) ) {
			$panelGroups[ $fieldSchema['panel'] ][ $fieldName ] = $fieldSchema;
		} else {
			$ungroupedFields[ $fieldName ] = $fieldSchema;
		}
	}
@endphp

@if ( $tab )
	{{-- Single-tab mode: render only the requested tab content without tab bar or padding wrapper --}}
	@if ( $block )
		@if ( 'settings' === $tab )
			<div class="space-y-3 p-1">
				@if ( $block->hasCustomInspector() )
					{!! $block->renderInspector( [ 'content' => [], 'styles' => [] ] ) !!}
				@endif

				@foreach ( $ungroupedFields as $fieldName => $fieldSchema )
					<x-ve-inspector-field
						:name="$fieldName"
						:schema="$fieldSchema"
						:value="null"
						:block-id="$blockId"
					/>
				@endforeach

				</div>

			@foreach ( $panelGroups as $panelLabel => $panelFields )
				<x-ve-panel-body :title="$panelLabel" :collapsible="true">
					@foreach ( $panelFields as $fieldName => $fieldSchema )
						<x-ve-inspector-field
							:name="$fieldName"
							:schema="$fieldSchema"
							:value="null"
							:block-id="$blockId"
						/>
					@endforeach
				</x-ve-panel-body>
			@endforeach

			@if ( [] !== $advancedSchema )
				<x-ve-panel-body :title="__( 'visual-editor::ve.advanced_tab' )" :opened="false" :collapsible="true">
					@foreach ( $advancedSchema as $fieldName => $fieldSchema )
						<x-ve-inspector-field
							:name="$fieldName"
							:schema="$fieldSchema"
							:value="null"
							:block-id="$blockId"
						/>
					@endforeach
				</x-ve-panel-body>
			@endif
		@elseif ( 'styles' === $tab )
			<div class="space-y-3 p-1">
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

				@foreach ( $styleSchema as $fieldName => $fieldSchema )
					<x-ve-inspector-field
						:name="$fieldName"
						:schema="$fieldSchema"
						:value="null"
						:block-id="$blockId"
					/>
				@endforeach
			</div>
		@endif
	@else
		<div class="text-center text-sm text-base-content/60">
			{{ __( 'visual-editor::ve.block_settings' ) }}
		</div>
	@endif
@else
	{{-- Full tabbed mode (standalone usage) --}}
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
			</div>

			{{-- Settings Tab --}}
			<div
				x-show="activeTab === 'settings'"
				role="tabpanel"
				class="space-y-3 p-3"
			>
				@if ( $block->hasCustomInspector() )
					{!! $block->renderInspector( [ 'content' => [], 'styles' => [] ] ) !!}
				@endif

				@foreach ( $ungroupedFields as $fieldName => $fieldSchema )
					<x-ve-inspector-field
						:name="$fieldName"
						:schema="$fieldSchema"
						:value="null"
						:block-id="$blockId"
					/>
				@endforeach
			</div>

			@foreach ( $panelGroups as $panelLabel => $panelFields )
				<div x-show="activeTab === 'settings'">
					<x-ve-panel-body :title="$panelLabel" :collapsible="true">
						@foreach ( $panelFields as $fieldName => $fieldSchema )
							<x-ve-inspector-field
								:name="$fieldName"
								:schema="$fieldSchema"
								:value="null"
								:block-id="$blockId"
							/>
						@endforeach
					</x-ve-panel-body>
				</div>
			@endforeach

			@if ( [] !== $advancedSchema )
				<div x-show="activeTab === 'settings'">
					<x-ve-panel-body :title="__( 'visual-editor::ve.advanced_tab' )" :opened="false" :collapsible="true">
						@foreach ( $advancedSchema as $fieldName => $fieldSchema )
							<x-ve-inspector-field
								:name="$fieldName"
								:schema="$fieldSchema"
								:value="null"
								:block-id="$blockId"
							/>
						@endforeach
					</x-ve-panel-body>
				</div>
			@endif

			{{-- Styles Tab --}}
			<div
				x-show="activeTab === 'styles'"
				role="tabpanel"
				class="space-y-3 p-3"
			>
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

				@foreach ( $styleSchema as $fieldName => $fieldSchema )
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
@endif
