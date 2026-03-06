{{-- Inspector Settings Panel Partial --}}
<div x-data="{
	inspectorBlockNames: {{ Js::from( $inspectorBlockNames ) }},
	inspectorBlockDescriptions: {{ Js::from( $inspectorBlockDescriptions ) }},
	inspectorBlockIcons: {{ Js::from( $toolbarBlockIcons ) }},
	get selectedBlockId() { return $store.selection?.focused ?? null },
	get selectedBlock() {
		if ( ! this.selectedBlockId || ! $store.editor ) return null;
		return $store.editor.getBlock( this.selectedBlockId );
	},
	get blockType() { return this.selectedBlock?.type ?? null },
}">
	<template x-if="! selectedBlockId">
		<p class="text-sm text-base-content/40 italic px-4 py-3">{{ __( 'Select a block to view its settings.' ) }}</p>
	</template>
	<template x-if="selectedBlockId && blockType">
		<div>
			<div class="flex items-center gap-3 px-4 py-3 border-b border-base-300">
				<div class="flex items-center justify-center w-8 h-8 rounded bg-base-200 text-base-content/70 shrink-0" x-html="inspectorBlockIcons[ blockType ] || ''"></div>
				<div class="min-w-0">
					<div class="text-sm font-semibold text-base-content" x-text="inspectorBlockNames[ blockType ] || blockType"></div>
					<div class="text-xs text-base-content/50 truncate" x-show="inspectorBlockDescriptions[ blockType ]" x-text="inspectorBlockDescriptions[ blockType ]"></div>
				</div>
			</div>

			{{-- Columns block: Columns count + Stack on mobile --}}
			<div x-show="blockType === 'columns'" x-cloak>
				<div
					x-data="{
						get block() {
							const blockId = $store.selection?.focused;
							if ( ! blockId || ! $store.editor ) return null;
							return $store.editor.getBlock( blockId );
						},
						get columnsCount() { return parseInt( this.block?.attributes?.columns ) || 2; },
						get stackOnMobile() { return this.block?.attributes?.stackOnMobile !== false; },
						updateAttr( attrs ) {
							const blockId = $store.selection?.focused;
							if ( blockId ) $store.editor.updateBlock( blockId, attrs );
						},
						setColumnsCount( count ) {
							const blockId = $store.selection?.focused;
							if ( ! blockId ) return;
							const block = $store.editor.getBlock( blockId );
							if ( ! block ) return;

							const current = ( block.innerBlocks || [] ).length;
							this.updateAttr( { columns: count } );

							// Add columns if needed.
							for ( let i = current; i < count; i++ ) {
								$store.editor.addInnerBlock( blockId, {
									id: 'block-' + Date.now() + '-col-' + i,
									type: 'column',
									attributes: { width: '', verticalAlignment: 'top' },
									innerBlocks: [],
								}, i );
							}
							// Remove extra columns from the end.
							for ( let i = current - 1; i >= count; i-- ) {
								const inner = block.innerBlocks[ i ];
								if ( inner ) $store.editor.removeInnerBlock( blockId, inner.id );
							}
						},
					}"
				>
					<x-ve-panel-body :title="__( 'visual-editor::ve.settings' )" :opened="true" :collapsible="true">
						<x-ve-panel-row :label="__( 'visual-editor::ve.columns_count' )">
							<div class="flex items-center gap-2 w-full">
								<input type="range" class="range range-sm range-primary flex-1" min="1" max="6" step="1" :value="columnsCount" x-on:input="setColumnsCount( parseInt( $event.target.value ) )" />
								<span class="text-sm font-medium w-4 text-center" x-text="columnsCount"></span>
							</div>
						</x-ve-panel-row>
						<x-ve-panel-row :label="__( 'visual-editor::ve.stack_on_mobile' )">
							<input type="checkbox" class="toggle toggle-sm toggle-primary" :checked="stackOnMobile" x-on:change="updateAttr( { stackOnMobile: $event.target.checked } )" />
						</x-ve-panel-row>
					</x-ve-panel-body>
				</div>
			</div>

			{{-- Column block: Width --}}
			<div x-show="blockType === 'column'" x-cloak>
				<div
					x-data="{
						get block() {
							const blockId = $store.selection?.focused;
							if ( ! blockId || ! $store.editor ) return null;
							return $store.editor.getBlock( blockId );
						},
						get colWidth() { return this.block?.attributes?.width || ''; },
						updateAttr( attrs ) {
							const blockId = $store.selection?.focused;
							if ( blockId ) $store.editor.updateBlock( blockId, attrs );
						},
					}"
				>
					<x-ve-panel-body :title="__( 'visual-editor::ve.column_width' )" :opened="true" :collapsible="true">
						<x-ve-panel-row :label="__( 'visual-editor::ve.column_width' )">
							<input type="text" class="input input-bordered input-sm w-full" :value="colWidth" x-on:change="updateAttr( { width: $event.target.value } )" placeholder="e.g. 50%" />
						</x-ve-panel-row>
						<p class="text-xs text-base-content/50 px-1 mt-1">{{ __( 'visual-editor::ve.column_width_hint' ) }}</p>
					</x-ve-panel-body>
				</div>
			</div>

			{{-- Grid Item block: Column Span + Row Span --}}
			<div x-show="blockType === 'grid-item'" x-cloak>
				<div
					x-data="{
						get block() {
							const blockId = $store.selection?.focused;
							if ( ! blockId || ! $store.editor ) return null;
							return $store.editor.getBlock( blockId );
						},
						getSpanValues( attr ) {
							const data = this.block?.attributes?.[ attr ];
							if ( ! data || typeof data !== 'object' ) {
								const val = data ?? 1;
								return { mode: 'global', global: val, desktop: val, tablet: val, mobile: val };
							}
							return {
								mode: data.mode ?? 'global',
								global: data.global ?? data.desktop ?? 1,
								desktop: data.desktop ?? 1,
								tablet: data.tablet ?? 1,
								mobile: data.mobile ?? 1,
							};
						},
						updateAttr( attrs ) {
							const blockId = $store.selection?.focused;
							if ( blockId ) $store.editor.updateBlock( blockId, attrs );
						},
					}"
				>
					<x-ve-panel-body :title="__( 'visual-editor::ve.grid_column_span' )" :opened="true" :collapsible="true">
						<x-ve-responsive-range-control
							:label="__( 'visual-editor::ve.grid_column_span' )"
							:value="[ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ]"
							:min="1"
							:max="12"
							:step="1"
							x-effect="
								const v = getSpanValues( 'columnSpan' );
								mode = v.mode;
								globalVal = v.global;
								desktopVal = v.desktop;
								tabletVal = v.tablet;
								mobileVal = v.mobile;
							"
							x-on:ve-responsive-range-change.stop="updateAttr( { columnSpan: $event.detail.values } )"
						/>
						<x-ve-responsive-range-control
							:label="__( 'visual-editor::ve.grid_row_span' )"
							:value="[ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ]"
							:min="1"
							:max="12"
							:step="1"
							x-effect="
								const v = getSpanValues( 'rowSpan' );
								mode = v.mode;
								globalVal = v.global;
								desktopVal = v.desktop;
								tabletVal = v.tablet;
								mobileVal = v.mobile;
							"
							x-on:ve-responsive-range-change.stop="updateAttr( { rowSpan: $event.detail.values } )"
						/>
					</x-ve-panel-body>
				</div>
			</div>

			{{-- Group block: Variation switcher + Layout + Group Spacing panels --}}
			<div x-show="blockType === 'group'" x-cloak>
				<div
					x-data="{
						get block() {
							const blockId = $store.selection?.focused;
							if ( ! blockId || ! $store.editor ) return null;
							return $store.editor.getBlock( blockId );
						},
						get flexDirection() { return this.block?.attributes?.flexDirection || 'column'; },
						get flexWrap() { return this.block?.attributes?.flexWrap || 'nowrap'; },
						get justifyContent() { return this.block?.attributes?.justifyContent || 'flex-start'; },
						get useContentWidth() { return this.block?.attributes?.useContentWidth || false; },
						get contentWidth() { return this.block?.attributes?.contentWidth || ''; },
						get wideWidth() { return this.block?.attributes?.wideWidth || ''; },
						updateAttr( attrs ) {
							const blockId = $store.selection?.focused;
							if ( blockId ) $store.editor.updateBlock( blockId, attrs );
						},
						setVariation( name ) {
							const map = {
								group: { flexDirection: 'column', flexWrap: 'nowrap', _groupVariation: 'group' },
								row:   { flexDirection: 'row', flexWrap: 'nowrap', justifyContent: 'flex-start', _groupVariation: 'row' },
								stack: { flexDirection: 'column', flexWrap: 'nowrap', _groupVariation: 'stack' },
							};
							this.updateAttr( map[ name ] || map.group );
						},
						getActiveVariation() {
							const stored = this.block?.attributes?._groupVariation;
							if ( stored ) return stored;
							if ( 'row' === this.flexDirection ) return 'row';
							return 'group';
						},
					}"
				>
					{{-- Variation Switcher Row --}}
					<div class="flex items-center gap-1 px-4 py-2 border-b border-base-300">
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="getActiveVariation() === 'group' ? 'bg-primary text-primary-content' : ''" x-on:click="setVariation( 'group' )" title="{{ __( 'visual-editor::ve.variation_group' ) }}">
							<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="9" x2="21" y2="9" /><line x1="3" y1="15" x2="21" y2="15" /></svg>
						</button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="getActiveVariation() === 'row' ? 'bg-primary text-primary-content' : ''" x-on:click="setVariation( 'row' )" title="{{ __( 'visual-editor::ve.variation_row' ) }}">
							<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="9" y1="3" x2="9" y2="21" /><line x1="15" y1="3" x2="15" y2="21" /></svg>
						</button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="getActiveVariation() === 'stack' ? 'bg-primary text-primary-content' : ''" x-on:click="setVariation( 'stack' )" title="{{ __( 'visual-editor::ve.variation_stack' ) }}">
							<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="8" x2="21" y2="8" /><line x1="3" y1="13" x2="21" y2="13" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
						</button>
						<button type="button" class="btn btn-ghost btn-xs btn-square opacity-40 cursor-not-allowed" disabled title="{{ __( 'visual-editor::ve.variation_grid' ) }}">
							<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="12" y1="3" x2="12" y2="21" /></svg>
						</button>
					</div>

					{{-- Layout Panel --}}
					<x-ve-panel-body :title="__( 'visual-editor::ve.layout' )" :opened="true" :collapsible="true">
						{{-- Group variation: content width controls --}}
						<template x-if="getActiveVariation() === 'group'">
							<div class="space-y-3">
								<x-ve-panel-row :label="__( 'visual-editor::ve.use_content_width' )">
									<input type="checkbox" class="toggle toggle-sm toggle-primary" :checked="useContentWidth" x-on:change="updateAttr( { useContentWidth: $event.target.checked } )" />
								</x-ve-panel-row>
								<template x-if="useContentWidth">
									<div class="space-y-3">
										<x-ve-panel-row :label="__( 'visual-editor::ve.content_width' )">
											<input type="text" class="input input-bordered input-sm w-full" :value="contentWidth" x-on:change="updateAttr( { contentWidth: $event.target.value } )" placeholder="e.g. 650px" />
										</x-ve-panel-row>
										<x-ve-panel-row :label="__( 'visual-editor::ve.wide_width_label' )">
											<input type="text" class="input input-bordered input-sm w-full" :value="wideWidth" x-on:change="updateAttr( { wideWidth: $event.target.value } )" placeholder="e.g. 1200px" />
										</x-ve-panel-row>
									</div>
								</template>

								{{-- 3-button justification for Group --}}
								<x-ve-panel-row :label="__( 'visual-editor::ve.justify_content' )">
									<div class="flex items-center gap-0.5" role="group">
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-start' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-start' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="4" x2="4" y2="20" /><rect x="8" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="14" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'center' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="4" x2="12" y2="20" stroke-dasharray="2 2" /><rect x="5" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="15" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-end' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-end' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="20" y1="4" x2="20" y2="20" /><rect x="6" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /><rect x="12" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /></svg></button>
									</div>
								</x-ve-panel-row>
							</div>
						</template>

						{{-- Row/Stack variation: justification + orientation + wrap --}}
						<template x-if="getActiveVariation() === 'row' || getActiveVariation() === 'stack'">
							<div class="space-y-3">
								{{-- 4-button justification --}}
								<x-ve-panel-row :label="__( 'visual-editor::ve.justify_content' )">
									<div class="flex items-center gap-0.5" role="group">
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-start' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-start' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="4" x2="4" y2="20" /><rect x="8" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="14" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'center' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="4" x2="12" y2="20" stroke-dasharray="2 2" /><rect x="5" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="15" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-end' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-end' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="20" y1="4" x2="20" y2="20" /><rect x="6" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /><rect x="12" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /></svg></button>
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'space-between' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'space-between' } )"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="4" x2="4" y2="20" /><line x1="20" y1="4" x2="20" y2="20" /><rect x="7" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /><rect x="13" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
									</div>
								</x-ve-panel-row>

								{{-- Orientation toggle --}}
								<x-ve-panel-row :label="__( 'visual-editor::ve.orientation' )">
									<div class="flex items-center gap-0.5" role="group">
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'row' === flexDirection ? 'bg-base-200' : ''" x-on:click="updateAttr( { flexDirection: 'row' } )" title="{{ __( 'visual-editor::ve.orientation_horizontal' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg></button>
										<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'column' === flexDirection ? 'bg-base-200' : ''" x-on:click="updateAttr( { flexDirection: 'column' } )" title="{{ __( 'visual-editor::ve.orientation_vertical' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" /></svg></button>
									</div>
								</x-ve-panel-row>

								{{-- Wrap toggle --}}
								<x-ve-panel-row :label="__( 'visual-editor::ve.allow_wrap' )">
									<input type="checkbox" class="toggle toggle-sm toggle-primary" :checked="flexWrap === 'wrap'" x-on:change="updateAttr( { flexWrap: $event.target.checked ? 'wrap' : 'nowrap' } )" />
								</x-ve-panel-row>
							</div>
						</template>
					</x-ve-panel-body>

				</div>
			</div>

			@foreach ( $inspectorBlockTypes as $inspectorType )
				<div x-show="blockType === {{ Js::from( $inspectorType ) }}" x-cloak>
					<x-ve-inspector-controls :block-type="$inspectorType" block-id="dynamic" tab="settings" />
				</div>
			@endforeach

			{{-- Custom inspector HTML from blocks that declare hasCustomInspector() --}}
			@foreach ( $customInspectorHtml as $ciType => $ciHtml )
				<div x-show="blockType === {{ Js::from( $ciType ) }}" x-cloak>
					{!! $ciHtml !!}
				</div>
			@endforeach
		</div>
	</template>
</div>
