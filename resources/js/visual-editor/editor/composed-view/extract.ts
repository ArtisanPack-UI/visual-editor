/**
 * Inverse of `composeBlocks` — pulls the user's real content blocks back
 * out of a composed tree so the existing persistence loop only ever sees
 * (and saves) the content-region blocks. Chrome blocks and the content-slot
 * host itself are discarded.
 *
 * @since 1.1.0
 */

import type { BlockInstance } from '@wordpress/blocks';

import { COMPOSED_CONTENT_SLOT_MARKER } from './compose';

/**
 * Return the `innerBlocks` of the first content-slot block found in the
 * composed tree. Returns `null` if no slot is present (defensive — the
 * caller should skip persistence in that case rather than treat the whole
 * composed tree as content).
 */
export function extractContentBlocks(
    composedTree: readonly BlockInstance[]
): BlockInstance[] | null {
    for (const block of composedTree) {
        if (isContentSlot(block)) {
            return stripSlotMarker(block.innerBlocks);
        }

        if (block.innerBlocks.length > 0) {
            const nested = extractContentBlocks(block.innerBlocks);

            if (nested !== null) {
                return nested;
            }
        }
    }

    return null;
}

function isContentSlot(block: BlockInstance): boolean {
    const attrs = block.attributes as Record<string, unknown> | undefined;

    return attrs?.[COMPOSED_CONTENT_SLOT_MARKER] === true;
}

// `innerBlocks` are the user's real blocks — pass them through untouched.
// The marker only lives on the slot host, not on its children.
function stripSlotMarker(
    blocks: readonly BlockInstance[]
): BlockInstance[] {
    return blocks.map((block) => block);
}
