/**
 * Navigation collapse breakpoint — attribute + shared helpers (#977).
 *
 * Upstream `core/navigation` with `overlayMenu: "mobile"` collapses behind
 * its open button below a fixed 600px, which matches none of the
 * registry's breakpoints. This module adds a `collapseBreakpoint`
 * attribute (`''` / absent = the 600px default, `mobile`, `tablet`) to the
 * upstream block, and the helpers the editor canvas uses to preview it:
 * the `collapses-at-{key}` class and the rules for one breakpoint.
 *
 * The rules mirror `NavigationCollapseStyles::css()` in the Blade
 * renderer and `support/navigationCollapseCss.ts` in the React / Vue
 * renderers, so the canvas collapses where the front end does. The
 * inspector control and canvas filters live in
 * `with-navigation-collapse-breakpoint.tsx`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.2
 */

import { addFilter, hasFilter } from '@wordpress/hooks';

/**
 * Breakpoint keys a navigation may collapse at: the registry's device
 * keys, which are also the screen-size visibility keys, so a block can
 * be hidden at exactly the collapse point.
 *
 * @since 1.15.2
 */
export const NAVIGATION_COLLAPSE_KEYS: readonly string[] = ['mobile', 'tablet'];

/** The `<nav>` class prefix; the key is appended. */
export const NAVIGATION_COLLAPSE_CLASS_PREFIX = 'collapses-at-';

const ATTRIBUTE_FILTER = 'artisanpack-ui/visual-editor/navigation-collapse-breakpoint-attribute';

/**
 * Pure `blocks.registerBlockType` transform: add the `collapseBreakpoint`
 * string attribute to `core/navigation`. No default, so an untouched
 * block serializes exactly as before. Exported for testing.
 *
 * @since 1.15.2
 *
 * @param settings Block settings being registered.
 * @param name     Fully-qualified block name.
 * @return Settings with the attribute added for `core/navigation`.
 */
export function addNavigationCollapseBreakpointAttribute(
    settings: Record<string, unknown>,
    name: string,
): Record<string, unknown> {
    if (name !== 'core/navigation') {
        return settings;
    }

    const attributes = (settings.attributes as Record<string, unknown> | undefined) ?? {};

    if ('collapseBreakpoint' in attributes) {
        return settings;
    }

    return {
        ...settings,
        attributes: { ...attributes, collapseBreakpoint: { type: 'string' } },
    };
}

/**
 * Install the attribute filter. Must run before `core/navigation`
 * registers; idempotent (guards on the hooks registry).
 *
 * @since 1.15.2
 */
export function registerNavigationCollapseBreakpointAttribute(): void {
    if (hasFilter('blocks.registerBlockType', ATTRIBUTE_FILTER)) {
        return;
    }

    addFilter('blocks.registerBlockType', ATTRIBUTE_FILTER, addNavigationCollapseBreakpointAttribute);
}

/**
 * The navigation's collapse key, or `null` for the 600px default (the
 * attribute is absent / empty / unknown, or the overlay isn't `mobile`).
 *
 * @since 1.15.2
 */
export function navigationCollapseKey(attributes: Record<string, unknown> | undefined): string | null {
    const overlayMenu = typeof attributes?.overlayMenu === 'string' ? attributes.overlayMenu : 'mobile';
    const key = typeof attributes?.collapseBreakpoint === 'string' ? attributes.collapseBreakpoint : '';

    return overlayMenu === 'mobile' && NAVIGATION_COLLAPSE_KEYS.includes(key) ? key : null;
}

/**
 * The collapse rules for one breakpoint: drawer collapsed up to
 * `maxWidth`, inline menu above it. Mirrors
 * `NavigationCollapseStyles::css()`.
 *
 * @since 1.15.2
 */
export function navigationCollapseCss(key: string, maxWidth: number): string {
    const nav = `.wp-block-navigation.${NAVIGATION_COLLAPSE_CLASS_PREFIX}${key}`;
    const container = `${nav} .wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open)`;
    const open = `${nav} .wp-block-navigation__responsive-container-open:not(.always-shown)`;

    return (
        `@media (max-width: ${maxWidth}px) {` +
        `${container} { display: none; }` +
        `${open} { display: flex; }` +
        '}' +
        `@media (min-width: ${maxWidth + 1}px) {` +
        `${container} { display: block; width: 100%; position: relative; z-index: auto; background-color: inherit; }` +
        `${container} .wp-block-navigation__responsive-container-close { display: none; }` +
        `${open} { display: none; }` +
        '}'
    );
}
