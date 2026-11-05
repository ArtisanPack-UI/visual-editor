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
 * Fill in the registered attribute defaults a block's stored attributes
 * are missing. Returns `attributes` itself when there's nothing to fill.
 * Unregistered blocks are left alone.
 */
function fillSchemaDefaults(
    name: string,
    attributes: Record<string, unknown>
): Record<string, unknown> {
    const schema = (getBlockType(name)?.attributes ?? {}) as Record<
        string,
        { default?: unknown }
    >;

    let filled = attributes;

    for (const [key, definition] of Object.entries(schema)) {
        if (definition?.default === undefined || attributes[key] !== undefined) {
            continue;
        }

        if (filled === attributes) {
            filled = { ...attributes };
        }

        filled[key] = definition.default;
    }

    return filled;
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
        const attributes = (block.attributes ?? {}) as Record<string, unknown>;
        const filled = fillSchemaDefaults(block.name, attributes);

        const inner =
            Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0
                ? applySchemaDefaults(block.innerBlocks)
                : block.innerBlocks;

        if (filled === attributes && inner === block.innerBlocks) {
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
 * Trees the editors saved are full `BlockInstance`s and keep their fields.
 * Trees that came from anywhere else — a template part created from
 * Gutenberg's serialized-string payload (#809), a page seeded with
 * `setBlockContent()` (#1055) — arrive as bare
 * `{name, attributes, innerBlocks}` with no `clientId`, and
 * `BlockEditorProvider` renders nothing for them because its store keys
 * every block by `clientId`. Any missing field gets filled independently:
 * a fresh `clientId`, `isValid: true`, and object-shaped attributes (PHP
 * encodes an empty array as `[]`).
 *
 * Blocks without a stored `clientId` also get their schema defaults, since
 * nothing ever ran `createBlock()` for them. Saved blocks don't: a block
 * can clear a defaulted attribute on purpose, as a fill spacer does with
 * `height`, and restoring it would make the block clear it again on every
 * load, which reads as an edit and autosaves.
 *
 * The `blockName` and `attrs` keys are read the way `BlockShape` reads them
 * on the server, which renders those blocks, and aren't kept: a stale copy
 * would sit next to the live attributes after the first save.
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

        const { blockName, attrs, ...block } = candidate as Partial<BlockInstance>
            & Record<string, unknown>;
        const name = [block.name, blockName, block.type].find(
            (value): value is string => typeof value === 'string' && value !== '',
        );

        if (name === undefined) {
            continue;
        }

        const innerBlocks = Array.isArray(block.innerBlocks)
            ? ensureEditorInstances(block.innerBlocks)
            : [];

        const hasClientId = typeof block.clientId === 'string' && block.clientId !== '';
        const attributes = (
            [block.attributes, attrs].find(
                (value) => value !== null && typeof value === 'object' && !Array.isArray(value),
            ) ?? {}
        ) as Record<string, unknown>;

        out.push({
            ...block,
            name,
            clientId: hasClientId ? block.clientId : mintClientId(),
            isValid: block.isValid ?? true,
            attributes: hasClientId ? attributes : fillSchemaDefaults(name, attributes),
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
