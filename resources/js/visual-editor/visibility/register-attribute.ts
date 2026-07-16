/**
 * Inject the `artisanpackVisibility` attribute on every block that
 * opts into block visibility (#491 · #492 · #493).
 *
 * Blocks are opted in by default. A block explicitly declares
 * `supports.artisanpackVisibility: false` in its `block.json` to opt
 * out — reserved for blocks that must not be conditionally hidden
 * (e.g. the root document title on a post editor). This
 * `blocks.registerBlockType` filter injects the storage attribute at
 * registration time so individual `block.json` files don't need to
 * declare it by hand.
 *
 * The injected attribute is a plain `object` matching the shape the
 * PHP `VisibilityEvaluator` consumes — see `types.ts` for the schema.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { addFilter } from '@wordpress/hooks';

const FILTER_HOOK      = 'blocks.registerBlockType';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/visibility-attribute';

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.visibility-attribute.registered',
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockSupports {
    artisanpackVisibility?: boolean;
}

interface BlockSettingsLike {
    supports?: BlockSupports;
    attributes?: Record<string, unknown>;
    [key: string]: unknown;
}

function injectVisibilityAttribute(settings: BlockSettingsLike): BlockSettingsLike {
    if (settings.supports?.artisanpackVisibility === false) {
        return settings;
    }

    if (settings.attributes && 'artisanpackVisibility' in settings.attributes) {
        return settings;
    }

    return {
        ...settings,
        attributes: {
            ...(settings.attributes ?? {}),
            artisanpackVisibility: {
                type:    'object',
                default: null,
            },
        },
    };
}

export function registerVisibilityAttribute(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY]) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, injectVisibilityAttribute);
    host[REGISTERED_KEY] = true;
}
