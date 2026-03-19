{{--
 * Template Switcher Component
 *
 * Dropdown for selecting and switching between available templates
 * in the editor toolbar.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		open: false,
		currentSlug: {{ Js::from( $currentSlug ) }},
		templates: {{ Js::from( $templates ) }},

		get currentName() {
			const tpl = this.templates.find( ( t ) => t.slug === this.currentSlug );
			return tpl ? tpl.name : {{ Js::from( __( 'visual-editor::ve.template_select' ) ) }};
		},

		selectTemplate( slug ) {
			this.currentSlug = slug;
			this.open = false;
			this.$dispatch( 've-template-switched', { slug: slug } );

			if ( Alpine.store( 'announcer' ) ) {
				const tpl = this.templates.find( ( t ) => t.slug === slug );
				Alpine.store( 'announcer' ).announce(
					{{ Js::from( __( 'visual-editor::ve.template_switched_to', [ 'name' => '__NAME__' ] ) ) }}.replace( '__NAME__', () => tpl ? tpl.name : slug )
				);
			}
		},
	}"
	{{ $attributes->merge( [ 'class' => 'relative' ] ) }}
>
	{{-- Trigger Button --}}
	<button
		type="button"
		class="btn btn-ghost btn-sm gap-1"
		x-on:click="open = ! open"
		:aria-expanded="open ? 'true' : 'false'"
		aria-haspopup="listbox"
		aria-label="{{ $label ?? __( 'visual-editor::ve.template_switcher' ) }}"
	>
		<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
		</svg>
		<span x-text="currentName" class="max-w-[150px] truncate"></span>
		<svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
		</svg>
	</button>

	{{-- Dropdown List --}}
	<div
		x-show="open"
		x-transition:enter="transition ease-out duration-150"
		x-transition:enter-start="opacity-0 -translate-y-1"
		x-transition:enter-end="opacity-100 translate-y-0"
		x-transition:leave="transition ease-in duration-100"
		x-transition:leave-start="opacity-100 translate-y-0"
		x-transition:leave-end="opacity-0 -translate-y-1"
		x-on:click.outside="open = false"
		x-on:keydown.escape.window="open = false"
		class="absolute top-full left-0 mt-1 z-50 w-64 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-72 overflow-y-auto"
		x-cloak
		role="listbox"
		aria-label="{{ __( 'visual-editor::ve.template_available_templates' ) }}"
	>
		<template x-for="tpl in templates" :key="tpl.slug">
			<button
				type="button"
				class="w-full text-left px-3 py-2 text-sm hover:bg-base-200 transition-colors flex items-center justify-between first:rounded-t-lg last:rounded-b-lg"
				:class="tpl.slug === currentSlug ? 'bg-base-200' : ''"
				x-on:click="selectTemplate( tpl.slug )"
				role="option"
				:aria-selected="tpl.slug === currentSlug ? 'true' : 'false'"
			>
				<div class="min-w-0">
					<div class="font-medium truncate" x-text="tpl.name"></div>
					<div
						x-show="tpl.description"
						class="text-xs text-base-content/50 mt-0.5 truncate"
						x-text="tpl.description"
					></div>
				</div>
				<svg
					x-show="tpl.slug === currentSlug"
					class="w-4 h-4 text-primary shrink-0 ml-2"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor"
					stroke-width="2"
					aria-hidden="true"
				>
					<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
				</svg>
			</button>
		</template>

		<template x-if="0 === templates.length">
			<div class="px-3 py-4 text-sm text-base-content/50 text-center">
				{{ __( 'visual-editor::ve.template_no_templates' ) }}
			</div>
		</template>
	</div>

	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.templateSwitcher.rendered')
	@endif
</div>
