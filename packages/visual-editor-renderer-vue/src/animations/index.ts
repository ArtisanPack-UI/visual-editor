/**
 * Animation helpers for the Vue renderer (#489).
 *
 * Re-exports the same shape the React renderer uses — the resolver is
 * framework-agnostic. The runtime CSS is server-side; the renderer's
 * only job is computing wrapper classes + data attributes from the
 * `artisanpackAnimations` attribute bag.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.1.0
 */

export interface AnimationsAttributeShape {
	entrance?: {
		name?: string | Record<string, string | null>;
		threshold?: number;
		once?: boolean;
	};
	hover?: {
		name?: string | Record<string, string | null>;
		duration?: number;
		easing?: string;
	};
	continuous?: {
		name?: string | Record<string, string | null>;
		duration?: number;
		easing?: string;
		count?: number | 'infinite';
	};
	reducedMotion?: 'respect' | 'allow';
}

export interface AnimationMarkup {
	hasAnimations: boolean;
	hasEntrance: boolean;
	classes: string[];
	dataAttributes: Record<string, string>;
}

function baseValue( name: unknown ): string | null {
	if ( 'string' === typeof name ) {
		return '' === name ? null : name;
	}
	if ( name && 'object' === typeof name ) {
		const base = ( name as Record<string, unknown> ).base;
		return 'string' === typeof base && '' !== base ? base : null;
	}
	return null;
}

function hasAnyName( name: unknown ): boolean {
	if ( null !== baseValue( name ) ) {
		return true;
	}
	if ( name && 'object' === typeof name ) {
		for ( const value of Object.values( name as Record<string, unknown> ) ) {
			if ( 'string' === typeof value && '' !== value ) {
				return true;
			}
		}
	}
	return false;
}

export function resolveAnimationMarkup( attributes: AnimationsAttributeShape | undefined ): AnimationMarkup {
	const entrance = attributes?.entrance ?? {};
	const hover = attributes?.hover;
	const continuous = attributes?.continuous;
	const hasEntrance = hasAnyName( entrance.name );
	// Mirrors `AnimationCssEmitter::hasHover`: name OR custom timing.
	const hasHover =
		hasAnyName( hover?.name ) ||
		undefined !== hover?.duration ||
		undefined !== hover?.easing;
	const hasContinuous = hasAnyName( continuous?.name );
	const hasAnimations = hasEntrance || hasHover || hasContinuous;

	const classes: string[] = [];
	if ( hasAnimations ) {
		classes.push( 'ap-anim' );
	}
	if ( hasEntrance ) {
		classes.push( 'ap-anim-pre' );
	}

	const dataAttributes: Record<string, string> = {};
	const entranceBase = baseValue( entrance.name );
	if ( entranceBase ) {
		dataAttributes[ 'data-ap-anim-entrance' ] = entranceBase;
		if ( 'number' === typeof entrance.threshold ) {
			dataAttributes[ 'data-ap-anim-threshold' ] = String( entrance.threshold );
		}
		if ( false === entrance.once ) {
			dataAttributes[ 'data-ap-anim-once' ] = 'false';
		}
	}

	if ( 'allow' === attributes?.reducedMotion ) {
		dataAttributes[ 'data-ap-anim-reduced' ] = 'allow';
	}

	return { hasAnimations, hasEntrance, classes, dataAttributes };
}
