import type { Block } from './types';

export function findBlock(blocks: Block[], clientId: string): Block | undefined {
    for (const block of blocks) {
        if (block.clientId === clientId) {
            return block;
        }

        const nested = findBlock(block.innerBlocks, clientId);

        if (nested) {
            return nested;
        }
    }

    return undefined;
}

export function findParent(blocks: Block[], clientId: string): Block | null {
    for (const block of blocks) {
        if (block.innerBlocks.some((child) => child.clientId === clientId)) {
            return block;
        }

        const nested = findParent(block.innerBlocks, clientId);

        if (nested) {
            return nested;
        }
    }

    return null;
}

export function findChildren(blocks: Block[], parentClientId: string | null): Block[] {
    if (parentClientId === null) {
        return blocks;
    }

    const parent = findBlock(blocks, parentClientId);

    return parent ? parent.innerBlocks : [];
}

function clampIndex(length: number, index: number | undefined): number {
    if (index === undefined || index < 0 || index > length) {
        return length;
    }

    return index;
}

export function insertIntoTree(
    blocks: Block[],
    block: Block,
    parentClientId: string | null | undefined,
    index: number | undefined
): Block[] {
    if (parentClientId === null || parentClientId === undefined) {
        const next = blocks.slice();

        next.splice(clampIndex(blocks.length, index), 0, block);

        return next;
    }

    let changed = false;

    const next = blocks.map((current) => {
        if (current.clientId === parentClientId) {
            const nextInner = current.innerBlocks.slice();

            nextInner.splice(clampIndex(current.innerBlocks.length, index), 0, block);

            changed = true;

            return { ...current, innerBlocks: nextInner };
        }

        if (current.innerBlocks.length === 0) {
            return current;
        }

        const nextInner = insertIntoTree(current.innerBlocks, block, parentClientId, index);

        if (nextInner === current.innerBlocks) {
            return current;
        }

        changed = true;

        return { ...current, innerBlocks: nextInner };
    });

    return changed ? next : blocks;
}

export function removeFromTree(blocks: Block[], clientId: string): Block[] {
    let changed = false;

    const next = blocks
        .filter((block) => {
            if (block.clientId === clientId) {
                changed = true;

                return false;
            }

            return true;
        })
        .map((block) => {
            if (block.innerBlocks.length === 0) {
                return block;
            }

            const nextInner = removeFromTree(block.innerBlocks, clientId);

            if (nextInner === block.innerBlocks) {
                return block;
            }

            changed = true;

            return { ...block, innerBlocks: nextInner };
        });

    return changed ? next : blocks;
}

export function updateAttributesInTree(
    blocks: Block[],
    clientId: string,
    attrs: Record<string, unknown>
): Block[] {
    let changed = false;

    const next = blocks.map((block) => {
        if (block.clientId === clientId) {
            changed = true;

            return { ...block, attributes: { ...block.attributes, ...attrs } };
        }

        if (block.innerBlocks.length === 0) {
            return block;
        }

        const nextInner = updateAttributesInTree(block.innerBlocks, clientId, attrs);

        if (nextInner === block.innerBlocks) {
            return block;
        }

        changed = true;

        return { ...block, innerBlocks: nextInner };
    });

    return changed ? next : blocks;
}
