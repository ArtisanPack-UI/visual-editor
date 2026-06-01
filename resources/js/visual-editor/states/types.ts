/**
 * Shared types for the state design tools (#488).
 *
 * Mirrors the PHP-side `StateRegistry` + `StateValueResolver` shapes
 * so the editor's UI surface can be type-safe end-to-end.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

export const BASE_KEY = 'idle' as const;

export type StateKey = typeof BASE_KEY | string;

export interface StateDefinition {
	key: string;
	label: string;
	/**
	 * CSS pseudo or attribute selector. `&` is replaced with the
	 * block's unique scope at emit time. The reserved `idle` slot has
	 * an empty selector — it represents the default styles.
	 */
	selector: string;
	icon: string;
	inheritsFrom: string | null;
	/**
	 * When true, the server-side renderer wraps the rule in
	 * `@media (hover: hover)`. Defaults to false.
	 */
	hoverMediaWrap: boolean;
}

/**
 * Discriminated per-state storage. Mirrors the PHP shape:
 * { idle: x, hover: null, focus: y, ... }
 *
 * `null` means "inherit from the next link in the inheritance chain."
 * Missing keys are treated identically to `null` by the resolver.
 */
export type StatefulAttribute<T> =
	| T
	| ({ [BASE_KEY]?: T | null } & { [key: string]: T | null | undefined });

export interface StateRegistrySnapshot {
	states: StateDefinition[];
}
