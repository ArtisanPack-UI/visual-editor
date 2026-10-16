/**
 * Animation inspector panel (#489).
 *
 * Three sub-panels — Entrance, Hover, Continuous — that surface every
 * animation in their family for the selected block. The panel is
 * mounted into the inspector by `withAnimationAttributes()` (the
 * higher-order component below).
 *
 * Per-family controls:
 *   - Animation dropdown (with "(none)" → clears the attribute)
 *   - Duration (number, ms)
 *   - Delay (number, ms) — entrance + hover only
 *   - Easing (text — accepts `ease`, `linear`, `cubic-bezier(...)`, etc.)
 *   - Threshold (number, 0–1) — entrance only
 *   - Repeat (text/number, `infinite` or N) — continuous only
 *
 * The Animations panel also exposes the global "Respect reduced motion"
 * toggle (default on).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { useSettings } from '@wordpress/block-editor';
import {
	BaseControl,
	ColorPalette,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

import { type AnimationRegistry, animationLabel } from './registry';
import type {
	AnimationsAttribute,
	ContinuousAttribute,
	EntranceAttribute,
	HoverAttribute,
} from './types';
import { FAMILY_CONTINUOUS, FAMILY_ENTRANCE, FAMILY_HOVER } from './types';
import { TEXT_DOMAIN } from '../vendor/i18n';
import { glowColorDisplayValue, glowColorStoredValue, type PaletteColor } from './glow-color';

export interface AnimationPanelProps {
	registry: AnimationRegistry;
	value: AnimationsAttribute | undefined;
	onChange: ( next: AnimationsAttribute ) => void;
	/** Set false when the block declares `supports.artisanpackAnimations: false`. */
	supportsAnimations?: boolean;
}

function dropdownOptions( registry: AnimationRegistry, family: 'entrance' | 'hover' | 'continuous' ) {
	return [
		{ label: __( '(none)', TEXT_DOMAIN ), value: '' },
		...registry.family( family ).map( ( def ) => ( {
			label: animationLabel( def ),
			value: def.key,
		} ) ),
	];
}

function baseString( name: EntranceAttribute['name'] | undefined ): string {
	if ( 'string' === typeof name ) {
		return name;
	}
	if ( name && 'object' === typeof name ) {
		const base = ( name as Record<string, string | null | undefined> ).base;
		return 'string' === typeof base ? base : '';
	}
	return '';
}

/**
 * Parse a NumberControl onChange value into a finite number or
 * `undefined`. Guards against `NaN` (e.g. `Number('')` is 0 but
 * `Number(undefined)` is NaN) from being persisted into the
 * `artisanpackAnimations` bag — NaN duration / threshold breaks
 * `AnimationCssEmitter`'s `intOr` fallback by passing `is_numeric`
 * but rendering as the literal `NaN` in CSS.
 */
function parseNumber( next: unknown ): number | undefined {
	if ( undefined === next || '' === next ) {
		return undefined;
	}
	const parsed = Number( next );
	return Number.isFinite( parsed ) ? parsed : undefined;
}

/**
 * Color list for the glow picker, matching the core color panel: the
 * theme palette, then the default palette unless the theme turned it
 * off with `color.defaultPalette: false`, then the user's custom
 * palette (R-25).
 *
 * @since 1.15.0
 */
export function glowPalette(
	themePalette: PaletteColor[] | undefined,
	defaultPalette: PaletteColor[] | undefined,
	customPalette: PaletteColor[] | undefined,
	showDefaultPalette: boolean | undefined,
): PaletteColor[] {
	return [
		...( themePalette ?? [] ),
		...( false === showDefaultPalette ? [] : ( defaultPalette ?? [] ) ),
		...( customPalette ?? [] ),
	];
}

