{{--
 * Global Styles Admin Page
 *
 * Split-view layout with style editors on the left and a live
 * CSS preview on the right. Operates with a standalone Alpine
 * store ('editor') that provides the same interface the style
 * editors expect, but without the full document editor overhead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Livewire\SiteEditor
 *
 * @since      1.0.0
 --}}

@php
	$initialPalette    = $palette;
	$initialTypography = $typography;
	$initialSpacing    = $spacing;
@endphp

<div
	x-data="{
		dirty: false,
		saving: false,
		saveStatus: 'idle',

		init() {
			{{-- Register a lightweight editor store for the style editors --}}
			if ( ! Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor', {
					globalStyles: {
						palette: JSON.parse( JSON.stringify( {{ Js::from( $initialPalette ) }} ) ),
						typography: JSON.parse( JSON.stringify( {{ Js::from( $initialTypography ) }} ) ),
						spacing: JSON.parse( JSON.stringify( {{ Js::from( $initialSpacing ) }} ) ),
					},
					_lastCssVars: [],

					_pushHistory() {},

					markDirty() {
						document.dispatchEvent( new CustomEvent( 've-store-dirty', { bubbles: true } ) );
					},

					_dispatchChange() {
						document.dispatchEvent( new CustomEvent( 've-store-dirty', { bubbles: true } ) );
					},

					setPalette( palette ) {
						this.globalStyles.palette = palette;
						this._syncGlobalCssVariables();
					},

					getPalette() {
						return this.globalStyles.palette;
					},

					setTypography( typography ) {
						this.globalStyles.typography = typography;
						this._syncGlobalCssVariables();
					},

					getTypography() {
						return this.globalStyles.typography;
					},

					setSpacing( spacing ) {
						this.globalStyles.spacing = spacing;
						this._syncGlobalCssVariables();
					},

					getSpacing() {
						return this.globalStyles.spacing;
					},

					_syncGlobalCssVariables() {
						const root    = document.documentElement;
						const newVars = [];
						const gs      = this.globalStyles;

						{{-- Colors --}}
						if ( gs.palette && Array.isArray( gs.palette ) ) {
							gs.palette.forEach( entry => {
								if ( entry.slug && entry.color ) {
									const varName = '--ve-color-' + entry.slug;
									root.style.setProperty( varName, entry.color );
									newVars.push( varName );
								}
							} );
						}

						{{-- Typography font families --}}
						if ( gs.typography && gs.typography.fontFamilies ) {
							Object.entries( gs.typography.fontFamilies ).forEach( ( [ slot, value ] ) => {
								const varName = '--ve-font-' + slot;
								root.style.setProperty( varName, value );
								newVars.push( varName );
							} );
						}

						{{-- Typography elements --}}
						if ( gs.typography && gs.typography.elements ) {
							Object.entries( gs.typography.elements ).forEach( ( [ element, props ] ) => {
								if ( typeof props === 'object' && props !== null ) {
									Object.entries( props ).forEach( ( [ prop, value ] ) => {
										const kebab   = prop.replace( /([a-z])([A-Z])/g, '$1-$2' ).toLowerCase();
										const varName = '--ve-text-' + element + '-' + kebab;
										root.style.setProperty( varName, value );
										newVars.push( varName );
									} );
								}
							} );
						}

						{{-- Spacing scale --}}
						if ( gs.spacing && gs.spacing.scale && Array.isArray( gs.spacing.scale ) ) {
							gs.spacing.scale.forEach( step => {
								if ( step.slug && step.value ) {
									const varName = '--ve-spacing-' + step.slug;
									root.style.setProperty( varName, step.value );
									newVars.push( varName );
								}
							} );
						}

						{{-- Spacing custom steps --}}
						if ( gs.spacing && gs.spacing.customSteps && Array.isArray( gs.spacing.customSteps ) ) {
							gs.spacing.customSteps.forEach( step => {
								if ( step.slug && step.value ) {
									const varName = '--ve-spacing-' + step.slug;
									root.style.setProperty( varName, step.value );
									newVars.push( varName );
								}
							} );
						}

						{{-- Block gap --}}
						if ( gs.spacing && gs.spacing.blockGap ) {
							let gapValue = gs.spacing.blockGap;
							if ( gs.spacing.scale && Array.isArray( gs.spacing.scale ) ) {
								const match = gs.spacing.scale.find( s => s.slug === gapValue );
								if ( match ) gapValue = match.value;
							}
							root.style.setProperty( '--ve-block-gap', gapValue );
							newVars.push( '--ve-block-gap' );
						}

						{{-- Remove stale vars --}}
						this._lastCssVars.forEach( varName => {
							if ( ! newVars.includes( varName ) ) {
								root.style.removeProperty( varName );
							}
						} );

						this._lastCssVars = newVars;
					},
				} );
			}

			{{-- Initial CSS sync --}}
			Alpine.store( 'editor' )._syncGlobalCssVariables();

			{{-- Listen for editor changes to mark dirty --}}
			const markDirty = () => { this.dirty = true; };
			window.addEventListener( 've-palette-change', markDirty );
			window.addEventListener( 've-typography-change', markDirty );
			window.addEventListener( 've-spacing-change', markDirty );
			document.addEventListener( 've-store-dirty', markDirty );

			this.$cleanup( () => {
				window.removeEventListener( 've-palette-change', markDirty );
				window.removeEventListener( 've-typography-change', markDirty );
				window.removeEventListener( 've-spacing-change', markDirty );
				document.removeEventListener( 've-store-dirty', markDirty );
			} );

			{{-- Listen for save/reset confirmations --}}
			Livewire.on( 've-global-styles-saved', () => {
				this.dirty      = false;
				this.saving     = false;
				this.saveStatus = 'saved';
				setTimeout( () => { this.saveStatus = 'idle'; }, 2000 );
			} );

			Livewire.on( 've-global-styles-reset', ( data ) => {
				if ( data && data[0] ) {
					const d     = data[0];
					const store = Alpine.store( 'editor' );
					if ( d.palette ) store.globalStyles.palette       = d.palette;
					if ( d.typography ) store.globalStyles.typography = d.typography;
					if ( d.spacing ) store.globalStyles.spacing       = d.spacing;
					store._syncGlobalCssVariables();
				}
				this.dirty      = false;
				this.saveStatus = 'idle';
			} );
		},

		handleSave() {
			this.saving = true;
			const store = Alpine.store( 'editor' );
			$wire.save(
				store.globalStyles.palette,
				store.globalStyles.typography,
				store.globalStyles.spacing,
			);
		},
	}"
	class="flex h-full"
