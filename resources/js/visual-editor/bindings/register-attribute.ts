/**
 * Inject the `bindings` sidecar attribute on every block (#504).
 *
 * Unlike opt-in features (animations, states, gradient borders), block
 * bindings are a universal storage shape — any scalar attribute on any
 * block can be linked to data. So this `blocks.registerBlockType`
 * filter unconditionally adds `bindings: { type: 'object', default:
 * undefined }` to every block's attribute schema so the editor can
 * `setAttributes( { bindings } )` and Gutenberg round-trips the value
 * through save/parse identically to today's non-bound blocks.
 *
 * The same filter declares the block context the bindings HOC reads
 * (#1041). Gutenberg hands an `editor.BlockEdit` filter only the context
 * keys the block lists in `usesContext`, so without this a block inside
 * Single Content or a Query Loop can't tell which record it belongs to.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { addFilter } from '@wordpress/hooks';

const FILTER_HOOK      = 'blocks.registerBlockType';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/bindings-attribute';

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.bindings-attribute.registered',
);

interface GlobalSentinelHost {
	[ REGISTERED_KEY ]?: boolean;
}

/**
 * Context keys the bindings HOC reads to find a block's record.
 *
 * @since 1.20.0
 */
export const BINDING_CONTEXT_KEYS: readonly string[] = [
	'postId',
	'postType',
	'artisanpack/postPreview',
];

interface BlockSettingsLike {
	attributes?: Record<string, unknown>;
	usesContext?: string[];
	[ key: string ]: unknown;
}

function injectBindingsAttribute( settings: BlockSettingsLike ): BlockSettingsLike {
	const attributes = settings.attributes ?? {};

	return {
		...settings,
		attributes: 'bindings' in attributes
			? attributes
			: { ...attributes, bindings: { type: 'object' } },
		usesContext: [
			...new Set( [ ...( settings.usesContext ?? [] ), ...BINDING_CONTEXT_KEYS ] ),
		],
	};
}

export function registerBindingsAttribute(): void {
	const host = globalThis as unknown as GlobalSentinelHost;

	if ( host[ REGISTERED_KEY ] ) {
		return;
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectBindingsAttribute );
	host[ REGISTERED_KEY ] = true;
}
