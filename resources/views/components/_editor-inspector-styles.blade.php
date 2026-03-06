{{-- Inspector Styles Panel Partial --}}
<div x-data="{
	get selectedBlockId() { return $store.selection?.focused ?? null },
	get selectedBlock() {
		if ( ! this.selectedBlockId || ! $store.editor ) return null;
		return $store.editor.getBlock( this.selectedBlockId );
	},
	get blockType() { return this.selectedBlock?.type ?? null },
}">
	<template x-if="! selectedBlockId">
		<p class="text-sm text-base-content/40 italic px-4 py-3">{{ __( 'Select a block to view its styles.' ) }}</p>
	</template>
	<template x-if="selectedBlockId && blockType">
		<div>
			@foreach ( $inspectorBlockTypes as $inspectorType )
				<div x-show="blockType === {{ Js::from( $inspectorType ) }}" x-cloak>
					<x-ve-inspector-controls :block-type="$inspectorType" block-id="dynamic" tab="styles" />
				</div>
			@endforeach

			{{-- Custom inspector HTML from blocks that declare hasCustomInspector() (styles tab) --}}
			@foreach ( $customInspectorHtml as $ciType => $ciHtml )
				<div x-show="blockType === {{ Js::from( $ciType ) }}" x-cloak>
					{!! $ciHtml !!}
				</div>
			@endforeach

			{{-- Group block: Group Spacing panel in styles tab --}}
			<div x-show="blockType === 'group'" x-cloak>
				<div
					x-data="{
						get block() {
							const blockId = $store.selection?.focused;
							if ( ! blockId || ! $store.editor ) return null;
							return $store.editor.getBlock( blockId );
						},
						get useFlexbox() { return this.block?.attributes?.useFlexbox || false; },
						get fillHeight() { return this.block?.attributes?.fillHeight || false; },
						get innerSpacing() { return this.block?.attributes?.innerSpacing || 'normal'; },
						updateAttr( attrs ) {
							const blockId = $store.selection?.focused;
							if ( blockId ) $store.editor.updateBlock( blockId, attrs );
						},
					}"
				>
					<x-ve-panel-body :title="__( 'visual-editor::ve.group_spacing' )" :opened="true" :collapsible="true">
						<x-ve-panel-row :label="__( 'visual-editor::ve.use_flexbox' )">
							<input type="checkbox" class="toggle toggle-sm toggle-primary" :checked="useFlexbox" x-on:change="updateAttr( { useFlexbox: $event.target.checked } )" />
						</x-ve-panel-row>
						<x-ve-panel-row :label="__( 'visual-editor::ve.fill_height' )">
							<input type="checkbox" class="toggle toggle-sm toggle-primary" :checked="fillHeight" x-on:change="updateAttr( { fillHeight: $event.target.checked } )" />
						</x-ve-panel-row>
						<x-ve-panel-row :label="__( 'visual-editor::ve.inner_spacing' )">
							<select class="select select-bordered select-sm w-full" :value="innerSpacing" x-on:change="updateAttr( { innerSpacing: $event.target.value } )">
								<option value="none">{{ __( 'visual-editor::ve.none' ) }}</option>
								<option value="small">{{ __( 'visual-editor::ve.small' ) }}</option>
								<option value="normal">{{ __( 'visual-editor::ve.normal_spacing' ) }}</option>
								<option value="medium">{{ __( 'visual-editor::ve.medium' ) }}</option>
								<option value="large">{{ __( 'visual-editor::ve.large' ) }}</option>
							</select>
						</x-ve-panel-row>
					</x-ve-panel-body>
				</div>
			</div>
		</div>
	</template>
</div>
