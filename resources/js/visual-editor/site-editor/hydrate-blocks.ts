/**
 * Block-tree hydration helpers shared between the entity-editor and
 * pattern-editor hooks.
 *
 * Both hooks receive entity content as `{ raw: string, blocks: object[] }`.
 * When `raw` is non-empty we run it through Gutenberg's `parse()` which
 * already fills in schema defaults from the registered block-type. When
 * only the `blocks` array is present (the database-JSON path our REST
 * envelope takes for templates / template parts / patterns), `parse()`
 * never runs and a block whose `edit()` reads a default-valued attribute
 * (e.g. `core/separator`'s `tagName`) gets `undefined` instead of `"hr"`,
 * renders `<undefined />`, and trips React error #130 (Keystone #49).
 *
 * `applySchemaDefaults` walks the pre-parsed tree and fills in any
 * registered attribute defaults the persisted JSON is missing,
 * mirroring the part of `parse()`'s pipeline that hydrates schema
 * defaults from the block-type registry.
 */

import { getBlockType, parse, type BlockInstance } from '@wordpress/blocks';

export interface LoadedContent {
    raw?: string;
    blocks: unknown[];
}

/**
 * Walk a pre-parsed block tree and fill in any registered attribute
 * defaults the persisted JSON is missing. Recurses into `innerBlocks`
 * so nested blocks get the same treatment. Unregistered blocks are
 * left alone — the renderer's fallback handles them, and reading a
 * non-existent block type's defaults would no-op anyway.
 */
export function applySchemaDefaults(blocks: BlockInstance[]): BlockInstance[] {
    return blocks.map((block) => {
        const blockType = getBlockType(block.name);
        const schema = (blockType?.attributes ?? {}) as Record<
            string,
            { default?: unknown }
        >;

        const filled: Record<string, unknown> = { ...(block.attributes ?? {}) };
        let mutated = false;

        for (const [key, definition] of Object.entries(schema)) {
            if (definition?.default === undefined) {
                continue;
            }

            if (filled[key] === undefined) {
                filled[key] = definition.default;
                mutated = true;
            }
        }

        const inner =
            Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0
                ? applySchemaDefaults(block.innerBlocks)
                : block.innerBlocks;

        if (!mutated && inner === block.innerBlocks) {
            return block;
        }

        return {
            ...block,
            attributes: filled as BlockInstance['attributes'],
            innerBlocks: inner,
        };
    });
}

/**
 * Hydrate a content envelope into `BlockInstance[]`. Prefers
 * `content.raw` (canonical Gutenberg HTML form — `parse()` guarantees
 * fresh `clientId`s and fills schema defaults); falls back to
 * `content.blocks` when `raw` is empty, applying schema defaults
 * manually so the parsed-JSON path matches `parse()`'s output shape.
 */
export function hydrateBlocks(content: LoadedContent): BlockInstance[] {
    const raw = typeof content.raw === 'string' ? content.raw.trim() : '';

    if (raw !== '') {
        return parse(raw);
    }

    return applySchemaDefaults(content.blocks as BlockInstance[]);
}
