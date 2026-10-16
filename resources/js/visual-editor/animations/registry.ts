/**
 * Client-side animation registry (#489).
 *
 * Mirrors the PHP `AnimationRegistry` so the editor can resolve
 * animations and surface metadata without round-tripping to the
 * server. In v1.1.0 the registry is seeded from `DEFAULT_ANIMATIONS`;
 * `registryFromSnapshot()` is the planned hydration entry point for a
 * follow-up that stamps a merged PHP config + theme.json + Global
 * Styles snapshot from the bootstrap path.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import {
	ANIMATION_FAMILIES,
	type AnimationDefinition,
	type AnimationFamily,
	type AnimationRegistrySnapshot,
	type CustomKeyframe,
	FAMILY_CONTINUOUS,
	FAMILY_ENTRANCE,
	FAMILY_HOVER,
} from './types';

export const DEFAULT_ANIMATIONS: Record<AnimationFamily, AnimationDefinition[]> = {
	[ FAMILY_ENTRANCE ]: [
		{ key: 'fade-in',        family: FAMILY_ENTRANCE, label: 'Fade in',         keyframe: 'apFadeIn',       duration: 600, easing: 'ease-out' },
		{ key: 'fade-in-up',     family: FAMILY_ENTRANCE, label: 'Fade in up',      keyframe: 'apFadeInUp',     duration: 600, easing: 'ease-out' },
		{ key: 'fade-in-down',   family: FAMILY_ENTRANCE, label: 'Fade in down',    keyframe: 'apFadeInDown',   duration: 600, easing: 'ease-out' },
		{ key: 'fade-in-left',   family: FAMILY_ENTRANCE, label: 'Fade in left',    keyframe: 'apFadeInLeft',   duration: 600, easing: 'ease-out' },
		{ key: 'fade-in-right',  family: FAMILY_ENTRANCE, label: 'Fade in right',   keyframe: 'apFadeInRight',  duration: 600, easing: 'ease-out' },
		{ key: 'zoom-in',        family: FAMILY_ENTRANCE, label: 'Zoom in',         keyframe: 'apZoomIn',       duration: 500, easing: 'ease-out' },
		{ key: 'zoom-out',       family: FAMILY_ENTRANCE, label: 'Zoom out',        keyframe: 'apZoomOut',      duration: 500, easing: 'ease-out' },
		{ key: 'slide-in-up',    family: FAMILY_ENTRANCE, label: 'Slide in up',     keyframe: 'apSlideInUp',    duration: 600, easing: 'ease-out' },
		{ key: 'slide-in-down',  family: FAMILY_ENTRANCE, label: 'Slide in down',   keyframe: 'apSlideInDown',  duration: 600, easing: 'ease-out' },
		{ key: 'slide-in-left',  family: FAMILY_ENTRANCE, label: 'Slide in left',   keyframe: 'apSlideInLeft',  duration: 600, easing: 'ease-out' },
		{ key: 'slide-in-right', family: FAMILY_ENTRANCE, label: 'Slide in right',  keyframe: 'apSlideInRight', duration: 600, easing: 'ease-out' },
		{ key: 'flip-x',         family: FAMILY_ENTRANCE, label: 'Flip X',          keyframe: 'apFlipX',        duration: 700, easing: 'ease-out' },
		{ key: 'flip-y',         family: FAMILY_ENTRANCE, label: 'Flip Y',          keyframe: 'apFlipY',        duration: 700, easing: 'ease-out' },
		{ key: 'rotate-in',      family: FAMILY_ENTRANCE, label: 'Rotate in',       keyframe: 'apRotateIn',     duration: 700, easing: 'ease-out' },
	],
	[ FAMILY_HOVER ]: [
		{ key: 'lift',  family: FAMILY_HOVER, label: 'Lift',  preset: 'lift',  duration: 200, easing: 'ease-out' },
		{ key: 'press', family: FAMILY_HOVER, label: 'Press', preset: 'press', duration: 120, easing: 'ease-in' },
		{ key: 'glow',  family: FAMILY_HOVER, label: 'Glow',  preset: 'glow',  duration: 250, easing: 'ease-in-out' },
	],
	[ FAMILY_CONTINUOUS ]: [
		{ key: 'pulse',  family: FAMILY_CONTINUOUS, label: 'Pulse',  keyframe: 'apPulse',  duration: 2000, easing: 'ease-in-out' },
		{ key: 'bounce', family: FAMILY_CONTINUOUS, label: 'Bounce', keyframe: 'apBounce', duration: 1000, easing: 'ease-in-out' },
		{ key: 'spin',   family: FAMILY_CONTINUOUS, label: 'Spin',   keyframe: 'apSpin',   duration: 2000, easing: 'linear' },
		{ key: 'ping',   family: FAMILY_CONTINUOUS, label: 'Ping',   keyframe: 'apPing',   duration: 1000, easing: 'cubic-bezier(0, 0, 0.2, 1)' },
		{ key: 'wiggle', family: FAMILY_CONTINUOUS, label: 'Wiggle', keyframe: 'apWiggle', duration: 800,  easing: 'ease-in-out' },
		{ key: 'float',  family: FAMILY_CONTINUOUS, label: 'Float',  keyframe: 'apFloat',  duration: 3000, easing: 'ease-in-out' },
	],
};

/**
 * Display label for an animation definition. The package defaults stay
 * English in {@link DEFAULT_ANIMATIONS} (they mirror the PHP registry
 * and load before locale data), so they are translated here at render
 * time; host-supplied labels pass through unchanged.
 *
 * @since 1.15.0
 */
