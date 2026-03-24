{{--
 * Shared Block Toolbar Controls
 *
 * Block alignment, text alignment, and text formatting controls shared
 * across all editor variants (Editor, TemplateEditor, TemplatePartEditor,
 * PatternEditor). Include this partial inside <x-ve-block-toolbar> after
 * the transform dropdown and before the custom toolbar HTML loop.
 *
 * Required variables: $blockAlignSupports
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

{{-- Block alignment dropdown (visible only for blocks that support align) --}}
<template x-if="(() => {
	if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
	const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
	if ( ! block ) return false;
	const _bas = {{ Js::from( $blockAlignSupports ) }};
	const supports = _bas[ block.type ];
	return supports && supports.length > 0;
})()">
	<div
		x-data="{
			alignOpen: false,
			blockAlignSupports: {{ Js::from( $blockAlignSupports ) }},
			alignIcons: {
				none: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><rect x=\'4\' y=\'4\' width=\'16\' height=\'16\' rx=\'1\' stroke=\'currentColor\' stroke-width=\'2\' /></svg>',
				left: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><rect x=\'1\' y=\'5\' width=\'12\' height=\'14\' rx=\'1\' fill=\'currentColor\' /><line x1=\'23\' y1=\'5\' x2=\'23\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /></svg>',
				center: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><line x1=\'1\' y1=\'5\' x2=\'1\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /><rect x=\'6\' y=\'5\' width=\'12\' height=\'14\' rx=\'1\' fill=\'currentColor\' /><line x1=\'23\' y1=\'5\' x2=\'23\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /></svg>',
				right: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><line x1=\'1\' y1=\'5\' x2=\'1\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /><rect x=\'11\' y=\'5\' width=\'12\' height=\'14\' rx=\'1\' fill=\'currentColor\' /></svg>',
				wide: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><line x1=\'1\' y1=\'5\' x2=\'1\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /><rect x=\'3\' y=\'8\' width=\'18\' height=\'8\' rx=\'1\' fill=\'currentColor\' /><line x1=\'23\' y1=\'5\' x2=\'23\' y2=\'19\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' /></svg>',
				full: '<svg class=\'w-3.5 h-3.5\' viewBox=\'0 0 24 24\' fill=\'none\'><rect x=\'1\' y=\'5\' width=\'22\' height=\'14\' rx=\'1\' fill=\'currentColor\' /></svg>',
			},
			alignLabels: {
				none: '{{ __( 'visual-editor::ve.align_none' ) }}',
				left: '{{ __( 'visual-editor::ve.align_left' ) }}',
				center: '{{ __( 'visual-editor::ve.align_center' ) }}',
				right: '{{ __( 'visual-editor::ve.align_right' ) }}',
				wide: '{{ __( 'visual-editor::ve.align_wide' ) }}',
				full: '{{ __( 'visual-editor::ve.align_full' ) }}',
			},

			get supportedAligns() {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( ! blockId || ! Alpine.store( 'editor' ) ) return [ 'none' ];
				const block = Alpine.store( 'editor' ).getBlock( blockId );
				if ( ! block ) return [ 'none' ];
				let options = this.blockAlignSupports[ block.type ] || [];
				if ( options.indexOf( 'none' ) === -1 ) {
					options = [ 'none', ...options ];
				}
				return options;
			},

			get currentAlign() {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'none';
				const block = Alpine.store( 'editor' ).getBlock( blockId );
				return block?.attributes?.align ?? 'none';
			},

			setAlign( value ) {
				const blockId = Alpine.store( 'selection' )?.focused;
				if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
				Alpine.store( 'editor' ).updateBlock( blockId, { align: value } );
				this.alignOpen = false;
				if ( Alpine.store( 'announcer' ) ) {
					Alpine.store( 'announcer' ).announce( '{{ __( 'visual-editor::ve.block_alignment_changed' , [ 'alignment' => ':alignment' ] ) }}'.replace( ':alignment', this.alignLabels[ value ] || value ) );
				}
			},
		}"
		class="relative flex items-center"
	>
		<div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

		<button
			type="button"
			class="btn btn-ghost btn-xs gap-0.5 px-1.5"
			x-on:click="alignOpen = ! alignOpen"
			:aria-expanded="alignOpen"
			aria-haspopup="listbox"
			aria-label="{{ __( 'visual-editor::ve.block_alignment' ) }}"
			title="{{ __( 'visual-editor::ve.block_alignment' ) }}"
		>
			<span class="flex items-center justify-center" x-html="alignIcons[ currentAlign ] || alignIcons.none"></span>
			<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
			</svg>
		</button>

		<div
			x-show="alignOpen"
			x-on:click.outside="alignOpen = false"
			x-transition
			class="absolute left-0 top-full mt-1 w-44 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50"
			role="listbox"
			aria-label="{{ __( 'visual-editor::ve.block_alignment' ) }}"
		>
			<template x-for="opt in supportedAligns" :key="opt">
				<button
					type="button"
					class="flex w-full items-center gap-2 px-3 py-1.5 text-sm hover:bg-base-200"
					:class="opt === currentAlign ? 'bg-primary/10 text-primary' : ''"
					role="option"
					:aria-selected="opt === currentAlign ? 'true' : 'false'"
					x-on:click="setAlign( opt )"
				>
					<span class="flex items-center justify-center shrink-0" x-html="alignIcons[ opt ] || alignIcons.none"></span>
					<span x-text="alignLabels[ opt ] || opt"></span>
				</button>
			</template>
		</div>
	</div>
