/**
 * Register the upstream `core/*` blocks that `artisanpack/*` forks
 * delegate to at render time.
 *
 * ## Why
 *
 * Phase I5 (#413) forks a family of entity-backed core blocks into the
 * `artisanpack/*` namespace. Each fork's `edit` component looks up its
 * upstream counterpart via `getBlockType( 'core/foo' )?.edit` and, when
 * present, renders it in place of a bespoke implementation
 * (see `blocks/_shared/forked-entity-edit.tsx`).
 *
 * The I7 cutover (#415) then replaced `registerCoreBlocks()` with the
 * first-party `artisanpack/*` registration in `blocks/index.ts` — but
 * the forks still assume their upstream counterparts are registered
 * somewhere. Without this module the lookup returns `undefined`, the
 * fork falls back to an empty `<div>` wrapper, and blocks like
 * `artisanpack/navigation` render *nothing* on the canvas even though
 * their `ref` is set (issue #808).
 *
 * ## What
 *
 * Selectively `init()` each upstream block the forks delegate to,
 * scoped to what's actually needed. `core/navigation` also drags in a
 * parent-locked child set (`core/navigation-link`,
 * `core/navigation-submenu`, `core/page-list`, `core/home-link`,
 * `core/loginout`) that its own edit surface renders as inner blocks —
 * those are registered here too.
 *
 * `registerForkedBlockCutoverFilter()` MUST run before these `init()`
 * calls so the core blocks register with `inserter: false` and don't
 * show up in the block inserter alongside their `artisanpack/*`
 * counterparts.
 *
 * The register calls are idempotent: `initBlock` from `@wordpress/block-
 * library` guards on the block-types store, so calling this on
 * successive boots (e.g. site-editor → post-editor) is a no-op after
 * the first.
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
 * Register the upstream core blocks that `artisanpack/*` forks
 * delegate to. Safe to call multiple times.
 */
export function registerForkedCoreBlocks(): void {
    // Install the inserter-suppression filter FIRST so the core blocks
    // pick it up as they register below.
    registerForkedBlockCutoverFilter();

    initNavigation();
    initNavigationLink();
    initNavigationSubmenu();
    initPageList();
    initPageListItem();
    initHomeLink();
    initLoginout();
}
