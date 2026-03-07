{{--
 * Column Block Inspector Controls
 *
 * Provides column width text input for the column block
 * inspector settings panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Column\Views
 *
 * @since      2.0.0
 --}}

<div
	x-data="{
		get block() {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
			return Alpine.store( 'editor' ).getBlock( blockId );
		},
		get colWidth() { return this.block?.attributes?.width || ''; },
		updateAttr( attrs ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
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
