{{--
 * Columns Block Inspector Controls
 *
 * Provides columns count range slider and stack-on-mobile toggle
 * for the columns block inspector settings panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Columns\Views
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
		get columnsCount() { return parseInt( this.block?.attributes?.columns ) || 2; },
		get stackOnMobile() { return this.block?.attributes?.stackOnMobile !== false; },
		updateAttr( attrs ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
		},
		setColumnsCount( count ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId ) return;
			const block = Alpine.store( 'editor' ).getBlock( blockId );
			if ( ! block ) return;

			const current = ( block.innerBlocks || [] ).length;
			this.updateAttr( { columns: count } );

			// Add columns if needed.
			for ( let i = current; i < count; i++ ) {
				Alpine.store( 'editor' ).addInnerBlock( blockId, {
					id: 'block-' + Date.now() + '-col-' + i,
					type: 'column',
					attributes: { width: '', verticalAlignment: 'top' },
					innerBlocks: [],
				}, i );
			}
			// Remove extra columns from the end.
			for ( let i = current - 1; i >= count; i-- ) {
				const inner = block.innerBlocks[ i ];
				if ( inner ) Alpine.store( 'editor' ).removeInnerBlock( blockId, inner.id );
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
