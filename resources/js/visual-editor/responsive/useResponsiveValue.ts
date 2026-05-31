/**
 * `useResponsiveValue` hook (#487).
 *
 * Resolves a discriminated `{base, sm, md, …}` attribute against the
 * editor's currently active breakpoint. Block edit components consume
 * this to render the resolved value at the breakpoint the editor is
 * authoring against.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import type { BreakpointRegistry } from './registry'
import { resolveResponsiveValue } from './resolver'
import type { ResponsiveAttribute } from './types'

/**
 * Returns the value for the given attribute at the editor's active
 * breakpoint. Re-renders the component whenever the editor switches
 * breakpoints, so the resolved value stays in sync.
 *
 * @param attribute  Either a scalar (legacy / unmodified) or the
 *                   discriminated object form.
 * @param registry   The active breakpoint registry — pass the
 *                   per-editor instance, not the bare snapshot.
 */
export function useResponsiveValue<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	registry: BreakpointRegistry,
): T | null {
	const activeBreakpoint = useSyncExternalStore( subscribeActiveBreakpoint, getActiveBreakpoint, getActiveBreakpoint )

	return resolveResponsiveValue<T>( attribute, activeBreakpoint, registry )
}

/**
 * Read-only sibling of {@see useResponsiveValue} for code paths that
 * already have an explicit breakpoint in hand (e.g. the renderer
 * rebuilding all breakpoints' values at once).
 */
export function resolveAt<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	breakpoint: string,
	registry: BreakpointRegistry,
): T | null {
	return resolveResponsiveValue<T>( attribute, breakpoint, registry )
}
