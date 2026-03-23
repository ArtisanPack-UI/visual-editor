{{--
 * Global Styles State Component
 *
 * Registers a standalone Alpine.store('globalStyles') that provides the
 * same interface the style editors expect, without the full editor overhead.
 * Style editors detect this store via _getStore() when the full editor
 * store is not present.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$initialPalette    = $paletteEntries;
	$initialTypography = $typographyData;
	$initialSpacing    = $spacingData;
@endphp

<div
	x-data="{
		init() {
			if ( ! Alpine.store( 'globalStyles' ) ) {
				Alpine.store( 'globalStyles', {
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
			Alpine.store( 'globalStyles' )._syncGlobalCssVariables();
		},
	}"
	class="hidden"
	aria-hidden="true"
></div>
