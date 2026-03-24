{{--
 * Global Styles Admin Page
 *
 * Split-view layout with style editors on the left and a live
 * CSS preview on the right. Operates with a standalone Alpine
 * store ('editor') that provides the same interface the style
 * editors expect, but without the full document editor overhead.
 *
 * Features:
 * - Real-time CSS custom property injection
 * - Responsive viewport preview (desktop/tablet/mobile)
 * - Preview contexts (default, blog post, archive)
 * - Before/after comparison toggle
 * - Unsaved changes indicator with discard option
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
		viewport: 'desktop',
		previewMode: 'live',

		{{-- Snapshot of the last-saved styles for before/after comparison --}}
		savedStyles: {
			palette: JSON.parse( JSON.stringify( {{ Js::from( $initialPalette ) }} ) ),
			typography: JSON.parse( JSON.stringify( {{ Js::from( $initialTypography ) }} ) ),
			spacing: JSON.parse( JSON.stringify( {{ Js::from( $initialSpacing ) }} ) ),
		},

		get viewportWidth() {
			switch ( this.viewport ) {
				case 'tablet':
					return '768px';
				case 'mobile':
					return '375px';
				default:
					return '100%';
			}
		},

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

			{{-- Listen for save/reset confirmations --}}
			const offSaved = Livewire.on( 've-global-styles-saved', () => {
				this.dirty      = false;
				this.saving     = false;
				this.saveStatus = 'saved';

				{{-- Snapshot the new saved state --}}
				const store = Alpine.store( 'editor' );
				this.savedStyles = {
					palette: JSON.parse( JSON.stringify( store.globalStyles.palette ) ),
					typography: JSON.parse( JSON.stringify( store.globalStyles.typography ) ),
					spacing: JSON.parse( JSON.stringify( store.globalStyles.spacing ) ),
				};

				setTimeout( () => { this.saveStatus = 'idle'; }, 2000 );
			} );

			const offReset = Livewire.on( 've-global-styles-reset', ( data ) => {
				if ( data && data[0] ) {
					const d     = data[0];
					const store = Alpine.store( 'editor' );
					if ( d.palette ) store.globalStyles.palette       = d.palette;
					if ( d.typography ) store.globalStyles.typography = d.typography;
					if ( d.spacing ) store.globalStyles.spacing       = d.spacing;
					store._syncGlobalCssVariables();

					{{-- Update the saved snapshot --}}
					this.savedStyles = {
						palette: JSON.parse( JSON.stringify( store.globalStyles.palette ) ),
						typography: JSON.parse( JSON.stringify( store.globalStyles.typography ) ),
						spacing: JSON.parse( JSON.stringify( store.globalStyles.spacing ) ),
					};
				}
				this.dirty       = false;
				this.previewMode = 'live';
				this.saveStatus  = 'idle';
			} );

			{{-- Teardown listeners when navigating away --}}
			document.addEventListener( 'livewire:navigating', () => {
				window.removeEventListener( 've-palette-change', markDirty );
				window.removeEventListener( 've-typography-change', markDirty );
				window.removeEventListener( 've-spacing-change', markDirty );
				document.removeEventListener( 've-store-dirty', markDirty );
				offSaved();
				offReset();
			}, { once: true } );
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

		handleDiscard() {
			$wire.discardChanges();
		},

		{{-- Before/After: switch to live (pending) preview --}}
		switchToLivePreview() {
			if ( 'live' === this.previewMode ) return;
			this.previewMode = 'live';
			const store = Alpine.store( 'editor' );
			store._syncGlobalCssVariables();
		},

		{{-- Before/After: switch to saved (before) preview --}}
		switchToSavedPreview() {
			if ( 'saved' === this.previewMode ) return;
			this.previewMode = 'saved';
			this._applySavedCssVariables();
		},

		{{-- Apply saved styles' CSS variables to the document root --}}
		_applySavedCssVariables() {
			const root = document.documentElement;
			const ss   = this.savedStyles;

			if ( ss.palette && Array.isArray( ss.palette ) ) {
				ss.palette.forEach( entry => {
					if ( entry.slug && entry.color ) {
						root.style.setProperty( '--ve-color-' + entry.slug, entry.color );
					}
				} );
			}

			if ( ss.typography && ss.typography.fontFamilies ) {
				Object.entries( ss.typography.fontFamilies ).forEach( ( [ slot, value ] ) => {
					root.style.setProperty( '--ve-font-' + slot, value );
				} );
			}

			if ( ss.typography && ss.typography.elements ) {
				Object.entries( ss.typography.elements ).forEach( ( [ element, props ] ) => {
					if ( typeof props === 'object' && props !== null ) {
						Object.entries( props ).forEach( ( [ prop, value ] ) => {
							const kebab = prop.replace( /([a-z])([A-Z])/g, '$1-$2' ).toLowerCase();
							root.style.setProperty( '--ve-text-' + element + '-' + kebab, value );
						} );
					}
				} );
			}

			if ( ss.spacing && ss.spacing.scale && Array.isArray( ss.spacing.scale ) ) {
				ss.spacing.scale.forEach( step => {
					if ( step.slug && step.value ) {
						root.style.setProperty( '--ve-spacing-' + step.slug, step.value );
					}
				} );
			}

			if ( ss.spacing && ss.spacing.blockGap ) {
				let gapValue = ss.spacing.blockGap;
				if ( ss.spacing.scale && Array.isArray( ss.spacing.scale ) ) {
					const match = ss.spacing.scale.find( s => s.slug === gapValue );
					if ( match ) gapValue = match.value;
				}
				root.style.setProperty( '--ve-block-gap', gapValue );
			}
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

				{{-- Unsaved changes indicator --}}
				<span
					x-show="dirty"
					x-transition
					x-cloak
					class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-warning-content bg-warning/80 rounded-full"
					aria-live="polite"
				>
					<svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8" aria-hidden="true" focusable="false">
						<circle cx="4" cy="4" r="4" />
					</svg>
					{{ __( 'visual-editor::ve.style_preview_unsaved' ) }}
				</span>
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

				{{-- Discard button --}}
				<button
					type="button"
					x-on:click="handleDiscard()"
					x-show="dirty"
					x-cloak
					class="px-3 py-1.5 text-xs font-medium text-error bg-error/10 rounded-lg hover:bg-error/20 transition-colors"
				>{{ __( 'visual-editor::ve.style_preview_discard' ) }}</button>

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

	{{-- Right panel: Live Preview --}}
	<div class="flex-1 min-w-0 flex flex-col bg-gray-100" data-theme="light">
		{{-- Preview toolbar --}}
		<x-ve-style-preview-toolbar />

		{{-- Preview content area with responsive viewport --}}
		<div class="flex-1 overflow-y-auto p-8">
			<div
				class="mx-auto transition-all duration-300 ease-in-out space-y-4"
				:style="{ width: viewportWidth, maxWidth: '100%' }"
			>
				{{-- Section: Style Overview (colors, typography, spacing) --}}
				@include( 'visual-editor::components._style-preview-section', [
					'title'   => __( 'visual-editor::ve.style_preview_section_overview' ),
					'id'      => 'overview',
					'open'    => true,
					'badge'   => '',
					'content' => 'visual-editor::components._style-preview-context-default',
				] )

				{{-- Section: Template Parts (grouped by area) --}}
				@if ( [] !== $previewParts )
					@php
						$totalParts = collect( $previewParts )->flatten( 1 )->count();
					@endphp

					@include( 'visual-editor::components._style-preview-section', [
						'title'   => __( 'visual-editor::ve.style_preview_section_parts' ),
						'id'      => 'parts',
						'open'    => true,
						'badge'   => (string) $totalParts,
						'content' => 'visual-editor::components._style-preview-parts',
					] )
				@endif

				{{-- Section: Patterns (grouped by category) --}}
				@if ( [] !== $previewPatterns )
					@php
						$totalPatterns = collect( $previewPatterns )->flatten( 1 )->count();
					@endphp

					@include( 'visual-editor::components._style-preview-section', [
						'title'   => __( 'visual-editor::ve.style_preview_section_patterns' ),
						'id'      => 'patterns',
						'open'    => true,
						'badge'   => (string) $totalPatterns,
						'content' => 'visual-editor::components._style-preview-patterns',
					] )
				@endif

				{{-- Empty state when no patterns or parts exist --}}
				@if ( [] === $previewParts && [] === $previewPatterns )
					<div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
						<p class="text-sm text-gray-400">
							{{ __( 'visual-editor::ve.style_preview_no_content' ) }}
						</p>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>
