/**
 * TypeScript shapes for the block binding layer (#504).
 *
 * Mirrors the PHP `BindingResolver` storage contract:
 *
 *   block.attrs    = static fallback values
 *   block.bindings = sidecar map (attribute → BindingDefinition)
 *
 * Drivers come from {@link BlockBindingSource} on the PHP side; this
 * file declares only what the editor needs.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

export type EmptyValuePolicy = 'fallback' | 'hide' | 'placeholder';

export interface BindingDefinition {
	source: string;
	args?: Record<string, unknown>;
	onEmpty?: EmptyValuePolicy;
	placeholder?: string;
	allowIncompatible?: boolean;
}

export type BindingsMap = Record<string, BindingDefinition>;

export interface BindingSourceSummary {
	name: string;
}

export interface BindingFieldDefinition {
	key: string;
	label: string;
	type: string;
}

export interface BindingsApiConfig {
	apiBase: string;
}
