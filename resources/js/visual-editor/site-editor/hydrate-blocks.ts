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

let clientIdFallbackCounter = 0;

function mintClientId(): string {
    if (
        typeof globalThis.crypto !== 'undefined'
        && typeof globalThis.crypto.randomUUID === 'function'
    ) {
        return globalThis.crypto.randomUUID();
    }

    clientIdFallbackCounter += 1;

    return `ap-hydrated-${Date.now().toString(36)}-${clientIdFallbackCounter.toString(36)}`;
}

/**
 * Give server-parsed blocks the editor-instance fields they lack.
 *
 * Trees the site editor saved are full `BlockInstance`s and pass through
 * untouched. Trees the server parsed from markup — e.g. a template part
 * created from Gutenberg's serialized-string payload (#809) — arrive as
 * bare `{name, attributes, innerBlocks}` with no `clientId`, and
 * `BlockEditorProvider` renders nothing for them. Those get a fresh
 * `clientId`, `isValid: true`, and object-shaped attributes (PHP encodes
 * an empty array as `[]`).
 *
 * Deliberately not `createBlock()`: it swaps unregistered names for
 * `core/missing`, which this package doesn't register either, so it
 * recurses until the stack overflows.
 */
export function ensureEditorInstances(blocks: readonly unknown[]): BlockInstance[] {
    const out: BlockInstance[] = [];

    for (const candidate of blocks) {
        if (candidate === null || typeof candidate !== 'object') {
            continue;
        }

        const block = candidate as Partial<BlockInstance> & Record<string, unknown>;

        if (typeof block.name !== 'string') {
            continue;
        }

        const innerBlocks = Array.isArray(block.innerBlocks)
            ? ensureEditorInstances(block.innerBlocks)
            : [];

        if (typeof block.clientId === 'string' && block.clientId !== '') {
            out.push({ ...block, innerBlocks } as BlockInstance);
            continue;
        }

        const attributes =
            block.attributes !== null
            && typeof block.attributes === 'object'
            && !Array.isArray(block.attributes)
                ? block.attributes
                : {};

        out.push({
            ...block,
            clientId: mintClientId(),
            isValid: true,
            attributes,
            innerBlocks,
        } as BlockInstance);
    }

    return out;
}

/**
 * Hydrate a content envelope into `BlockInstance[]`. Prefers
 * `content.raw` (canonical Gutenberg HTML form — `parse()` guarantees
 * fresh `clientId`s and fills schema defaults); falls back to
 * `content.blocks` when `raw` is empty, minting any missing editor
 * fields and applying schema defaults manually so the parsed-JSON path
 * matches `parse()`'s output shape.
 */
export function hydrateBlocks(content: LoadedContent): BlockInstance[] {
    const raw = typeof content.raw === 'string' ? content.raw.trim() : '';

    if (raw !== '') {
        return parse(raw);
    }

    return applySchemaDefaults(ensureEditorInstances(content.blocks));
}
