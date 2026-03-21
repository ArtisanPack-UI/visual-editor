{{--
 * Style Source Indicator Component
 *
 * Shows where a block's style value comes from in the cascade
 * (global, template, or block override) and provides a reset button.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<span
	x-data="{
		field: {{ Js::from( $field ) }},

		get block() {
			const blockId = $store.selection?.focused;
			if ( ! blockId || ! $store.editor ) return null;
			return $store.editor.getBlock( blockId );
		},

		get hasBlockOverride() {
			if ( ! this.block || ! this.block.attributes ) return false;
			return undefined !== this.block.attributes[ this.field ];
		},

		get source() {
			if ( this.hasBlockOverride ) return 'block';
			return 'global';
		},

		get sourceLabel() {
			const labels = {
				block: {{ Js::from( __( 'visual-editor::ve.style_source_block' ) ) }},
				template: {{ Js::from( __( 'visual-editor::ve.style_source_template' ) ) }},
				global: {{ Js::from( __( 'visual-editor::ve.style_source_global' ) ) }},
			};
			return labels[ this.source ] || labels.global;
		},

		get sourceTooltip() {
			const tooltips = {
				block: {{ Js::from( __( 'visual-editor::ve.style_overridden_by_block' ) ) }},
				template: {{ Js::from( __( 'visual-editor::ve.style_inherited_from_template' ) ) }},
				global: {{ Js::from( __( 'visual-editor::ve.style_inherited_from_global' ) ) }},
			};
			return tooltips[ this.source ] || tooltips.global;
		},

		resetToDefault() {
			const blockId = $store.selection?.focused;
			if ( ! blockId ) return;
			$store.editor.removeBlockAttributes( blockId, [ this.field ] );
		},
	}"
	class="inline-flex items-center gap-1"
	{{ $attributes }}
>
	<span
		class="inline-flex items-center rounded px-1 py-0.5 text-[10px] font-medium leading-none"
		:class="{
			'bg-info/15 text-info': source === 'global',
			'bg-secondary/15 text-secondary': source === 'template',
			'bg-warning/15 text-warning': source === 'block',
		}"
		:title="sourceTooltip"
		x-text="sourceLabel"
	></span>
	<button
		type="button"
		x-show="hasBlockOverride"
		x-cloak
		class="inline-flex items-center justify-center rounded p-0.5 text-base-content/40 hover:text-base-content/80 hover:bg-base-200 transition-colors"
		:title="{{ Js::from( __( 'visual-editor::ve.style_reset_to_inherited' ) ) }}"
		x-on:click.stop="resetToDefault()"
		aria-label="{{ __( 'visual-editor::ve.style_reset_to_inherited' ) }}"
	>
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3 h-3">
			<path fill-rule="evenodd" d="M8 1a.75.75 0 0 1 .75.75v6.5a.75.75 0 0 1-1.5 0v-6.5A.75.75 0 0 1 8 1ZM4.11 3.05a.75.75 0 0 1 0 1.06 5.5 5.5 0 1 0 7.78 0 .75.75 0 0 1 1.06-1.06 7 7 0 1 1-9.9 0 .75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
		</svg>
	</button>
</span>
