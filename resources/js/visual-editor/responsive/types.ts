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
	/**
	 * Human-readable label rendered in the viewport switcher (#617).
	 * When omitted, the UI falls back to the breakpoint key.
	 */
	label?: string;
	/**
	 * Canvas iframe width, in pixels, used when the switcher previews
	 * this breakpoint (#617). When omitted, the UI falls back to
	 * `minWidthPx`. Split from `minWidthPx` because Tailwind's `sm`
	 * cascade activates at `640px` but you preview it on a phone-sized
	 * `375px` viewport.
	 */
	previewWidthPx?: number;
}

/**
 * Wire form used by the PHP → JS bootstrap payload. Mirrors the
 * `BreakpointRegistry::toArray()` shape so a snapshot round-trips
 * losslessly between server and client.
 *
 * @since 1.0.0
 */
export interface BreakpointWireEntry {
	minWidthPx: number;
	label?: string;
	previewWidthPx?: number;
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
