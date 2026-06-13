/**
 * Shared types for the block animations system (#489).
 *
 * Mirrors the PHP-side AnimationRegistry / KeyframeRegistry /
 * AnimationCssEmitter shapes so the editor's UI is type-safe end-to-end.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import type { ResponsiveAttribute } from '../responsive/types';

export const FAMILY_ENTRANCE = 'entrance' as const;
export const FAMILY_HOVER = 'hover' as const;
export const FAMILY_CONTINUOUS = 'continuous' as const;

export type AnimationFamily =
	| typeof FAMILY_ENTRANCE
	| typeof FAMILY_HOVER
	| typeof FAMILY_CONTINUOUS;

export const ANIMATION_FAMILIES: AnimationFamily[] = [
	FAMILY_ENTRANCE,
	FAMILY_HOVER,
	FAMILY_CONTINUOUS,
];

export interface AnimationDefinition {
	key: string;
	family: AnimationFamily;
	label: string;
	duration: number;
	easing: string;
	/** Set on entrance + continuous entries. */
	keyframe?: string;
	/** Set on hover entries. */
	preset?: string;
}

export interface AnimationRegistrySnapshot {
	animations: Record<AnimationFamily, Record<string, AnimationDefinition>>;
	customKeyframes: CustomKeyframe[];
}

export interface CustomKeyframeStop {
	at: string;
	transform?: string;
	opacity?: string;
	filter?: string;
	color?: string;
	'background-color'?: string;
	'box-shadow'?: string;
}

export interface CustomKeyframe {
	name: string;
	stops: CustomKeyframeStop[];
}

export interface EntranceAttribute {
	name?: ResponsiveAttribute<string | null>;
	duration?: number;
	delay?: number;
	easing?: string;
	threshold?: number;
	once?: boolean;
}

export interface HoverAttribute {
	name?: ResponsiveAttribute<string | null>;
	duration?: number;
	delay?: number;
	easing?: string;
}

export interface ContinuousAttribute {
	name?: ResponsiveAttribute<string | null>;
	duration?: number;
	easing?: string;
	count?: number | 'infinite';
}

export type ReducedMotionPolicy = 'respect' | 'allow';

export interface AnimationsAttribute {
	entrance?: EntranceAttribute;
	hover?: HoverAttribute;
	continuous?: ContinuousAttribute;
	reducedMotion?: ReducedMotionPolicy;
}
