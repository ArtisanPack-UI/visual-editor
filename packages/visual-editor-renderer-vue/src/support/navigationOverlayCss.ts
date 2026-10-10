/**
 * Navigation overlay CSS (#804).
 *
 * The Blade renderer leans on the bundled block-library stylesheet for
 * the responsive-container breakpoints and emits its own overlay
 * fix-ups once per response from the `core/navigation` partial. React
 * and Vue hosts are not required to load that stylesheet, so
 * `BlockTree` emits this once — in a `<style data-ve-navigation-overlay>`
 * tag — whenever the tree holds a navigation block with its overlay
 * switched on.
 *
 * The first group is the subset of the upstream block-library overlay
 * rules the drawer needs to work (hidden behind the open button below
 * 600px, inline above it, full-screen dialog while open). The second
 * group mirrors the fix-ups in the Blade partial: stretch the open
 * drawer's items to the inline-start edge, and swap the menu for the
 * `overlay` template part's content while the drawer is open.
 *
 * Everything sits in the `ve-navigation` cascade layer, so unlayered host
 * theme styles (and the block-library stylesheet, when loaded) win over
 * these defaults regardless of selector specificity.
 *
 * @since 1.13.0
 */

import { NAVIGATION_BLOCK_NAMES } from '../templateParts';
import type { Block } from '../types';

export const NAVIGATION_OVERLAY_CSS =
    '@layer ve-navigation {\n' +
    '.wp-block-navigation__responsive-container { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; }\n' +
    '.wp-block-navigation__responsive-container :where(.wp-block-navigation-item a) { color: inherit; }\n' +
    '.wp-block-navigation__responsive-container .wp-block-navigation__responsive-container-content { display: flex; flex-wrap: var(--navigation-layout-wrap, wrap); flex-direction: var(--navigation-layout-direction, initial); justify-content: var(--navigation-layout-justify, initial); align-items: var(--navigation-layout-align, initial); }\n' +
    '.wp-block-navigation__responsive-container.is-menu-open { display: flex; flex-direction: column; background-color: inherit; overflow: auto; z-index: 100000; padding: clamp(1rem, var(--wp--style--root--padding-top), 20rem) clamp(1rem, var(--wp--style--root--padding-right), 20rem) clamp(1rem, var(--wp--style--root--padding-bottom), 20rem) clamp(1rem, var(--wp--style--root--padding-left), 20rem); }\n' +
    '.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content { padding-top: calc(2rem + 24px); overflow: visible; flex-direction: column; flex-wrap: nowrap; align-items: var(--navigation-layout-justification-setting, inherit); }\n' +
    '.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__container { flex-direction: column; }\n' +
    '.wp-block-navigation:not(.has-background) .wp-block-navigation__responsive-container.is-menu-open { background-color: #fff; }\n' +
    '.wp-block-navigation:not(.has-text-color) .wp-block-navigation__responsive-container.is-menu-open { color: #000; }\n' +
    '.wp-block-navigation__responsive-container-open, .wp-block-navigation__responsive-container-close { vertical-align: middle; cursor: pointer; color: currentColor; background: transparent; border: none; margin: 0; padding: 0; text-transform: inherit; font-family: inherit; font-weight: inherit; font-size: inherit; }\n' +
    '.wp-block-navigation__responsive-container-open svg, .wp-block-navigation__responsive-container-close svg { fill: currentColor; pointer-events: none; display: block; width: 24px; height: 24px; }\n' +
    '.wp-block-navigation__responsive-container-open { display: flex; }\n' +
    '.wp-block-navigation__responsive-container-close { position: absolute; top: 0; right: 0; z-index: 2; }\n' +
    '.wp-block-navigation__responsive-close { width: 100%; }\n' +
    '.wp-block-navigation__responsive-close:focus { outline: none; }\n' +
    '.wp-block-navigation__responsive-dialog { position: relative; }\n' +
    '.is-menu-open .wp-block-navigation__responsive-close, .is-menu-open .wp-block-navigation__responsive-dialog, .is-menu-open .wp-block-navigation__responsive-container-content { box-sizing: border-box; }\n' +
    '@media (min-width: 600px) { .wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open) { display: block; width: 100%; position: relative; z-index: auto; background-color: inherit; } .wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open) .wp-block-navigation__responsive-container-close { display: none; } .wp-block-navigation__responsive-container-open:not(.always-shown) { display: none; } }\n' +
    '.wp-block-navigation__responsive-container.is-menu-open { --navigation-layout-justification-setting: stretch; --navigation-layout-justify: flex-start; --navigation-layout-align: stretch; --wp--style--root--padding-top: 1.5rem; --wp--style--root--padding-right: 1.5rem; --wp--style--root--padding-bottom: 1.5rem; --wp--style--root--padding-left: 1.5rem; }\n' +
    '.wp-block-navigation__responsive-container-content.has-overlay-template .wp-block-navigation__overlay-content { display: none; }\n' +
    '.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content.has-overlay-template .wp-block-navigation__container { display: none; }\n' +
    '.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content.has-overlay-template .wp-block-navigation__overlay-content { display: block; }\n' +
    '}';

/**
 * Whether the tree holds a navigation block whose overlay is switched
 * on (`overlayMenu` other than `never`), i.e. whether
 * {@link NAVIGATION_OVERLAY_CSS} needs emitting.
 *
 * @since 1.13.0
 */
export function treeHasNavigationOverlay(blocks: Block[]): boolean {
    for (const block of blocks) {
        if (NAVIGATION_BLOCK_NAMES.has(block.name) && block.attributes?.overlayMenu !== 'never') {
            return true;
        }

        if (Array.isArray(block.innerBlocks) && treeHasNavigationOverlay(block.innerBlocks)) {
            return true;
        }
    }

    return false;
}
