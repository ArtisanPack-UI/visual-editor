import type { InserterBlock } from './inserterRegistry';

/**
 * Filters the inserter block list by a free-text query. Matches against
 * the block title, name, description, and registered keywords, all
 * lowercased. An empty query returns the list unchanged.
 */
export function filterInserterBlocks(
    blocks: readonly InserterBlock[],
    query: string
): InserterBlock[] {
    const normalized = query.trim().toLowerCase();

    if (normalized.length === 0) {
        return blocks.slice();
    }

    return blocks.filter((block) => {
        if (block.title.toLowerCase().includes(normalized)) {
            return true;
        }

        if (block.name.toLowerCase().includes(normalized)) {
            return true;
        }

        if (block.description && block.description.toLowerCase().includes(normalized)) {
            return true;
        }

        return (
            block.keywords?.some((keyword) => keyword.toLowerCase().includes(normalized)) ?? false
        );
    });
}
