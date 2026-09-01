/**
 * ArtisanPack block registration entrypoint.
 *
 * I7 (#415) cutover: replaces the `registerCoreBlocks()` call from
 * `@wordpress/block-library`. All blocks are now `artisanpack/*`
 * namespace — discovered via Vite's `import.meta.glob` in
 * `custom-blocks.ts` and registered through `@wordpress/blocks`.
 *
 * Both `editor-app.tsx` and `site-editor-app.tsx` call this function
 * once at boot in place of the former core block registration path.
 */

import { setDefaultBlockName, setGroupingBlockName } from '@wordpress/blocks';

import { discoverAndRegisterCustomBlocks } from '../editor/custom-blocks';
import { markExternalBlocksReady } from '../editor/external-block-registration';
import { registerServerRenderedBlocks } from '../editor/server-blocks';

/**
 * Register every `artisanpack/*` block and configure the editor's
 * special block name slots.
 *
 * After the build-time blocks register, the two runtime third-party seams
 * (#766) open: the pre-boot client escape-hatch queue is flushed, and the
 * server-rendered blocks a downstream registered in PHP are fetched and
 * registered. The server pass is fire-and-forget — it must never block boot,
 * and `@wordpress/blocks`' block-types store is reactive, so a block that
 * lands a moment after mount still appears in the inserter. Both are additive
 * and deduped, so the failure of either leaves the built-in blocks intact.
 *
 * Idempotent — subsequent calls are no-ops because the internal registration
 * cache in `custom-blocks.ts` deduplicates by block name, the setters are
 * simple assignments, and the runtime seams dedupe by name too.
 */
export function registerArtisanPackBlocks(): void {
    const registered = discoverAndRegisterCustomBlocks();

    if (registered.includes('artisanpack/paragraph')) {
        setDefaultBlockName('artisanpack/paragraph');
    }

    if (registered.includes('artisanpack/group')) {
        setGroupingBlockName('artisanpack/group');
    }

    // Runtime third-party registration (#766): flush any host blocks queued
    // before boot, then pull in the PHP-registered server blocks.
    markExternalBlocksReady();
    void registerServerRenderedBlocks();
}
