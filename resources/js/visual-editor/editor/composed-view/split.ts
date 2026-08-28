/**
 * Split an applied template's block list into the chrome that renders
 * before the content slot (header) and the chrome that renders after
 * (footer).
 *
 * The composed view renders those two lists as inert block previews above
 * and below the live block canvas (see `chrome-blocks.tsx`), so the split
 * point is wherever the template's `post-content` slot sits.
 *
 * The slot is rarely a top-level sibling. The conventional template shape
 * nests it inside a layout wrapper:
 *
 *     template-part (header)
 *     group[tagName=main]
 *         post-content          <- the slot
 *     template-part (footer)
 *
 * so a top-level-only scan would find no slot and dump the entire template
 * into the header. Instead the walk descends into `innerBlocks`, and when
 * the slot is found inside a wrapper the wrapper is *cloned onto both
 * sides* — the header keeps the children that preceded the slot, the
 * footer keeps the ones that followed. Wrapper attributes ride along on
 * both copies so the surrounding layout still reads correctly; a side that
 * ends up with no children is dropped rather than rendered empty.
 *
 * This is an approximation by construction — a constrained wrapper split in
 * two is not what the front end paints. #618 scopes the composed view to
 * "close enough to see the shape" and leaves pixel parity to Preview.
 *
 * Template-part refs are *not* expanded here. Since #675 the
 * `artisanpack/template-part` block resolves its own referenced part
 * through the core-data shim, and the applied-template endpoint rewrites
 * `core/template-part` into that fork, so expanding them here would render
 * each part twice.
 *
 * @since 1.1.0
 */

import type { BlockInstance } from '@wordpress/blocks';

/**
 * Block names treated as the content slot. The endpoint rewrites core
 * names to their forks, but the raw core name is still accepted so a host
 * that supplies its own already-forked payload — or an older cached
 * response — splits the same way.
 */
const CONTENT_SLOT_NAMES: ReadonlySet<string> = new Set([
    'artisanpack/post-content',
    'core/post-content',
]);

export interface SplitTemplateResult {
    /** Chrome rendered above the block canvas. */
    header: readonly BlockInstance[];
    /** Chrome rendered below the block canvas. */
    footer: readonly BlockInstance[];
    templateName: string;
    /**
     * Whether a content slot was found. When `false` the whole template is
     * returned as `header` — the author still sees the template's shape,
     * and the caller can flag that the content position is a guess.
     */
    slotFound: boolean;
}

export function splitTemplateAroundContentSlot(
    blocks: readonly BlockInstance[],
    templateName: string
): SplitTemplateResult {
    const split = splitBlocks(blocks);

    if (!split.found) {
        return {
            header: blocks,
            footer: [],
            templateName,
            slotFound: false,
        };
    }

    return {
        header: split.before,
        footer: split.after,
        templateName,
        slotFound: true,
    };
}

interface SplitResult {
    before: BlockInstance[];
    after: BlockInstance[];
    found: boolean;
}

function splitBlocks(blocks: readonly BlockInstance[]): SplitResult {
    const before: BlockInstance[] = [];
    const after: BlockInstance[] = [];
    let found = false;

    for (const block of blocks) {
        // The split runs *before* hydration (see `editor-app.tsx`), so
        // `hydrateBlocks`' own guard never covers this walk. A `null`
        // element or a block the server serialized without a `name` would
        // throw here — inside a `useMemo` with no error boundary above it,
        // white-screening the editor instead of taking the fallback path.
        if (typeof block?.name !== 'string') {
            continue;
        }

        if (found) {
            after.push(block);
            continue;
        }

        if (CONTENT_SLOT_NAMES.has(block.name)) {
            found = true;
            continue;
        }

        // Anything that isn't an array counts as absent. `null` in
        // particular reaches this line as a valid payload — the response
        // validator treats a null `innerBlocks` the same as an omitted one.
        const inner = Array.isArray(block.innerBlocks)
            ? block.innerBlocks
            : undefined;

        if (inner !== undefined && inner.length > 0) {
            const innerSplit = splitBlocks(inner);

            if (innerSplit.found) {
                found = true;

                if (innerSplit.before.length > 0) {
                    before.push({ ...block, innerBlocks: innerSplit.before });
                }

                if (innerSplit.after.length > 0) {
                    after.push({ ...block, innerBlocks: innerSplit.after });
                }

                continue;
            }
        }

        before.push(block);
    }

    return { before, after, found };
}
