{{--
 * Pattern Browser Component
 *
 * Compact pattern listing for the Patterns tab in the left sidebar.
 * Provides search and collapsible category sections.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$patterns = function_exists( 'applyFilters' )
		? applyFilters( 'ap.visualEditor.patterns.registered', $patterns )
		: $patterns;

	$categories = function_exists( 'applyFilters' )
		? applyFilters( 'ap.visualEditor.patterns.categories', $categories )
		: $categories;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		search: '',
		collapsedCategories: {},
		patterns: {{ Js::from( $patterns ) }},
		categories: {{ Js::from( $categories ) }},

		get filteredPatterns() {
			let result = this.patterns;

			if ( this.search.trim().length > 0 ) {
				const query = this.search.trim().toLowerCase();
				result = result.filter( ( p ) => ( p.name || '' ).toLowerCase().includes( query ) );
			}

			return result;
		},

		get groupedPatterns() {
			const groups = {};
			this.filteredPatterns.forEach( ( pattern ) => {
				const cat = pattern.category || 'uncategorized';
				if ( ! groups[ cat ] ) {
					groups[ cat ] = [];
				}
				groups[ cat ].push( pattern );
			} );
			return groups;
		},

		get hasResults() {
			return this.filteredPatterns.length > 0;
		},

		getCategoryLabel( slug ) {
			const cat = this.categories.find( ( c ) => c.slug === slug );
			return cat ? cat.label : slug;
		},

		toggleCategory( category ) {
			this.collapsedCategories[ category ] = ! this.collapsedCategories[ category ];
		},

		isCategoryCollapsed( category ) {
			return !! this.collapsedCategories[ category ];
		},

		insertPattern( pattern ) {
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).insertPattern( pattern );
			}
		},

		openPatternModal() {
			document.dispatchEvent( new CustomEvent( 've-open-pattern-modal', { bubbles: true } ) );
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col h-full' ] ) }}
	aria-label="{{ __( 'visual-editor::ve.pattern_browser' ) }}"
>
	{{-- Search --}}
	@if ( $showSearch )
		<div class="p-3 border-b border-base-300">
			<input
				type="text"
				class="input input-sm w-full"
				x-model.debounce.300ms="search"
				placeholder="{{ __( 'visual-editor::ve.search_patterns' ) }}"
			/>
		</div>
	@endif

	{{-- Pattern list by category --}}
	<div class="flex-1 overflow-y-auto px-3 py-2">
		<template x-if="hasResults">
			<div>
				<template x-for="( categoryPatterns, categorySlug ) in groupedPatterns" :key="categorySlug">
					<div class="mb-1">
						<button
							type="button"
							class="flex items-center gap-1.5 w-full px-2 py-1.5 text-xs font-semibold text-base-content/60 uppercase tracking-wide hover:text-base-content transition-colors"
							x-on:click="toggleCategory( categorySlug )"
						>
							<svg
								class="w-3.5 h-3.5 transition-transform"
								:class="isCategoryCollapsed( categorySlug ) ? '-rotate-90' : ''"
								fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"
							>
								<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
							</svg>
							<span x-text="getCategoryLabel( categorySlug )"></span>
						</button>
						<div x-show="! isCategoryCollapsed( categorySlug )" x-collapse>
							<div class="space-y-1.5 pb-2">
								<template x-for="pattern in categoryPatterns" :key="pattern.category + '-' + pattern.name">
									<button
										type="button"
										class="w-full text-left p-3 rounded-lg border border-base-300 hover:border-primary/40 hover:bg-base-200 transition-colors"
										x-on:click="insertPattern( pattern )"
										:aria-label="{{ Js::from( __( 'visual-editor::ve.select_pattern' ) ) }} + ': ' + pattern.name"
									>
										<div class="text-sm font-medium" x-text="pattern.name"></div>
										<div class="text-xs text-base-content/50 mt-0.5" x-text="pattern.description || ''"></div>
									</button>
								</template>
							</div>
						</div>
					</div>
				</template>
			</div>
		</template>
		<template x-if="! hasResults">
			<p class="text-sm text-base-content/40 italic text-center py-4">
				{{ __( 'visual-editor::ve.no_patterns_found' ) }}
			</p>
		</template>
	</div>

	{{-- Explore all patterns button --}}
	<div class="p-3 border-t border-base-300">
		<button
			type="button"
			class="btn btn-sm btn-outline btn-primary w-full"
			x-on:click="openPatternModal()"
		>
			{{ __( 'visual-editor::ve.explore_all_patterns' ) }}
		</button>
	</div>
</div>
