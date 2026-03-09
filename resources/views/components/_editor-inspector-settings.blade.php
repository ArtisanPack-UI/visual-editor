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

			@foreach ( $inspectorBlockTypes as $inspectorType )
				<div x-show="blockType === {{ Js::from( $inspectorType ) }}" x-cloak>
					<x-ve-inspector-controls :block-type="$inspectorType" block-id="dynamic" tab="settings" />
				</div>
			@endforeach

			{{-- Custom inspector HTML is rendered by inspector-controls component via renderInspector() --}}
		</div>
	</template>
</div>
