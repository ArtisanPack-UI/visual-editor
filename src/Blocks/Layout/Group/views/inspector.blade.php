{{--
 * Group Block Inspector Controls
 *
 * Provides variation switcher (group, row, stack, grid) and
 * layout panels with justification, orientation, wrap, and
 * content-width controls for the group block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Group\Views
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
		get flexDirection() { return this.block?.attributes?.flexDirection || 'column'; },
		get flexWrap() { return this.block?.attributes?.flexWrap || 'nowrap'; },
		get justifyContent() { return this.block?.attributes?.justifyContent || 'flex-start'; },
		get useContentWidth() { return this.block?.attributes?.useContentWidth || false; },
		get contentWidth() { return this.block?.attributes?.contentWidth || ''; },
		get wideWidth() { return this.block?.attributes?.wideWidth || ''; },
		updateAttr( attrs ) {
			const blockId = Alpine.store( 'selection' )?.focused;
			if ( blockId ) Alpine.store( 'editor' ).updateBlock( blockId, attrs );
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
			return this.block?.attributes?._groupVariation || 'group';
		},
	}"
>
	{{-- Variation Switcher Row --}}
	<div class="flex items-center gap-1 px-4 py-2 border-b border-base-300">
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="getActiveVariation() === 'group' ? 'bg-primary text-primary-content' : ''" x-on:click="setVariation( 'group' )" title="{{ __( 'visual-editor::ve.variation_group' ) }}" aria-label="{{ __( 'visual-editor::ve.variation_group' ) }}" :aria-pressed="( getActiveVariation() === 'group' ).toString()">
			<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="9" x2="21" y2="9" /><line x1="3" y1="15" x2="21" y2="15" /></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="getActiveVariation() === 'row' ? 'bg-primary text-primary-content' : ''" x-on:click="setVariation( 'row' )" title="{{ __( 'visual-editor::ve.variation_row' ) }}" aria-label="{{ __( 'visual-editor::ve.variation_row' ) }}" :aria-pressed="( getActiveVariation() === 'row' ).toString()">
			<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="9" y1="3" x2="9" y2="21" /><line x1="15" y1="3" x2="15" y2="21" /></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square" :class="getActiveVariation() === 'stack' ? 'bg-primary text-primary-content' : ''" x-on:click="setVariation( 'stack' )" title="{{ __( 'visual-editor::ve.variation_stack' ) }}" aria-label="{{ __( 'visual-editor::ve.variation_stack' ) }}" :aria-pressed="( getActiveVariation() === 'stack' ).toString()">
			<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="8" x2="21" y2="8" /><line x1="3" y1="13" x2="21" y2="13" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
		</button>
		<button type="button" class="btn btn-ghost btn-xs btn-square opacity-40 cursor-not-allowed" disabled title="{{ __( 'visual-editor::ve.variation_grid' ) }}" aria-label="{{ __( 'visual-editor::ve.variation_grid' ) }}" aria-disabled="true">
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
					<div class="flex items-center gap-0.5" role="group" aria-label="{{ __( 'visual-editor::ve.justify_content' ) }}">
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-start' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-start' } )" :aria-pressed="( justifyContent === 'flex-start' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_start' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="4" y2="20" /><rect x="8" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="14" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'center' } )" :aria-pressed="( justifyContent === 'center' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_center' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="4" x2="12" y2="20" stroke-dasharray="2 2" /><rect x="5" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="15" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-end' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-end' } )" :aria-pressed="( justifyContent === 'flex-end' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_end' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="20" y1="4" x2="20" y2="20" /><rect x="6" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /><rect x="12" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /></svg></button>
					</div>
				</x-ve-panel-row>
			</div>
		</template>

		{{-- Row/Stack variation: justification + orientation + wrap --}}
		<template x-if="getActiveVariation() === 'row' || getActiveVariation() === 'stack'">
			<div class="space-y-3">
				{{-- 4-button justification --}}
				<x-ve-panel-row :label="__( 'visual-editor::ve.justify_content' )">
					<div class="flex items-center gap-0.5" role="group" aria-label="{{ __( 'visual-editor::ve.justify_content' ) }}">
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-start' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-start' } )" :aria-pressed="( justifyContent === 'flex-start' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_start' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="4" y2="20" /><rect x="8" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="14" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'center' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'center' } )" :aria-pressed="( justifyContent === 'center' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_center' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="4" x2="12" y2="20" stroke-dasharray="2 2" /><rect x="5" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /><rect x="15" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'flex-end' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'flex-end' } )" :aria-pressed="( justifyContent === 'flex-end' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_end' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="20" y1="4" x2="20" y2="20" /><rect x="6" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /><rect x="12" y="6" width="4" height="12" rx="1" fill="currentColor" stroke="none" /></svg></button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="justifyContent === 'space-between' ? 'bg-base-200' : ''" x-on:click="updateAttr( { justifyContent: 'space-between' } )" :aria-pressed="( justifyContent === 'space-between' ).toString()" aria-label="{{ __( 'visual-editor::ve.justify_space_between' ) }}"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="4" x2="4" y2="20" /><line x1="20" y1="4" x2="20" y2="20" /><rect x="7" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /><rect x="13" y="8" width="4" height="8" rx="1" fill="currentColor" stroke="none" /></svg></button>
					</div>
				</x-ve-panel-row>

				{{-- Orientation toggle --}}
				<x-ve-panel-row :label="__( 'visual-editor::ve.orientation' )">
					<div class="flex items-center gap-0.5" role="group" aria-label="{{ __( 'visual-editor::ve.orientation' ) }}">
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'row' === flexDirection ? 'bg-base-200' : ''" x-on:click="updateAttr( { flexDirection: 'row', _groupVariation: 'row' } )" title="{{ __( 'visual-editor::ve.orientation_horizontal' ) }}" aria-label="{{ __( 'visual-editor::ve.orientation_horizontal' ) }}" :aria-pressed="( 'row' === flexDirection ).toString()"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg></button>
						<button type="button" class="btn btn-ghost btn-xs btn-square" :class="'column' === flexDirection ? 'bg-base-200' : ''" x-on:click="updateAttr( { flexDirection: 'column', _groupVariation: 'stack' } )" title="{{ __( 'visual-editor::ve.orientation_vertical' ) }}" aria-label="{{ __( 'visual-editor::ve.orientation_vertical' ) }}" :aria-pressed="( 'column' === flexDirection ).toString()"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" /></svg></button>
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
