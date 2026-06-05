/**
 * Client-side synced-pattern resolution helpers.
 *
 * Mirrors the behaviour of the server-side
 * `ArtisanPackUI\VisualEditor\Resources\PatternInliner` so the React
 * renderer can produce the same inlined block tree without
 * round-tripping to the server. Hosts pass a pre-fetched list of
 * `patterns`; this module walks the tree and recursively splices each
 * `core/block` reference's blocks into the output tree.
 *
 * Only synced patterns reach this resolver. Unsynced patterns are
 * inlined into the target block tree at insert time by the editor and
 * travel as plain block trees from that point on — they never appear in
 * saved content as `core/block` references.
 *
 * Recursion guard: an in-flight stack of pattern-id keys catches cycles
 * (pattern A → pattern B → pattern A) and a depth counter caps how deep
 * the resolution chain can go (default 10). Either guard turns the
 * offending block into a marker whose `attributes._resolutionError`
 * records the reason — the registered `core/block` renderer translates
 * that into a graceful empty render in production and a visible warning
 * in dev.
 */

import type { Block } from './types';

export const DEFAULT_MAX_PATTERN_DEPTH = 10;

export type PatternResolutionError =
    | 'missing-ref'
    | 'not-found'
    | 'cycle'
    | 'depth-limit';

export interface PatternRecord {
    id: number;
    blocks: Block[];
}

export interface InlinePatternsOptions {
    patterns: PatternRecord[];
    maxDepth?: number;
}

interface WalkContext {
    patterns: PatternRecord[];
    maxDepth: number;
    stack: string[];
    depth: number;
}

export function inlinePatterns(tree: Block[], options: InlinePatternsOptions): Block[] {
    const context: WalkContext = {
        patterns: options.patterns,
        maxDepth: options.maxDepth ?? DEFAULT_MAX_PATTERN_DEPTH,
        stack: [],
        depth: 0,
    };

    return walk(tree, context);
}

function walk(tree: Block[], context: WalkContext): Block[] {
    const out: Block[] = [];

    for (const block of tree) {
        if (block === null || typeof block !== 'object' || Array.isArray(block)) {
            continue;
        }

        const name = typeof block.name === 'string' ? block.name : '';

        if (name === 'core/block') {
            out.push(resolvePattern(block, context));

            continue;
        }

        const inner = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];

        out.push({
            ...block,
            innerBlocks: walk(inner, context),
        });
    }

    return out;
}

function resolvePattern(block: Block, context: WalkContext): Block {
    const attrs = block.attributes ?? {};
    const ref = normalizeRef(attrs.ref);

    if (ref === null) {
        return markUnresolved(block, 'missing-ref');
    }

    if (context.depth >= context.maxDepth) {
        return markUnresolved(block, 'depth-limit');
    }

    const key = String(ref);

    if (context.stack.includes(key)) {
        return markUnresolved(block, 'cycle');
    }

    const pattern = findPattern(context.patterns, ref);

    if (pattern === undefined) {
        return markUnresolved(block, 'not-found');
    }

    const childContext: WalkContext = {
        ...context,
        stack: [...context.stack, key],
        depth: context.depth + 1,
    };

    return {
        clientId: block.clientId,
        name: 'core/block',
        attributes: {
            ...attrs,
            ref,
        },
        innerBlocks: walk(Array.isArray(pattern.blocks) ? pattern.blocks : [], childContext),
    };
}

function findPattern(patterns: PatternRecord[], ref: number): PatternRecord | undefined {
    return patterns.find((pattern) => pattern.id === ref);
}

function normalizeRef(value: unknown): number | null {
    if (typeof value === 'number') {
        return Number.isInteger(value) && value > 0 ? value : null;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (trimmed === '' || !/^\d+$/.test(trimmed)) {
            return null;
        }

        const parsed = Number.parseInt(trimmed, 10);

        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    }

    return null;
}

function markUnresolved(block: Block, reason: PatternResolutionError): Block {
    return {
        clientId: block.clientId,
        name: 'core/block',
        attributes: {
            ...(block.attributes ?? {}),
            _resolutionError: reason,
        },
        innerBlocks: [],
    };
}
