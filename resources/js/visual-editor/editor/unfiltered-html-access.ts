/**
 * Whether the current user's block markup is saved unfiltered (#1062), and
 * what that means for the Custom HTML block.
 *
 * Paste needs `core/html` registered, because upstream's paste pipeline
 * hard-codes it for nodes no block's raw transform matches (#1067). So it
 * registers for everyone, and this keeps it out of the inserter unless the
 * host stamped `data-can-use-unfiltered-html`. The flag only hides the
 * block: every save re-checks the gate server-side (`BlockHtmlGuard`), and
 * a Custom HTML block an author reaches another way is filtered like any
 * other markup.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import type { BlockConfiguration } from '@wordpress/blocks';
import { addFilter, hasFilter } from '@wordpress/hooks';

const INSERTER_FILTER = 'artisanpack/custom-html-inserter';

let allowed = false;

/**
 * Record whether the current user holds the unfiltered-HTML ability. Call
 * it before the blocks register.
 *
 * @since 1.20.0
 */
export function setUnfilteredHtmlAccess( value: boolean ): void {
    allowed = value;
}

/**
 * Whether Custom HTML should be offered in the inserter.
 *
 * @since 1.20.0
 */
export function canUseUnfilteredHtml(): boolean {
    return allowed;
}

/**
 * Pure `blocks.registerBlockType` transform: hides `core/html` from the
 * inserter for a user who doesn't hold the ability.
 *
 * @since 1.20.0
 *
 * @param settings Block settings being registered.
 * @param name     Block name.
 */
export function withCustomHtmlInserterAccess< T extends Partial< BlockConfiguration > >( settings: T, name: string ): T {
    if ( 'core/html' !== name || allowed ) {
        return settings;
    }

    return { ...settings, supports: { ...settings.supports, inserter: false } };
}

/**
 * Install the filter. Idempotent (guards on the hooks registry).
 *
 * @since 1.20.0
 */
export function registerCustomHtmlInserterFilter(): void {
    if ( hasFilter( 'blocks.registerBlockType', INSERTER_FILTER ) ) {
        return;
    }

    addFilter( 'blocks.registerBlockType', INSERTER_FILTER, withCustomHtmlInserterAccess );
}
