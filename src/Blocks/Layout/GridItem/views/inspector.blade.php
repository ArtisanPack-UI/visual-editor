{{--
 * Grid Item Block Inspector Controls
 *
 * Provides responsive column span and row span controls
 * for the grid item block inspector settings panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\GridItem\Views
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
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
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
