/**
 * Bound-attribute lock context (#995).
 *
 * The bindings HOC overlays resolved values on top of a block's saved
 * attributes, so any edit control wired to a bound attribute would have
 * every keystroke overwritten by the bound value. The HOC publishes which
 * attributes are bound (and to what) through this context so edit
 * components can render those controls disabled instead — Gutenberg's
 * `canUserEditValue` parity.
 *
 * Deliberately free of `@wordpress/*` imports so block edits can consume
 * it without widening their test mocks; the user-facing help string lives
 * in {@link ./bound-attribute-help}.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.3
 */

import { createContext, useContext } from 'react';

import type { BindingsMap } from './types';

/** What one bound attribute is connected to. */
export interface BoundAttributeInfo {
	/** Binding source name, e.g. `package` or `post_core`. */
	readonly source: string;
	/** The source field (`args.key`, else `args.path`), when one is set. */
	readonly key: string | null;
}

/** Attribute name → binding info, for every attribute with an active binding. */
export type BoundAttributesMap = Readonly<Record<string, BoundAttributeInfo>>;

const EMPTY: BoundAttributesMap = Object.freeze( {} );

/**
 * The map for a block with nothing bound. Provided explicitly so a
 * nested block never inherits its parent's bound attributes.
 */
export const NO_BOUND_ATTRIBUTES: BoundAttributesMap = EMPTY;

/** Carries the bound-attribute map from the bindings HOC to block edits. */
export const BoundAttributesContext = createContext<BoundAttributesMap>( EMPTY );

BoundAttributesContext.displayName = 'BoundAttributesContext';

/**
 * Reduce a block's bindings sidecar to the attributes that are actually
 * locked — a binding only counts once a source has been picked, matching
 * when the resolver starts overlaying values.
 */
export function collectBoundAttributes(
	bindings: BindingsMap | null | undefined,
): BoundAttributesMap {
	if ( ! bindings ) {
		return EMPTY;
	}

	const out: Record<string, BoundAttributeInfo> = {};

	for ( const [ name, binding ] of Object.entries( bindings ) ) {
		const source = binding?.source;

		if ( typeof source !== 'string' || source === '' ) {
			continue;
		}

		const args = binding.args ?? {};
		const key  = typeof args.key === 'string' && args.key !== ''
			? args.key
			: typeof args.path === 'string' && args.path !== ''
				? args.path
				: null;

		out[ name ] = { source, key };
	}

	return Object.keys( out ).length === 0 ? EMPTY : out;
}

/** Every bound attribute of the block being edited. */
export function useBoundAttributes(): BoundAttributesMap {
	return useContext( BoundAttributesContext );
}

/** The binding info for one attribute, or `null` when it is editable. */
export function useBoundAttribute( name: string ): BoundAttributeInfo | null {
	return useContext( BoundAttributesContext )[ name ] ?? null;
}
