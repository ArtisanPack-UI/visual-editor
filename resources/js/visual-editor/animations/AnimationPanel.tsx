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

import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { AnimationRegistry } from './registry';
import type {
	AnimationsAttribute,
	ContinuousAttribute,
	EntranceAttribute,
	HoverAttribute,
} from './types';
import { FAMILY_CONTINUOUS, FAMILY_ENTRANCE, FAMILY_HOVER } from './types';

export interface AnimationPanelProps {
	registry: AnimationRegistry;
	value: AnimationsAttribute | undefined;
	onChange: ( next: AnimationsAttribute ) => void;
	/** Set false when the block declares `supports.artisanpackAnimations: false`. */
	supportsAnimations?: boolean;
}

function dropdownOptions( registry: AnimationRegistry, family: 'entrance' | 'hover' | 'continuous' ) {
	return [
		{ label: __( '(none)', 'visual-editor' ), value: '' },
		...registry.family( family ).map( ( def ) => ( {
			label: def.label,
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
			<PanelBody title={ __( 'Motion preferences', 'visual-editor' ) } initialOpen={ false }>
				<ToggleControl
					label={ __( 'Respect reduced motion', 'visual-editor' ) }
					help={ __(
						'When on, the runtime suppresses entrance + continuous animations for visitors who set “reduce motion” in their OS.',
						'visual-editor'
					) }
					checked={ 'respect' === reducedMotion }
					onChange={ ( next: boolean ) =>
						patch( { reducedMotion: next ? 'respect' : 'allow' } )
					}
				/>
			</PanelBody>

			<PanelBody title={ __( 'Entrance animation', 'visual-editor' ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Motion', 'visual-editor' ) }
					value={ baseString( entrance.name ) }
					options={ dropdownOptions( registry, FAMILY_ENTRANCE ) }
					onChange={ ( next: string ) =>
						patchEntrance( { name: '' === next ? undefined : next } )
					}
				/>
				<NumberControl
					label={ __( 'Duration (ms)', 'visual-editor' ) }
					value={ entrance.duration ?? '' }
					min={ 0 }
					step={ 50 }
					onChange={ ( next ) => patchEntrance( { duration: parseNumber( next ) } ) }
				/>
				<NumberControl
					label={ __( 'Delay (ms)', 'visual-editor' ) }
					value={ entrance.delay ?? '' }
					min={ 0 }
					step={ 50 }
					onChange={ ( next ) => patchEntrance( { delay: parseNumber( next ) } ) }
				/>
				<TextControl
					label={ __( 'Easing', 'visual-editor' ) }
					value={ entrance.easing ?? '' }
					onChange={ ( next: string ) => patchEntrance( { easing: '' === next ? undefined : next } ) }
				/>
				<NumberControl
					label={ __( 'Viewport threshold (0–1)', 'visual-editor' ) }
					value={ entrance.threshold ?? '' }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					onChange={ ( next ) => patchEntrance( { threshold: parseNumber( next ) } ) }
				/>
			</PanelBody>

			<PanelBody title={ __( 'Hover animation', 'visual-editor' ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Preset', 'visual-editor' ) }
					value={ baseString( hover.name ) }
					options={ dropdownOptions( registry, FAMILY_HOVER ) }
					onChange={ ( next: string ) =>
						patchHover( { name: '' === next ? undefined : next } )
					}
				/>
				<NumberControl
					label={ __( 'Duration (ms)', 'visual-editor' ) }
					value={ hover.duration ?? '' }
					min={ 0 }
					step={ 25 }
					onChange={ ( next ) => patchHover( { duration: parseNumber( next ) } ) }
				/>
				<TextControl
					label={ __( 'Easing', 'visual-editor' ) }
					value={ hover.easing ?? '' }
					onChange={ ( next: string ) => patchHover( { easing: '' === next ? undefined : next } ) }
				/>
			</PanelBody>

			<PanelBody title={ __( 'Continuous animation', 'visual-editor' ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Motion', 'visual-editor' ) }
					value={ baseString( continuous.name ) }
					options={ dropdownOptions( registry, FAMILY_CONTINUOUS ) }
					onChange={ ( next: string ) =>
						patchContinuous( { name: '' === next ? undefined : next } )
					}
				/>
				<NumberControl
					label={ __( 'Duration (ms)', 'visual-editor' ) }
					value={ continuous.duration ?? '' }
					min={ 0 }
					step={ 100 }
					onChange={ ( next ) => patchContinuous( { duration: parseNumber( next ) } ) }
				/>
				<TextControl
					label={ __( 'Easing', 'visual-editor' ) }
					value={ continuous.easing ?? '' }
					onChange={ ( next: string ) =>
						patchContinuous( { easing: '' === next ? undefined : next } )
					}
				/>
				<TextControl
					label={ __( 'Repeat', 'visual-editor' ) }
					help={ __( '“infinite” or a positive integer.', 'visual-editor' ) }
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
						const parsed = parseInt( next, 10 );
						if ( ! Number.isNaN( parsed ) && parsed > 0 ) {
							patchContinuous( { count: parsed } );
						}
					} }
				/>
			</PanelBody>
		</>
	);
}
