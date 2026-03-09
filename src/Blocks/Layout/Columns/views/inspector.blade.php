{{--
 * Columns Block Inspector Controls
 *
 * Provides responsive columns count range control
 * for the columns block inspector settings panel.
 * Uses the responsive range control to support global
 * or per-breakpoint (desktop/tablet/mobile) column counts.
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
		getColumnsValues() {
			const data = this.block?.attributes?.columns;
			if ( ! data || typeof data !== 'object' ) {
				const val = parseInt( data ) || 2;
				return { mode: 'global', global: val, desktop: val, tablet: val, mobile: 1 };
			}
			return {
				mode: data.mode ?? 'global',
				global: data.global ?? data.desktop ?? 2,
				desktop: data.desktop ?? 2,
				tablet: data.tablet ?? 2,
				mobile: data.mobile ?? 1,
			};
		},
		setColumns( values ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( ! blockId ) return;
			const store = Alpine.store( 'editor' );
			const block = store.getBlock( blockId );
			if ( ! block ) return;

			// Determine the maximum column count across all breakpoints.
			const maxCount = Math.max(
				values.global || 0,
				values.desktop || 0,
				values.tablet || 0,
				values.mobile || 0,
			);

			// Update the columns attribute.
			store.updateBlock( blockId, { columns: values } );

			// Add inner blocks if we need more columns.
			const current = ( block.innerBlocks || [] ).length;
			for ( let i = current; i < maxCount; i++ ) {
				store.addInnerBlock( blockId, {
					type: 'column',
					attributes: { width: '', verticalAlignment: 'top' },
					innerBlocks: [],
				}, i );
			}

			// Remove extra columns from the end.
			for ( let i = current - 1; i >= maxCount; i-- ) {
				const inner = block.innerBlocks[ i ];
				if ( inner ) store.removeInnerBlock( blockId, inner.id );
			}
		},
	}"
>
	<x-ve-panel-body :title="__( 'visual-editor::ve.settings' )" :opened="true" :collapsible="true">
		<x-ve-responsive-range-control
			:label="__( 'visual-editor::ve.columns_count' )"
			:value="[ 'mode' => 'global', 'global' => 2, 'desktop' => 2, 'tablet' => 2, 'mobile' => 1 ]"
			:min="1"
			:max="6"
			:step="1"
			x-effect="
				const v = getColumnsValues();
				mode = v.mode;
				globalVal = v.global;
				desktopVal = v.desktop;
				tabletVal = v.tablet;
				mobileVal = v.mobile;
			"
			x-on:ve-responsive-range-change.stop="setColumns( $event.detail.values )"
		/>
	</x-ve-panel-body>
</div>
