/**
 * Forked-block cutover.
 *
 * Once a `core/*` block has an `artisanpack/*` fork (clusters I1–I4), this
 * hides the upstream block from the inserter so authors only reach for the
 * fork. The core block stays registered, so legacy `<!-- wp:* -->` markup
 * still deserializes through its deprecation chain — only the inserter
 * surface changes. Same intent as the I0 paragraph cutover, which is
 * handled separately because it also re-homes the editor's default block.
 *
 * The suppression is applied through the `blocks.registerBlockType` filter
 * installed *before* `registerCoreBlocks()` runs, so the core blocks
 * register with `inserter: false` from the start — no post-registration
 * mutation of the block registry is required.
 */

import { addFilter, hasFilter } from '@wordpress/hooks';

/**
 * `core/*` blocks superseded by an `artisanpack/*` fork (I1–I4).
 *
 * `core/paragraph` is intentionally absent — the paragraph cutover handles
 * it. `core/row` and `core/stack` are variations of `core/group`, so
 * suppressing `core/group` covers them.
 */
export const FORKED_CORE_BLOCKS: readonly string[] = [
    // Content (I1)
    'core/heading',
    'core/list',
    'core/list-item',
    'core/quote',
    'core/code',
    'core/preformatted',
    'core/pullquote',
    'core/verse',
    'core/table',
    // Media (I2)
    'core/image',
    'core/gallery',
    'core/video',
    'core/audio',
    'core/file',
    'core/embed',
    'core/cover',
    'core/media-text',
    // Layout (I3)
    'core/columns',
    'core/column',
    'core/group',
    'core/buttons',
    'core/button',
    'core/separator',
    'core/spacer',
    'core/details',
    // Widgets (I4)
    'core/search',
    'core/latest-posts',
];

const FORKED_BLOCK_CUTOVER_FILTER =
    'artisanpack-ui/visual-editor/forked-block-cutover';

/**
 * Pure `blocks.registerBlockType` transform: stamp `supports.inserter = false`
 * on a forked core block, pass everything else through untouched. Exported
 * for testing.
 */
export function suppressForkedBlockInserter(
    settings: Record<string, unknown>,
    name: string,
    forked: ReadonlySet<string> = new Set( FORKED_CORE_BLOCKS )
): Record<string, unknown> {
    if ( ! forked.has( name ) ) {
        return settings;
    }

    const supports =
        ( settings.supports as Record<string, unknown> | undefined ) ?? {};

    return {
        ...settings,
        supports: { ...supports, inserter: false },
    };
}

/**
 * Install the forked-block inserter-suppression filter. Idempotent (guards
 * on the WordPress hooks registry rather than module state) and must run
 * before `registerCoreBlocks()` so the core blocks register with
 * `inserter: false` rather than being mutated after the fact.
 */
export function registerForkedBlockCutoverFilter(): void {
    if ( hasFilter( 'blocks.registerBlockType', FORKED_BLOCK_CUTOVER_FILTER ) ) {
        return;
    }

    const forked = new Set( FORKED_CORE_BLOCKS );

    addFilter(
        'blocks.registerBlockType',
        FORKED_BLOCK_CUTOVER_FILTER,
        ( settings: Record<string, unknown>, name: string ) =>
            suppressForkedBlockInserter( settings, name, forked )
    );
}
