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
		<p class="text-sm text-base-content/40 italic px-4 py-3">{{ __( 'visual-editor::ve.select_block_for_styles' ) }}</p>
	</template>
	<template x-if="selectedBlockId && blockType">
		<div>
			{{-- Reset Styles Button --}}
			<div
				x-data="{
					_styleKeys: [ 'backgroundColor', 'textColor', 'fontSize', 'fontFamily', 'padding', 'margin', 'border', 'shadow', 'lineHeight', 'letterSpacing', 'textDecoration', 'textTransform', 'fontAppearance', 'blockSpacing', 'backgroundImage', 'backgroundSize', 'backgroundPosition', 'backgroundGradient', 'aspectRatio', 'minHeight' ],
					get block() { return $store.editor?.getBlock( $store.selection?.focused ); },
					get attrs() { return this.block?.attributes || {}; },
					get hasOverrides() {
						return this._styleKeys.some( ( key ) => undefined !== this.attrs[ key ] );
					},
					_refreshInspector( blockId ) {
						if ( $store.selection ) {
							$store.selection.focused = null;
							requestAnimationFrame( () => {
								$store.selection.focused = blockId;
							} );
						}
					},
					resetAll() {
						const blockId = $store.selection?.focused;
						if ( ! blockId ) return;
						$store.editor.removeBlockAttributes( blockId, this._styleKeys );
						this._refreshInspector( blockId );
					},
				}"
				x-show="hasOverrides"
				x-cloak
				class="px-3 py-2 border-b border-base-300"
			>
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-block text-warning hover:text-warning/80 gap-1"
					x-on:click.stop="resetAll()"
					:title="{{ Js::from( __( 'visual-editor::ve.reset_to_default' ) ) }}"
				>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M8 1a.75.75 0 0 1 .75.75v6.5a.75.75 0 0 1-1.5 0v-6.5A.75.75 0 0 1 8 1ZM4.11 3.05a.75.75 0 0 1 0 1.06 5.5 5.5 0 1 0 7.78 0 .75.75 0 0 1 1.06-1.06 7 7 0 1 1-9.9 0 .75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>
					{{ __( 'visual-editor::ve.reset_to_default' ) }}
				</button>
			</div>

			@foreach ( $inspectorBlockTypes as $inspectorType )
				<div x-show="blockType === {{ Js::from( $inspectorType ) }}" x-cloak>
					<x-ve-inspector-controls :block-type="$inspectorType" block-id="dynamic" tab="styles" />
				</div>
			@endforeach

			{{-- Custom inspector HTML is rendered by inspector-controls component via renderInspector() --}}

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