export function animationLabel( definition: Pick<AnimationDefinition, 'label'> ): string {
	const translated: Record<string, string> = {
		'Fade in': __( 'Fade in', TEXT_DOMAIN ),
		'Fade in up': __( 'Fade in up', TEXT_DOMAIN ),
		'Fade in down': __( 'Fade in down', TEXT_DOMAIN ),
		'Fade in left': __( 'Fade in left', TEXT_DOMAIN ),
		'Fade in right': __( 'Fade in right', TEXT_DOMAIN ),
		'Zoom in': __( 'Zoom in', TEXT_DOMAIN ),
		'Zoom out': __( 'Zoom out', TEXT_DOMAIN ),
		'Slide in up': __( 'Slide in up', TEXT_DOMAIN ),
		'Slide in down': __( 'Slide in down', TEXT_DOMAIN ),
		'Slide in left': __( 'Slide in left', TEXT_DOMAIN ),
		'Slide in right': __( 'Slide in right', TEXT_DOMAIN ),
		'Flip X': __( 'Flip X', TEXT_DOMAIN ),
		'Flip Y': __( 'Flip Y', TEXT_DOMAIN ),
		'Rotate in': __( 'Rotate in', TEXT_DOMAIN ),
		'Lift': __( 'Lift', TEXT_DOMAIN ),
		'Press': __( 'Press', TEXT_DOMAIN ),
		'Glow': __( 'Glow', TEXT_DOMAIN ),
		'Pulse': __( 'Pulse', TEXT_DOMAIN ),
		'Bounce': __( 'Bounce', TEXT_DOMAIN ),
		'Spin': __( 'Spin', TEXT_DOMAIN ),
		'Ping': __( 'Ping', TEXT_DOMAIN ),
		'Wiggle': __( 'Wiggle', TEXT_DOMAIN ),
		'Float': __( 'Float', TEXT_DOMAIN ),
	};

	return translated[ definition.label ] ?? definition.label;
}

export class AnimationRegistry {
	protected readonly byFamily: Record<AnimationFamily, Map<string, AnimationDefinition>>;
	protected readonly customKeyframes: Map<string, CustomKeyframe>;

	constructor(
		animations: Record<AnimationFamily, AnimationDefinition[]> = DEFAULT_ANIMATIONS,
		customKeyframes: CustomKeyframe[] = [],
	) {
		this.byFamily = {
			[ FAMILY_ENTRANCE ]:   new Map(),
			[ FAMILY_HOVER ]:      new Map(),
			[ FAMILY_CONTINUOUS ]: new Map(),
		};

		for ( const family of ANIMATION_FAMILIES ) {
			for ( const def of animations[ family ] ?? [] ) {
				this.byFamily[ family ].set( def.key, def );
			}
		}

		this.customKeyframes = new Map();
		for ( const kf of customKeyframes ) {
			this.customKeyframes.set( kf.name, kf );
		}
	}

	family( family: AnimationFamily ): AnimationDefinition[] {
		return Array.from( this.byFamily[ family ].values() );
	}

	get( family: AnimationFamily, key: string ): AnimationDefinition | null {
		return this.byFamily[ family ].get( key ) ?? null;
	}

	has( family: AnimationFamily, key: string ): boolean {
		return this.byFamily[ family ].has( key );
	}

	customs(): CustomKeyframe[] {
		return Array.from( this.customKeyframes.values() );
	}
}

export function registryFromSnapshot( snapshot: AnimationRegistrySnapshot | undefined ): AnimationRegistry {
	if ( ! snapshot ) {
		return new AnimationRegistry();
	}

	const animations: Record<AnimationFamily, AnimationDefinition[]> = {
		[ FAMILY_ENTRANCE ]:   Object.values( snapshot.animations[ FAMILY_ENTRANCE ]   ?? {} ),
		[ FAMILY_HOVER ]:      Object.values( snapshot.animations[ FAMILY_HOVER ]      ?? {} ),
		[ FAMILY_CONTINUOUS ]: Object.values( snapshot.animations[ FAMILY_CONTINUOUS ] ?? {} ),
	};

	return new AnimationRegistry( animations, snapshot.customKeyframes ?? [] );
}
