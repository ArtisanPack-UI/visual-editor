/**
 * Register the upstream `core/navigation` family that the artisanpack
 * block set does not fork.
 *
 * ## Why
 *
 * I7 (#415) replaced `registerCoreBlocks()` with first-party
 * `artisanpack/*` registration in `blocks/index.ts` — so the upstream
 * `core/*` blocks are no longer registered wholesale. `core/navigation`
 * was originally forked in Phase I5 (#413), but the fork was reverted
 * in #808 (upstream Gutenberg hardcodes the `core/navigation` name in
 * too many places — parent lookups, LinkControl mount effects,
 * prioritized inserter blocks — for an alias to work reliably). With
 * the fork gone, `core/navigation` needs an explicit registration path,
 * and its parent-locked inner-block family (`core/navigation-link`,
 * `core/navigation-submenu`, `core/page-list`, `core/home-link`,
 * `core/loginout`) must register alongside it or the block's edit
 * surface refuses to build a menu.
 *
 * ## What
 *
 * Selectively `init()` each of those blocks. `initBlock` from
 * `@wordpress/block-library` calls `registerBlockType` unguarded (a
 * second call logs "Block … is already registered"), so each `init` is
 * gated here on `getBlockType( name )` — calling this on successive
 * boots (site editor → post editor) is a no-op after the first.
 *
 * `core/navigation` is also opted into the ArtisanPack block
 * animations feature (#489) via a `blocks.registerBlockType` filter
 * that stamps `supports.artisanpackAnimations`. The 1.11 fork declared
 * that support in its own `block.json`; without it, the legacy
 * `artisanpack/navigation` → `core/navigation` migration (see
 * `blocks/navigation/edit.tsx`) would strip the saved animation config.
 * The filter runs at an earlier priority than the animations attribute
 * injector (`animations/register-attribute.ts`, default priority 10), so
 * the attribute lands regardless of which of the two filters was added
 * first.
 *
 * The forked-block inserter-suppression filter is installed FIRST so
 * that any block in this family which the cutover treats as forked
 * (currently none — nav's family is not on the forked list) still
 * lands with the right supports shape.
 */

import { init as initNavigation } from '@wordpress/block-library/build-module/navigation/index.mjs';
import { init as initNavigationLink } from '@wordpress/block-library/build-module/navigation-link/index.mjs';
import { init as initNavigationSubmenu } from '@wordpress/block-library/build-module/navigation-submenu/index.mjs';
import { init as initPageList } from '@wordpress/block-library/build-module/page-list/index.mjs';
import { init as initPageListItem } from '@wordpress/block-library/build-module/page-list-item/index.mjs';
import { init as initHomeLink } from '@wordpress/block-library/build-module/home-link/index.mjs';
import { init as initLoginout } from '@wordpress/block-library/build-module/loginout/index.mjs';

import { getBlockType } from '@wordpress/blocks';
import { addFilter, hasFilter } from '@wordpress/hooks';

import { registerForkedBlockCutoverFilter } from './forked-block-cutover';

/**
 * Upstream blocks registered here that opt into ArtisanPack block
 * animations (`supports.artisanpackAnimations`).
 *
 * @since 1.12.0
 */
export const ANIMATED_FORKED_CORE_BLOCKS: readonly string[] = [
    'core/navigation',
];

const ANIMATIONS_SUPPORT_FILTER =
    'artisanpack-ui/visual-editor/forked-cores-animations-support';

/**
 * Runs before the animations attribute injector (priority 10) so the
 * support flag is present when that filter inspects the settings.
 */
const ANIMATIONS_SUPPORT_PRIORITY = 5;

/**
 * Pure `blocks.registerBlockType` transform: stamp
 * `supports.artisanpackAnimations = true` on the listed upstream blocks,
 * pass everything else through untouched. A block that already declares
 * the support (any value) is left as-is. Exported for testing.
 *
 * @since 1.12.0
 *
 * @param settings Block settings being registered.
 * @param name     Fully-qualified block name.
 * @return Settings with the animations support applied when listed.
 */
export function addForkedCoreAnimationsSupport(
    settings: Record<string, unknown>,
    name: string,
): Record<string, unknown> {
    if (!ANIMATED_FORKED_CORE_BLOCKS.includes(name)) {
        return settings;
    }

    const supports =
        (settings.supports as Record<string, unknown> | undefined) ?? {};

    if ('artisanpackAnimations' in supports) {
        return settings;
    }

    return {
        ...settings,
        supports: { ...supports, artisanpackAnimations: true },
    };
}

/**
 * Install the animations-support filter for the upstream blocks
 * registered here. Idempotent (guards on the hooks registry).
 *
 * @since 1.12.0
 */
export function registerForkedCoreAnimationsSupportFilter(): void {
    if (hasFilter('blocks.registerBlockType', ANIMATIONS_SUPPORT_FILTER)) {
        return;
    }

    addFilter(
        'blocks.registerBlockType',
        ANIMATIONS_SUPPORT_FILTER,
        addForkedCoreAnimationsSupport,
        ANIMATIONS_SUPPORT_PRIORITY,
    );
}

/**
 * Upstream `init` functions keyed by the block name they register.
 */
const FORKED_CORE_INITS: ReadonlyArray<[string, () => unknown]> = [
    ['core/navigation', initNavigation],
    ['core/navigation-link', initNavigationLink],
    ['core/navigation-submenu', initNavigationSubmenu],
    ['core/page-list', initPageList],
    ['core/page-list-item', initPageListItem],
    ['core/home-link', initHomeLink],
    ['core/loginout', initLoginout],
];

/**
 * Register the upstream `core/navigation` family. Safe to call multiple
 * times — each `init` is skipped when its block type is already
 * registered.
 */
export function registerForkedCoreBlocks(): void {
    // Install the inserter-suppression filter FIRST so any forked block
    // that later registers picks it up (nav itself is no longer forked,
    // but other forks may register through the same boot path).
    registerForkedBlockCutoverFilter();
    registerForkedCoreAnimationsSupportFilter();

    for (const [name, init] of FORKED_CORE_INITS) {
        if (!getBlockType(name)) {
            init();
        }
    }
}
