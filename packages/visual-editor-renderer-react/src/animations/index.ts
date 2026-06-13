/**
 * Animation hooks for the React renderer (#489).
 *
 * The CSS for block animations is produced server-side by
 * `AnimationCssEmitter` and ships in the editor's `<style data-ve-animations>`
 * block at the top of the markup. The renderer's only client-side
 * responsibility is:
 *
 *  - Apply the `ap-anim` and `ap-anim-pre` wrapper classes when the
 *    block has any animations configured.
 *  - Stamp the `data-ap-anim-*` attribute set onto the wrapper so the
 *    shared runtime can find it.
 *
 * The runtime itself is loaded once per page by the host bundle — it
 * lives in `@artisanpack-ui/visual-editor/animations/runtime`.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
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

/**
 * Computes the wrapper classes + data-* attributes for a block whose
 * `artisanpackAnimations` attribute bag has been hydrated from the
 * server-rendered tree.
 *
 * Mirrors {@see AnimationCssEmitter::wrapperClasses()} and
 * {@see AnimationCssEmitter::dataAttributes()} on the PHP side so the
 * client and server agree exactly on what markup the runtime sees.
 *
 * @since 1.1.0
 */
export function resolveAnimationMarkup( attributes: AnimationsAttributeShape | undefined ): AnimationMarkup {
	const entrance = attributes?.entrance ?? {};
	const hover = attributes?.hover;
	const continuous = attributes?.continuous;
	const hasEntrance = hasAnyName( entrance.name );
	// Mirrors `AnimationCssEmitter::hasHover`: treat as enabled when a
	// preset name OR a duration/easing curve is authored, so a custom
	// timing on the hover state alone produces a transition.
	const hasHover =
		hasAnyName( hover?.name ) ||
		undefined !== hover?.duration ||
		undefined !== hover?.easing;
	// Mirrors `AnimationCssEmitter::hasContinuousAnywhere`: any
	// configured name in the shape (base or per-breakpoint) qualifies.
	// Duration/easing alone do not enable continuous (the loop needs a
	// keyframe), matching the server.
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
