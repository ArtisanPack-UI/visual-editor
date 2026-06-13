/**
 * Inject the `artisanpackAnimations` attribute on every block that
 * opts into block animations (#489).
 *
 * Blocks declare opt-in via `supports.artisanpackAnimations` in
 * `block.json`. This `blocks.registerBlockType` filter adds the
 * storage attribute at registration time so individual `block.json`
 * files don't need to declare it by hand.
 *
 * The injected attribute is a plain `object` matching the shape the
 * PHP `AnimationCssEmitter` consumes — see `types.ts` for the schema.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { addFilter } from '@wordpress/hooks';

const FILTER_HOOK      = 'blocks.registerBlockType';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/animations-attribute';

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.animations-attribute.registered',
);

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean;
}

interface BlockSupports {
	artisanpackAnimations?: boolean | { families?: string[] };
}

interface BlockSettingsLike {
	supports?: BlockSupports;
	attributes?: Record<string, unknown>;
	[key: string]: unknown;
}

function injectAnimationsAttribute( settings: BlockSettingsLike ): BlockSettingsLike {
	const support = settings.supports?.artisanpackAnimations;

	if ( ! support ) {
		return settings;
	}

	if ( settings.attributes && 'artisanpackAnimations' in settings.attributes ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...( settings.attributes ?? {} ),
			artisanpackAnimations: {
				type:    'object',
				default: null,
			},
		},
	};
}

export function registerAnimationsAttribute(): void {
	const host = globalThis as unknown as GlobalSentinelHost;

	if ( host[ REGISTERED_KEY ] ) {
		return;
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectAnimationsAttribute );
	host[ REGISTERED_KEY ] = true;
}