function GlowColorControl( {
	value,
	onChange,
}: {
	value: string | undefined;
	onChange: ( next: string | undefined ) => void;
} ): JSX.Element {
	const [ themePalette, defaultPalette, customPalette, showDefaultPalette ] = useSettings(
		'color.palette.theme',
		'color.palette.default',
		'color.palette.custom',
		'color.defaultPalette',
	) as [
		PaletteColor[] | undefined,
		PaletteColor[] | undefined,
		PaletteColor[] | undefined,
		boolean | undefined,
	];
	const palette = glowPalette( themePalette, defaultPalette, customPalette, showDefaultPalette );

	// `ColorPalette` takes no `id`, so a `<label for>` would point at
	// nothing. Name the swatch listbox (and the group around the picker)
	// with a visual label instead, and describe the group with the help
	// text, which `BaseControl` renders with the id `${ id }__help`.
	const id      = useInstanceId( GlowColorControl, 'ap-animation-glow-color' ) as string;
	const labelId = `${ id }__label`;
	const helpId  = `${ id }__help`;

	return (
		<BaseControl
			id={ id }
			help={ __( 'Leave unset to use the theme’s glow color.', TEXT_DOMAIN ) }
			__nextHasNoMarginBottom
		>
			<BaseControl.VisualLabel id={ labelId }>
				{ __( 'Glow color', TEXT_DOMAIN ) }
			</BaseControl.VisualLabel>
			<div id={ id } role="group" aria-labelledby={ labelId } aria-describedby={ helpId }>
				<ColorPalette
					aria-labelledby={ labelId }
					colors={ palette.map( ( entry ) => ( { name: entry.name ?? entry.slug, color: entry.color } ) ) }
					value={ glowColorDisplayValue( value, palette ) }
					onChange={ ( next?: string ) => onChange( glowColorStoredValue( next, palette ) ) }
					clearable
				/>
			</div>
		</BaseControl>
	);
}

