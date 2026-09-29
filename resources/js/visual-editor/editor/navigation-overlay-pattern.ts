/**
 * Starter content for new navigation-overlay template parts (#809).
 *
 * `core/navigation`'s Create Overlay action seeds the new template part
 * from the `core/navigation-overlay` block pattern. WordPress registers
 * that pattern server-side; we don't, so upstream falls back to
 * `serialize([createBlock('core/paragraph')])`. `core/paragraph` isn't
 * registered here (the package ships `artisanpack/paragraph`), and
 * `createBlock` swaps unregistered names for `core/missing` — which isn't
 * registered either, so it recurses until the stack overflows. Upstream
 * swallows the error into a snackbar notice this editor doesn't render,
 * so the button silently does nothing (and in Safari the recursion can
 * hang the tab).
 *
 * Shipping the pattern through the block-editor settings means upstream
 * never reaches that fallback. The content is a vertical `core/navigation`
 * with its own overlay switched off — the same starting point WordPress
 * uses — built only from blocks the package registers.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

/** Pattern name upstream looks up via `getPatternBySlug()`. */
export const NAVIGATION_OVERLAY_PATTERN_NAME = 'core/navigation-overlay';

export const NAVIGATION_OVERLAY_PATTERN_CONTENT =
    '<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} /-->';

export interface BlockPatternSetting {
    name: string;
    title: string;
    content: string;
    inserter: boolean;
}

/**
 * Pattern entry for `settings.__experimentalBlockPatterns`, consumed by
 * the shared `editorSettings` in `editor-settings.ts`.
 */
export function getNavigationOverlayPattern(): BlockPatternSetting {
    return {
        name: NAVIGATION_OVERLAY_PATTERN_NAME,
        title: __('Navigation Overlay', TEXT_DOMAIN),
        content: NAVIGATION_OVERLAY_PATTERN_CONTENT,
        // Seed-only: keep it out of the inserter's Patterns tab.
        inserter: false,
    };
}
