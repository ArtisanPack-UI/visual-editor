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

interface BlockSettingsLike {
	attributes?: Record<string, unknown>;
	[ key: string ]: unknown;
}

function injectBindingsAttribute( settings: BlockSettingsLike ): BlockSettingsLike {
	if ( settings.attributes && 'bindings' in settings.attributes ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...( settings.attributes ?? {} ),
			bindings: {
				type: 'object',
			},
		},
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
