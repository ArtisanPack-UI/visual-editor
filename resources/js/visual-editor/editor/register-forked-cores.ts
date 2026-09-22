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
 * Selectively `init()` each of those blocks. The register calls are
 * idempotent — `initBlock` from `@wordpress/block-library` guards on
 * the block-types store, so calling this on successive boots (site
 * editor → post editor) is a no-op after the first.
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

import { registerForkedBlockCutoverFilter } from './forked-block-cutover';

/**
 * Register the upstream `core/navigation` family. Safe to call multiple
 * times — each `init` is guarded by `@wordpress/block-library`.
 */
export function registerForkedCoreBlocks(): void {
    // Install the inserter-suppression filter FIRST so any forked block
    // that later registers picks it up (nav itself is no longer forked,
    // but other forks may register through the same boot path).
    registerForkedBlockCutoverFilter();

    initNavigation();
    initNavigationLink();
    initNavigationSubmenu();
    initPageList();
    initPageListItem();
    initHomeLink();
    initLoginout();
}
