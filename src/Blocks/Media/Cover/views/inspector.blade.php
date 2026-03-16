{{--
 * Cover Block Inspector Controls
 *
 * Custom inspector panel rendered at the top of the Settings tab.
 * Provides a combined min-height + unit control and a visual focal point picker.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Cover\Views
 *
 * @since      1.0.0
 --}}

<x-ve-panel-body :title="__( 'visual-editor::ve.cover_panel_dimensions' )" :collapsible="true">
	<div
		x-data="{
			get block() {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
				return Alpine.store( 'editor' ).getBlock( blockId );
			},
			get minHeightRaw() {
				const val = this.block?.attributes?.minHeight || '430px';
				return parseFloat( val ) || 430;
			},
			get minHeightUnit() { return this.block?.attributes?.minHeightUnit || 'px'; },
			updateMinHeight( value, unit ) {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( blockId ) {
					Alpine.store( 'editor' ).updateBlock( blockId, {
						minHeight: value + unit,
						minHeightUnit: unit,
					} );
				}
			},
		}"
	>
		<x-ve-unit-control
			:label="__( 'visual-editor::ve.min_height' )"
			:value="430"
			unit="px"
			:units="[ 'px', 'vh', 'vw' ]"
			:min="0"
			:max="2000"
			:step="1"
			x-effect="
				const _b = $store.editor?.getBlock( $store.selection?.focused );
				if ( _b ) {
					const raw = parseFloat( _b.attributes?.minHeight || '430' );
					const u = _b.attributes?.minHeightUnit || 'px';
					if ( $el.__x_unit_synced !== ( raw + u ) ) {
						$el.__x_unit_synced = raw + u;
						$el.querySelector( 'input[type=number]' ) && ( $el.querySelector( 'input[type=number]' ).value = raw );
						$el.querySelector( 'select' ) && ( $el.querySelector( 'select' ).value = u );
					}
				}
			"
			x-on:ve-unit-change.stop="updateMinHeight( $event.detail.value, $event.detail.unit )"
		/>
	</div>
</x-ve-panel-body>

<x-ve-panel-body :title="__( 'visual-editor::ve.cover_panel_focal_point' )" :collapsible="true">
	<div
		x-data="{
			dragging: false,
			tempFocalX: 50,
			tempFocalY: 50,
			get block() {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( ! blockId || ! Alpine.store( 'editor' ) ) return null;
				return Alpine.store( 'editor' ).getBlock( blockId );
			},
			get mediaType() { return this.block?.attributes?.mediaType || 'image'; },
			get mediaUrl() { return this.block?.attributes?.mediaUrl || ''; },
			get focalX() {
				const fp = this.block?.attributes?.focalPoint;
				return fp ? Math.round( ( fp.x ?? 0.5 ) * 100 ) : 50;
			},
			get focalY() {
				const fp = this.block?.attributes?.focalPoint;
				return fp ? Math.round( ( fp.y ?? 0.5 ) * 100 ) : 50;
			},
			setFocalFromEvent( e ) {
				const target = this.$refs.focalArea;
				if ( ! target ) return;
				const rect = target.getBoundingClientRect();
				const x = Math.max( 0, Math.min( 100, Math.round( ( ( e.clientX - rect.left ) / rect.width ) * 100 ) ) );
				const y = Math.max( 0, Math.min( 100, Math.round( ( ( e.clientY - rect.top ) / rect.height ) * 100 ) ) );
				this.tempFocalX = x;
				this.tempFocalY = y;
			},
			startDrag( e ) {
				this.tempFocalX = this.focalX;
				this.tempFocalY = this.focalY;
				this.dragging = true;
				this.setFocalFromEvent( e );
			},
			onDrag( e ) {
				if ( ! this.dragging ) return;
				e.preventDefault();
				this.setFocalFromEvent( e );
			},
			stopDrag() {
				if ( this.dragging ) {
					this.updateFocal( this.tempFocalX, this.tempFocalY );
				}
				this.dragging = false;
			},
			updateFocal( x, y ) {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( blockId ) {
					Alpine.store( 'editor' ).updateBlock( blockId, {
						focalPoint: { x: x / 100, y: y / 100 },
					} );
				}
			},
		}"
		x-on:mousemove.window="onDrag( $event )"
		x-on:mouseup.window="stopDrag()"
	>
		<template x-if="'image' === mediaType && mediaUrl">
			<div class="space-y-2">
				{{-- Visual focal point picker with drag support --}}
				<div
					x-ref="focalArea"
					class="relative w-full rounded-lg overflow-hidden border border-base-300 cursor-crosshair select-none"
					style="aspect-ratio: 16/9;"
					x-on:mousedown.prevent="startDrag( $event )"
				>
					<img
						:src="mediaUrl"
						alt=""
						class="w-full h-full object-cover pointer-events-none"
						draggable="false"
					/>
					<div
						class="absolute w-5 h-5 border-2 border-white rounded-full pointer-events-none"
						style="transform: translate(-50%, -50%); box-shadow: 0 0 0 1px rgba(0,0,0,0.3), 0 2px 4px rgba(0,0,0,0.3);"
						:style="{ left: ( dragging ? tempFocalX : focalX ) + '%', top: ( dragging ? tempFocalY : focalY ) + '%' }"
					></div>
				</div>

				{{-- Numeric inputs --}}
				<div class="flex gap-2">
					<div class="flex-1">
						<label class="text-xs font-medium text-base-content/60 block mb-0.5">
							{{ __( 'visual-editor::ve.cover_focal_left' ) }}
						</label>
						<input
							type="number"
							class="input input-bordered input-sm w-full"
							min="0"
							max="100"
							:value="focalX"
							x-on:change="const v = Math.max( 0, Math.min( 100, parseInt( $el.value, 10 ) ) ); updateFocal( Number.isNaN( parseInt( $el.value, 10 ) ) ? 50 : v, focalY )"
						/>
					</div>
					<div class="flex-1">
						<label class="text-xs font-medium text-base-content/60 block mb-0.5">
							{{ __( 'visual-editor::ve.cover_focal_top' ) }}
						</label>
						<input
							type="number"
							class="input input-bordered input-sm w-full"
							min="0"
							max="100"
							:value="focalY"
							x-on:change="const v = Math.max( 0, Math.min( 100, parseInt( $el.value, 10 ) ) ); updateFocal( focalX, Number.isNaN( parseInt( $el.value, 10 ) ) ? 50 : v )"
						/>
					</div>
				</div>
			</div>
		</template>

		<template x-if="'image' !== mediaType || ! mediaUrl">
			<p class="text-sm text-base-content/50">
				{{ __( 'visual-editor::ve.cover_focal_point_unavailable' ) }}
			</p>
		</template>
	</div>
</x-ve-panel-body>
