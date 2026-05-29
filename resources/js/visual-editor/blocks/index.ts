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

/**
 * Register every `artisanpack/*` block and configure the editor's
 * special block name slots.
 *
 * Idempotent — subsequent calls are no-ops because the internal
 * registration cache in `custom-blocks.ts` deduplicates by block name
 * and the setters are simple assignments.
 */
export function registerArtisanPackBlocks(): void {
    const registered = discoverAndRegisterCustomBlocks();

    if (registered.includes('artisanpack/paragraph')) {
        setDefaultBlockName('artisanpack/paragraph');
    }

    if (registered.includes('artisanpack/group')) {
        setGroupingBlockName('artisanpack/group');
    }
}
