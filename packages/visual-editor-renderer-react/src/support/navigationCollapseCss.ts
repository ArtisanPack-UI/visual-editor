/**
 * Navigation collapse breakpoint (#977).
 *
 * A `core/navigation` with `overlayMenu: "mobile"` collapses behind its
 * open button below a fixed 600px. Its `collapseBreakpoint` attribute
 * (`mobile` / `tablet`) moves that point to the matching device
 * breakpoint: the `<nav>` carries `collapses-at-{key}`, and `BlockTree`
 * emits the rules for every key in the tree once, in a
 * `<style data-ve-navigation-collapse>` tag. The rules keep the drawer
 * collapsed up to the breakpoint's max-width and show the inline menu
 * above it.
 *
 * Unlike `NAVIGATION_OVERLAY_CSS` the rules are not layered: they must
 * beat the block-library stylesheet's unlayered 600px rules when a host
 * loads it. They're scoped to the opt-in class, so they reach no other
 * navigation.
 *
 * Mirrors `NavigationCollapseStyles` in the Blade renderer byte for byte
 * (kept honest by the markup-parity check). The mirror lives at the
 * matching path in the Vue renderer.
 *
 * @since 1.15.2
 */

import { NAVIGATION_BLOCK_NAMES } from '../templateParts';
import type { Block } from '../types';
import { getDeviceBreakpoints } from '../visibility';

/** Breakpoint keys a navigation may collapse at. Mirrors `NavigationCollapseStyles::KEYS`. */
export const NAVIGATION_COLLAPSE_KEYS: readonly string[] = ['mobile', 'tablet'];

/** The `<nav>` class prefix; the key is appended. */
export const NAVIGATION_COLLAPSE_CLASS_PREFIX = 'collapses-at-';

/**
 * Resolve a navigation's collapse breakpoint: the key and its max-width,
 * or `null` for the legacy 600px behavior (attribute absent, empty or
 * unknown, overlay not `mobile`, or no registry entry for the key).
 * Mirrors `NavigationCollapseStyles::resolve()`.
 */
export function resolveNavigationCollapse(
    attributes: Record<string, unknown> | undefined
): { key: string; maxWidth: number } | null {
    const overlayMenu = typeof attributes?.overlayMenu === 'string' ? attributes.overlayMenu : 'mobile';
    const key = typeof attributes?.collapseBreakpoint === 'string' ? attributes.collapseBreakpoint : '';

    if (overlayMenu !== 'mobile' || !NAVIGATION_COLLAPSE_KEYS.includes(key)) {
        return null;
    }

    const entry = getDeviceBreakpoints().find((breakpoint) => breakpoint.key === key);

    if (entry === undefined || !(entry.maxWidthPx > 0)) {
        return null;
    }

    return { key, maxWidth: entry.maxWidthPx };
}

/** The `<nav>` class for a navigation's collapse breakpoint, or `null`. */
export function navigationCollapseClass(attributes: Record<string, unknown> | undefined): string | null {
    const collapse = resolveNavigationCollapse(attributes);

    return collapse === null ? null : `${NAVIGATION_COLLAPSE_CLASS_PREFIX}${collapse.key}`;
}

/** The collapse rules for one breakpoint. Mirrors `NavigationCollapseStyles::css()`. */
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

/**
 * The collapse rules for every breakpoint a navigation in the tree
 * collapses at, each once, in first-use order. `''` when none does.
 */
export function treeNavigationCollapseCss(blocks: Block[]): string {
    const emitted = new Map<string, string>();

    const visit = (nodes: Block[]): void => {
        for (const block of nodes) {
            if (NAVIGATION_BLOCK_NAMES.has(block.name)) {
                const collapse = resolveNavigationCollapse(block.attributes);

                if (collapse !== null && !emitted.has(collapse.key)) {
                    emitted.set(collapse.key, navigationCollapseCss(collapse.key, collapse.maxWidth));
                }
            }

            if (Array.isArray(block.innerBlocks)) {
                visit(block.innerBlocks);
            }
        }
    };

    visit(blocks);

    return Array.from(emitted.values()).join('');
}