export function AnimationPanel( {
	registry,
	value,
	onChange,
	supportsAnimations = true,
}: AnimationPanelProps ): JSX.Element | null {
	if ( ! supportsAnimations ) {
		return null;
	}

	const entrance:   EntranceAttribute   = value?.entrance   ?? {};
	const hover:      HoverAttribute      = value?.hover      ?? {};
	const continuous: ContinuousAttribute = value?.continuous ?? {};
	const reducedMotion = value?.reducedMotion ?? 'respect';

	function patch( next: Partial<AnimationsAttribute> ): void {
		onChange( { ...( value ?? {} ), ...next } );
	}

	function patchEntrance( next: Partial<EntranceAttribute> ): void {
		patch( { entrance: { ...entrance, ...next } } );
	}

	function patchHover( next: Partial<HoverAttribute> ): void {
		patch( { hover: { ...hover, ...next } } );
	}

	function patchContinuous( next: Partial<ContinuousAttribute> ): void {
		patch( { continuous: { ...continuous, ...next } } );
	}

	return (
		<>
			<PanelBody title={ __( 'Motion preferences', TEXT_DOMAIN ) } initialOpen={ false }>
				<ToggleControl
					label={ __( 'Respect reduced motion', TEXT_DOMAIN ) }
					help={ __(
						'When on, the runtime suppresses entrance + continuous animations for visitors who set “reduce motion” in their OS.',
						TEXT_DOMAIN
					) }
					checked={ 'respect' === reducedMotion }
					onChange={ ( next: boolean ) =>
						patch( { reducedMotion: next ? 'respect' : 'allow' } )
					}
				/>
			</PanelBody>

			<PanelBody title={ __( 'Entrance animation', TEXT_DOMAIN ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Motion', TEXT_DOMAIN ) }
					value={ baseString( entrance.name ) }
					options={ dropdownOptions( registry, FAMILY_ENTRANCE ) }
					onChange={ ( next: string ) =>
						patchEntrance( { name: '' === next ? undefined : next } )
					}
				/>
				<NumberControl
					label={ __( 'Duration (ms)', TEXT_DOMAIN ) }
					value={ entrance.duration ?? '' }
					min={ 0 }
					step={ 50 }
					onChange={ ( next ) => patchEntrance( { duration: parseNumber( next ) } ) }
				/>
				<NumberControl
					label={ __( 'Delay (ms)', TEXT_DOMAIN ) }
					value={ entrance.delay ?? '' }
					min={ 0 }
					step={ 50 }
					onChange={ ( next ) => patchEntrance( { delay: parseNumber( next ) } ) }
				/>
				<TextControl
					label={ __( 'Easing', TEXT_DOMAIN ) }
					value={ entrance.easing ?? '' }
					onChange={ ( next: string ) => patchEntrance( { easing: '' === next ? undefined : next } ) }
				/>
				<NumberControl
					label={ __( 'Viewport threshold (0–1)', TEXT_DOMAIN ) }
					value={ entrance.threshold ?? '' }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					onChange={ ( next ) => patchEntrance( { threshold: parseNumber( next ) } ) }
				/>
			</PanelBody>

			<PanelBody title={ __( 'Hover animation', TEXT_DOMAIN ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Preset', TEXT_DOMAIN ) }
					value={ baseString( hover.name ) }
					options={ dropdownOptions( registry, FAMILY_HOVER ) }
					onChange={ ( next: string ) =>
						patchHover( { name: '' === next ? undefined : next } )
					}
				/>
				{ 'glow' === registry.get( FAMILY_HOVER, baseString( hover.name ) )?.preset && (
					<GlowColorControl
						value={ hover.color }
						onChange={ ( next ) => patchHover( { color: next } ) }
					/>
				) }
				<NumberControl
					label={ __( 'Duration (ms)', TEXT_DOMAIN ) }
					value={ hover.duration ?? '' }
					min={ 0 }
					step={ 25 }
					onChange={ ( next ) => patchHover( { duration: parseNumber( next ) } ) }
				/>
				<TextControl
					label={ __( 'Easing', TEXT_DOMAIN ) }
					value={ hover.easing ?? '' }
					onChange={ ( next: string ) => patchHover( { easing: '' === next ? undefined : next } ) }
				/>
			</PanelBody>

			<PanelBody title={ __( 'Continuous animation', TEXT_DOMAIN ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Motion', TEXT_DOMAIN ) }
					value={ baseString( continuous.name ) }
					options={ dropdownOptions( registry, FAMILY_CONTINUOUS ) }
					onChange={ ( next: string ) =>
						patchContinuous( { name: '' === next ? undefined : next } )
					}
				/>
				<NumberControl
					label={ __( 'Duration (ms)', TEXT_DOMAIN ) }
					value={ continuous.duration ?? '' }
					min={ 0 }
					step={ 100 }
					onChange={ ( next ) => patchContinuous( { duration: parseNumber( next ) } ) }
				/>
				<TextControl
					label={ __( 'Easing', TEXT_DOMAIN ) }
					value={ continuous.easing ?? '' }
					onChange={ ( next: string ) =>
						patchContinuous( { easing: '' === next ? undefined : next } )
					}
				/>
				<TextControl
					label={ __( 'Repeat', TEXT_DOMAIN ) }
					help={ __( '“infinite” or a positive integer.', TEXT_DOMAIN ) }
					value={ undefined === continuous.count ? '' : String( continuous.count ) }
					onChange={ ( next: string ) => {
						if ( '' === next ) {
							patchContinuous( { count: undefined } );
							return;
						}
						if ( 'infinite' === next ) {
							patchContinuous( { count: 'infinite' } );
							return;
						}
						// `Number.isInteger` rejects mixed input like
						// `"2abc"` that `parseInt` would silently accept
						// as `2`.
						const parsed = Number( next );
						if ( Number.isInteger( parsed ) && parsed > 0 ) {
							patchContinuous( { count: parsed } );
						}
					} }
				/>
			</PanelBody>
		</>
	);
}
