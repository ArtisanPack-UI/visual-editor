/**
 * Shared types for the responsive design tools (#487).
 *
 * Mirrors the PHP-side BreakpointRegistry + ResponsiveValueResolver
 * shapes so the editor's UI surface can be type-safe end-to-end.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

export const BASE_KEY = 'base' as const;

export type BreakpointKey = typeof BASE_KEY | string;

export interface Breakpoint {
	key: string;
	minWidthPx: number;
}

/**
 * Discriminated per-breakpoint storage. Mirrors the PHP shape:
 * { base: x, sm: null, md: y, ... }
 *
 * `null` means "inherit from the next smaller defined slot."
 * Missing keys are treated identically to `null` by the resolver.
 */
export type ResponsiveAttribute<T> =
	| T
	| ({ [BASE_KEY]?: T | null } & { [key: string]: T | null | undefined });

export interface BreakpointRegistrySnapshot {
	/** Ascending-sorted named breakpoints (no `base`). */
	breakpoints: Breakpoint[];
}
