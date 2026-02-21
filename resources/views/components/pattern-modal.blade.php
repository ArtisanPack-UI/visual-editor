{{--
 * Pattern Modal Component
 *
 * Full-screen modal for browsing and selecting patterns.
 * Uses a native HTML dialog element driven by Alpine.js.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<dialog
	id="{{ $uuid }}"
	x-data="{
		open: false,
		search: '',
		activeCategory: 'all',
		_dialog: null,
		patterns: {{ Js::from( $patterns ) }},
		categories: {{ Js::from( $categories ) }},

		init() {
			this._dialog = this.$el;
		},

		get filteredPatterns() {
			let result = this.patterns;

			if ( 'all' !== this.activeCategory ) {
				result = result.filter( ( p ) => p.category === this.activeCategory );
			}

			if ( this.search.trim().length > 0 ) {
				const query = this.search.trim().toLowerCase();
				result = result.filter( ( p ) => ( p.name || '' ).toLowerCase().includes( query ) );
			}

			return result;
		},

		openModal() {
			this.open = true;
			this._dialog.showModal();
		},

		closeModal() {
			this.open = false;
			this._dialog.close();
		},

		selectPattern( pattern ) {
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).insertPattern( pattern );
			}
			this.closeModal();
		},
	}"
	x-on:ve-open-pattern-modal.window="openModal()"
	x-on:close="open = false"
	{{ $attributes->merge( [ 'class' => 'modal' ] ) }}
	aria-label="{{ __( 'visual-editor::ve.pattern_modal_title' ) }}"
>
	<div class="modal-box max-w-6xl w-full h-[80vh] p-0 flex flex-col">
		{{-- Header --}}
		<div class="flex items-center justify-between px-6 py-4 border-b border-base-300 shrink-0">
			<h2 class="text-lg font-semibold">{{ __( 'visual-editor::ve.pattern_modal_title' ) }}</h2>
			<div class="flex items-center gap-3">
				<input
					type="text"
					class="input input-sm w-64"
					x-model.debounce.300ms="search"
					placeholder="{{ __( 'visual-editor::ve.search_patterns' ) }}"
					aria-label="{{ __( 'visual-editor::ve.search_patterns' ) }}"
				/>
				<button
					type="button"
					class="btn btn-ghost btn-sm btn-square"
					x-on:click="closeModal()"
					:aria-label="{{ Js::from( __( 'visual-editor::ve.close' ) ) }}"
				>
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
				</button>
			</div>
		</div>

		{{-- Body: category sidebar + pattern grid --}}
		<div class="flex flex-1 min-h-0">
			{{-- Category sidebar --}}
			<nav class="w-48 shrink-0 border-r border-base-300 overflow-y-auto p-3 space-y-1">
				<button
					type="button"
					class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
					:class="'all' === activeCategory ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-base-200 text-base-content/70'"
					x-on:click="activeCategory = 'all'"
				>
					{{ __( 'visual-editor::ve.all_pattern_categories' ) }}
				</button>
				<template x-for="cat in categories" :key="cat.slug">
					<button
						type="button"
						class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
						:class="cat.slug === activeCategory ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-base-200 text-base-content/70'"
						x-on:click="activeCategory = cat.slug"
						x-text="cat.label"
					></button>
				</template>
			</nav>

			{{-- Pattern grid --}}
			<div class="flex-1 overflow-y-auto p-6">
				<template x-if="filteredPatterns.length > 0">
					<div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
						<template x-for="( pattern, patternIndex ) in filteredPatterns" :key="pattern.category + '-' + pattern.name + '-' + patternIndex">
							<button
								type="button"
								class="text-left rounded-lg border border-base-300 overflow-hidden hover:border-primary/40 hover:shadow-md transition-all group cursor-pointer"
								x-on:click="selectPattern( pattern )"
								:aria-label="{{ Js::from( __( 'visual-editor::ve.select_pattern' ) ) }} + ': ' + pattern.name"
							>
								{{-- Preview area --}}
								<div class="aspect-video bg-base-200 flex items-center justify-center p-4">
									<template x-if="pattern.preview">
										<img :src="pattern.preview" :alt="pattern.name" class="max-w-full max-h-full object-contain" />
									</template>
									<template x-if="! pattern.preview">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-base-content/20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" /></svg>
									</template>
								</div>
								{{-- Label --}}
								<div class="p-3 border-t border-base-300">
									<div class="text-sm font-medium group-hover:text-primary transition-colors" x-text="pattern.name"></div>
									<div class="text-xs text-base-content/50 mt-0.5" x-text="pattern.description || ''"></div>
								</div>
							</button>
						</template>
					</div>
				</template>
				<template x-if="filteredPatterns.length === 0">
					<div class="flex items-center justify-center h-full">
						<p class="text-base-content/40 italic">
							{{ __( 'visual-editor::ve.no_patterns_found' ) }}
						</p>
					</div>
				</template>
			</div>
		</div>
	</div>

	{{-- Backdrop close --}}
	<form method="dialog" class="modal-backdrop">
		<button type="submit" x-on:click="open = false">{{ __( 'visual-editor::ve.close' ) }}</button>
	</form>
</dialog>
