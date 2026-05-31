/**
 * Inject the `responsive` attribute on every block that opts into
 * per-breakpoint editing (#487).
 *
 * Blocks declare opt-in via `supports.artisanpackResponsive` in
 * `block.json`. Rather than asking each block's `block.json` to also
 * declare the `responsive` attribute by hand (easy to forget, easy to
 * mistype, doubles the noise per block), this `blocks.registerBlockType`
 * filter adds it automatically at registration time.
 *
 * The injected attribute is a plain `object` keyed by attribute path
 * (e.g. `style.spacing.padding`) → discriminated `{ sm, md, lg, … }`
 * object. Reads/writes are mediated by `withResponsiveAttributes`; this
 * file is just responsible for making sure the attribute exists so
 * `setAttributes` calls actually persist.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks'

const FILTER_HOOK    = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/responsive-attribute'

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.responsive-attribute.registered',
)

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean
}

interface BlockSupports {
    artisanpackResponsive?: {
        attributes?: string[]
    }
}

interface BlockSettingsLike {
    supports?: BlockSupports
    attributes?: Record<string, unknown>
    [key: string]: unknown
}

function injectResponsiveAttribute( settings: BlockSettingsLike ): BlockSettingsLike {
    const responsiveSupport = settings.supports?.artisanpackResponsive

    if ( ! responsiveSupport || ! Array.isArray( responsiveSupport.attributes ) || 0 === responsiveSupport.attributes.length ) {
        return settings
    }

    if ( settings.attributes && 'responsive' in settings.attributes ) {
        return settings
    }

    return {
        ...settings,
        attributes: {
            ...( settings.attributes ?? {} ),
            responsive: {
                type:    'object',
                default: null,
            },
        },
    }
}

/**
 * Register the filter at most once per page. Idempotent — safe to
 * call from both the post-editor and site-editor entries.
 */
export function registerResponsiveAttribute(): void {
    const host = globalThis as unknown as GlobalSentinelHost

    if ( host[ REGISTERED_KEY ] ) {
        return
    }

    addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectResponsiveAttribute )
    host[ REGISTERED_KEY ] = true
}