</template>

{{-- Text alignment controls (shown for blocks that declare textAlignment support) --}}
<template x-if="(() => {
	if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
	const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
	if ( ! block ) return false;
	const meta = Alpine.store( 'blockRenderers' ).getMeta( block.type );
	return meta?.textAlignment === true;
})()">
	<div class="contents">
		<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

		<div
			x-data="{
				get currentAlignment() {
					const blockId = Alpine.store( 'selection' )?.focused;
					if ( ! blockId || ! Alpine.store( 'editor' ) ) return 'left';
					const block = Alpine.store( 'editor' ).getBlock( blockId );
					return block?.attributes?.alignment ?? 'left';
				},
				setAlignment( value ) {
					const blockId = Alpine.store( 'selection' )?.focused;
					if ( ! blockId || ! Alpine.store( 'editor' ) ) return;
					Alpine.store( 'editor' ).updateBlock( blockId, { alignment: value } );
				},
			}"
			class="flex items-center"
		>
			<button
				type="button"
				class="btn btn-ghost btn-xs btn-square"
				:class="currentAlignment === 'left' ? 'bg-base-200 text-base-content' : ''"
				x-on:click="setAlignment( 'left' )"
				aria-label="{{ __( 'visual-editor::ve.align_left' ) }}"
				title="{{ __( 'visual-editor::ve.align_left' ) }}"
			>
				<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
					<path stroke-linecap="round" d="M3 6h18M3 12h12M3 18h16" />
				</svg>
			</button>
			<button
				type="button"
				class="btn btn-ghost btn-xs btn-square"
				:class="currentAlignment === 'center' ? 'bg-base-200 text-base-content' : ''"
				x-on:click="setAlignment( 'center' )"
				aria-label="{{ __( 'visual-editor::ve.align_center' ) }}"
				title="{{ __( 'visual-editor::ve.align_center' ) }}"
			>
				<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
					<path stroke-linecap="round" d="M3 6h18M6 12h12M4 18h16" />
				</svg>
			</button>
			<button
				type="button"
				class="btn btn-ghost btn-xs btn-square"
				:class="currentAlignment === 'right' ? 'bg-base-200 text-base-content' : ''"
				x-on:click="setAlignment( 'right' )"
				aria-label="{{ __( 'visual-editor::ve.align_right' ) }}"
				title="{{ __( 'visual-editor::ve.align_right' ) }}"
			>
				<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
					<path stroke-linecap="round" d="M3 6h18M9 12h12M5 18h16" />
				</svg>
			</button>
		</div>
	</div>
</template>

{{-- Text formatting controls (shown for blocks that declare textFormatting support) --}}
<template x-if="(() => {
	if ( ! Alpine.store( 'selection' )?.focused || ! Alpine.store( 'editor' ) ) return false;
	const block = Alpine.store( 'editor' ).getBlock( Alpine.store( 'selection' ).focused );
	if ( ! block ) return false;
	const meta = Alpine.store( 'blockRenderers' ).getMeta( block.type );
	return meta?.textFormatting === true;
})()">
	<div
		class="contents"
		x-data="veInlineLinkControl"
		x-on:ve-inline-link-apply.stop="applyInlineLink( $event.detail )"
	>
		<div class="w-px h-4 bg-base-300" aria-hidden="true"></div>

		<x-ve-toolbar-button
			:label="__( 'Bold' )"
			icon="o-bold"
			:tooltip="__( 'Bold' )"
			shortcut="Ctrl+B"
			x-on:click="document.execCommand( 'bold', false, null )"
		/>
		<x-ve-toolbar-button
			:label="__( 'Italic' )"
			icon="o-italic"
			:tooltip="__( 'Italic' )"
			shortcut="Ctrl+I"
			x-on:click="document.execCommand( 'italic', false, null )"
		/>

		<div class="relative">
			<x-ve-toolbar-button
				:label="__( 'Link' )"
				icon="o-link"
				:tooltip="__( 'Link' )"
				shortcut="Ctrl+K"
				x-on:click="openLinkPopover()"
			/>
			<x-ve-link-popover event-name="ve-inline-link-apply" />
		</div>
	</div>
</template>