>
	{{-- Left panel: Editors --}}
	<div class="flex flex-col shrink-0 grow-0 border-r border-base-300 bg-base-100 overflow-y-auto overflow-x-hidden" style="width: 420px; min-width: 420px; max-width: 420px;">
		{{-- Header with actions --}}
		<div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
			<div class="flex items-center gap-2">
				<a
					href="{{ route( 'visual-editor.site-editor' ) }}"
					class="flex items-center gap-1 text-sm text-base-content/60 hover:text-base-content transition-colors"
					aria-label="{{ __( 'visual-editor::ve.back_to_hub' ) }}"
				>
					<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
					</svg>
				</a>
				<h1 class="text-lg font-semibold text-base-content">
					{{ __( 'visual-editor::ve.global_styles_title' ) }}
				</h1>
			</div>

			<div class="flex items-center gap-2">
				{{-- Save status indicator --}}
				<span
					x-show="'saved' === saveStatus"
					x-transition
					class="text-xs text-success"
				>{{ __( 'visual-editor::ve.global_styles_saved' ) }}</span>

				{{-- History button --}}
				<button
					type="button"
					wire:click="toggleHistory"
					class="p-1.5 rounded-lg text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
					title="{{ __( 'visual-editor::ve.global_styles_history' ) }}"
				>
					<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
					</svg>
				</button>

				{{-- Reset button --}}
				<button
					type="button"
					wire:click="resetToDefaults"
					wire:confirm="{{ __( 'visual-editor::ve.global_styles_reset_confirm' ) }}"
					class="px-3 py-1.5 text-xs font-medium text-base-content/70 bg-base-200 rounded-lg hover:bg-base-300 transition-colors"
				>{{ __( 'visual-editor::ve.global_styles_reset' ) }}</button>

				{{-- Save button --}}
				<button
					type="button"
					x-on:click="handleSave()"
					x-bind:disabled="! dirty || saving"
					class="px-3 py-1.5 text-xs font-medium text-primary-content bg-primary rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
				>
					<span x-show="! saving">{{ __( 'visual-editor::ve.global_styles_save' ) }}</span>
					<span x-show="saving" x-cloak>{{ __( 'visual-editor::ve.global_styles_saving' ) }}</span>
				</button>
			</div>
		</div>

		{{-- Revision history panel --}}
		@if ( $showHistory )
			<div class="border-b border-base-300 bg-base-50">
				<div class="px-4 py-2 text-xs font-semibold text-base-content/60 uppercase tracking-wider">
					{{ __( 'visual-editor::ve.global_styles_history' ) }}
				</div>

				@if ( [] === $revisions )
					<div class="px-4 py-3 text-sm text-base-content/50">
						{{ __( 'visual-editor::ve.global_styles_no_revisions' ) }}
					</div>
				@else
					<ul class="max-h-48 overflow-y-auto divide-y divide-base-200" role="list">
						@foreach ( $revisions as $revision )
							<li class="flex items-center justify-between px-4 py-2 hover:bg-base-100">
								<span class="text-sm text-base-content/70">
									{{ $revision['created_at'] }}
								</span>
								<button
									type="button"
									wire:click="restoreRevision({{ $revision['id'] }})"
									wire:confirm="{{ __( 'visual-editor::ve.global_styles_restore_confirm' ) }}"
									class="text-xs text-primary hover:underline"
								>{{ __( 'visual-editor::ve.global_styles_restore' ) }}</button>
							</li>
						@endforeach
					</ul>
				@endif
			</div>
		@endif

		{{-- Style editors --}}
		<div class="flex-1 overflow-y-auto overflow-x-hidden">
			{{-- Color Palette --}}
			<div class="border-b border-base-300 px-5 py-5">
				<x-ve-color-palette-editor :palette="$palette" />
			</div>

			{{-- Typography Presets --}}
			<div class="border-b border-base-300 px-5 py-5">
				<x-ve-typography-presets-editor :typography="$typography" />
			</div>

			{{-- Spacing Scale --}}
			<div class="px-5 py-5">
				<x-ve-spacing-scale-editor :spacing="$spacing" />
			</div>
		</div>
	</div>

	{{-- Right panel: Live Preview (forced light mode) --}}
	<div class="flex-1 min-w-0 overflow-y-auto bg-gray-100 p-8" data-theme="light">
		<div class="max-w-3xl mx-auto space-y-8">
			{{-- Color swatches preview --}}
			<div class="bg-white rounded-xl border border-gray-200 p-6">
				<h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
					{{ __( 'visual-editor::ve.global_styles_preview_colors' ) }}
				</h2>
				<div class="grid grid-cols-4 gap-3 sm:grid-cols-6" x-data>
					<template x-for="entry in Alpine.store( 'editor' )?.globalStyles?.palette || []" :key="entry.slug">
						<div class="flex flex-col items-center gap-1.5">
							<div
								class="w-12 h-12 rounded-lg border border-gray-200 shadow-sm"
								x-bind:style="'background-color:' + entry.color"
								x-bind:title="entry.name"
							></div>
							<span class="text-[10px] text-gray-400 truncate max-w-[60px]" x-text="entry.name"></span>
						</div>
					</template>
				</div>
			</div>

			{{-- Typography preview --}}
			<div class="bg-white rounded-xl border border-gray-200 p-6">
				<h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
					{{ __( 'visual-editor::ve.global_styles_preview_typography' ) }}
				</h2>
				<div class="space-y-3 text-gray-900">
					<h1 style="font-family: var(--ve-font-heading, inherit); font-size: var(--ve-text-h1-font-size, 2.25rem); font-weight: var(--ve-text-h1-font-weight, 800); line-height: var(--ve-text-h1-line-height, 1.2);">
						{{ __( 'visual-editor::ve.global_styles_preview_heading_1' ) }}
					</h1>
					<h2 style="font-family: var(--ve-font-heading, inherit); font-size: var(--ve-text-h2-font-size, 1.875rem); font-weight: var(--ve-text-h2-font-weight, 700); line-height: var(--ve-text-h2-line-height, 1.3);">
						{{ __( 'visual-editor::ve.global_styles_preview_heading_2' ) }}
					</h2>
					<h3 style="font-family: var(--ve-font-heading, inherit); font-size: var(--ve-text-h3-font-size, 1.5rem); font-weight: var(--ve-text-h3-font-weight, 600); line-height: var(--ve-text-h3-line-height, 1.4);">
						{{ __( 'visual-editor::ve.global_styles_preview_heading_3' ) }}
					</h3>
					<p style="font-family: var(--ve-font-body, inherit); font-size: var(--ve-text-body-font-size, 1rem); line-height: var(--ve-text-body-line-height, 1.6);">
						{{ __( 'visual-editor::ve.global_styles_preview_body_text' ) }}
					</p>
					<p style="font-family: var(--ve-font-body, inherit); font-size: var(--ve-text-small-font-size, 0.875rem); line-height: var(--ve-text-small-line-height, 1.5);" class="text-gray-400">
						{{ __( 'visual-editor::ve.global_styles_preview_small_text' ) }}
					</p>
				</div>
			</div>

			{{-- Spacing preview --}}
			<div class="bg-white rounded-xl border border-gray-200 p-6">
				<h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
					{{ __( 'visual-editor::ve.global_styles_preview_spacing' ) }}
				</h2>
				<div class="space-y-2" x-data>
					<template x-for="step in Alpine.store( 'editor' )?.globalStyles?.spacing?.scale || []" :key="step.slug">
						<div class="flex items-center gap-3">
							<span class="w-8 text-xs text-gray-400 text-right tabular-nums" x-text="step.slug"></span>
							<div
								class="h-4 rounded bg-blue-100 border border-blue-200"
								x-bind:style="'width:' + step.value"
							></div>
							<span class="text-xs text-gray-300 tabular-nums" x-text="step.value"></span>
						</div>
					</template>
				</div>
			</div>
		</div>
	</div>
</div>
