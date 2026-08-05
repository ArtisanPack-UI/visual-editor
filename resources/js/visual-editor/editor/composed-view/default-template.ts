/**
 * The composed view's built-in default template (#624).
 *
 * When the applied-template endpoint cannot resolve a template for the
 * current content — no `template` field, an unknown slug, or a network
 * failure — the composed view still has to render *something*, otherwise
 * flipping the toggle looks like it silently did nothing. It composes
 * against this minimal block tree instead: title, featured image, content
 * slot, in that order.
 *
 * The tree is deliberately a client-side constant rather than a persisted
 * template. Nothing about it is editable or linkable — it exists only so
 * the canvas has a shape to fall back to — so writing it to the database
 * would put a phantom template in the site editor's template list.
 *
 * Emitted in the *server* block shape (no `clientId`, no `isValid`), the
 * same shape the endpoint returns, so it flows through the existing
 * `splitTemplateAroundContentSlot` → `hydrateBlocks` pipeline unchanged.
 *
 * @since 1.6.0
 */

import { __ } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { TEXT_DOMAIN } from '../../vendor/i18n';

/**
 * Display name for the fallback template. A function rather than a
 * module-level constant so the string is translated at call time, after
 * the locale data has loaded.
 */
export function defaultTemplateName(): string {
    return __('Default template', TEXT_DOMAIN);
}

/**
 * Build the fallback block tree.
 *
 * Returns a fresh array on every call: the caller hydrates it into a
 * block-editor store, and handing out a shared instance would let one
 * store's hydration leak `clientId`s into another's.
 *
 * Forked block names are used (not `core/*`) to match what the
 * applied-template endpoint rewrites real templates to, so the split
 * finds `artisanpack/post-content` as the content slot exactly as it
 * would in a resolved template.
 */
export function createDefaultTemplateBlocks(): BlockInstance[] {
    return [
        bareBlock('artisanpack/post-title'),
        bareBlock('artisanpack/post-featured-image'),
        bareBlock('artisanpack/post-content'),
    ];
}

function bareBlock(name: string): BlockInstance {
    return {
        name,
        attributes: {},
        innerBlocks: [],
    } as unknown as BlockInstance;
}
