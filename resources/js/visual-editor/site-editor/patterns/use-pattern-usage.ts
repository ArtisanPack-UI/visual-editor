/**
 * Client-side synced-pattern usage counter.
 *
 * The C5 backend does not yet expose a usage-count column, so D5 derives
 * it on demand: when the user opens the delete confirmation dialog, we
 * walk the templates + parts list and count occurrences of `core/block`
 * blocks whose `ref` attribute points at the target pattern. Cheap for
 * the V1 catalog sizes (a few dozen entities, a few thousand blocks);
 * if scaling becomes a problem the C5 endpoint can return a precomputed
 * count and this hook can return it directly instead.
 *
 * The hook is fire-on-demand (`run()` returns a Promise) — we never
 * pre-fetch usage counts on list mount because they are only needed at
 * delete time and a list of 50 patterns would otherwise issue 50 list
 * queries on entry.
 */

import { useCallback, useRef, useState } from 'react';
import { parse } from '@wordpress/blocks';

import {
    listEntities,
    fetchEntity,
    type EntityKind,
    type EntityRecord,
    type SiteEditorApiConfig,
} from '../api-client';

export interface PatternUsageOptions {
    apiConfig: SiteEditorApiConfig;
}

export interface UsageBreakdown {
    total: number;
    perKind: Record<EntityKind, number>;
}

export interface UsePatternUsageResult {
    usage: UsageBreakdown | null;
    isLoading: boolean;
    error: string | null;
    /** Counts references to the given pattern id across templates + parts. */
    run: (patternId: number | string) => Promise<UsageBreakdown>;
    reset: () => void;
}

const TARGET_KINDS: ReadonlyArray<EntityKind> = ['template', 'template-part'];

const EMPTY_USAGE: UsageBreakdown = {
    total: 0,
    perKind: { template: 0, 'template-part': 0 },
};

interface BlockTreeNode {
    name?: unknown;
    blockName?: unknown;
    attributes?: unknown;
    innerBlocks?: unknown;
}

function readName(node: BlockTreeNode): string | null {
    if (typeof node.blockName === 'string' && node.blockName !== '') {
        return node.blockName;
    }

    if (typeof node.name === 'string' && node.name !== '') {
        return node.name;
    }

    return null;
}

function readRefAttr(node: BlockTreeNode): number | string | null {
    if (
        node.attributes === null ||
        typeof node.attributes !== 'object' ||
        node.attributes === undefined
    ) {
        return null;
    }

    const ref = (node.attributes as { ref?: unknown }).ref;

    if (typeof ref === 'number' || typeof ref === 'string') {
        return ref;
    }

    return null;
}

function readInner(node: BlockTreeNode): readonly BlockTreeNode[] {
    return Array.isArray(node.innerBlocks)
        ? (node.innerBlocks as readonly BlockTreeNode[])
        : [];
}

function countReferences(
    blocks: readonly unknown[],
    targetId: number | string
): number {
    let count = 0;
    const targetString = String(targetId);

    function walk(list: readonly unknown[]): void {
        for (const raw of list) {
            if (raw === null || typeof raw !== 'object') {
                continue;
            }

            const node = raw as BlockTreeNode;

            if (readName(node) === 'core/block') {
                const ref = readRefAttr(node);

                if (ref !== null && String(ref) === targetString) {
                    count += 1;
                }
            }

            const inner = readInner(node);

            if (inner.length > 0) {
                walk(inner);
            }
        }
    }

    walk(blocks);

    return count;
}

function blocksFromRecord(record: EntityRecord<EntityKind>): readonly unknown[] {
    const content = record.content;

    if (
        content === null ||
        typeof content !== 'object' ||
        !('blocks' in content)
    ) {
        return [];
    }

    const blocks = (content as { blocks: unknown }).blocks;

    if (!Array.isArray(blocks)) {
        return [];
    }

    if (blocks.length > 0) {
        return blocks as readonly unknown[];
    }

    // Fall back to parsing the raw HTML when the server returned an
    // empty parsed array but a non-empty raw string.
    const raw = (content as { raw?: unknown }).raw;

    if (typeof raw === 'string' && raw.trim() !== '') {
        return parse(raw);
    }

    return [];
}

export function usePatternUsage(
    options: PatternUsageOptions
): UsePatternUsageResult {
    const { apiConfig } = options;

    const [usage, setUsage] = useState<UsageBreakdown | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Counter used to invalidate concurrent / superseded `run()`
    // invocations: a later call (or `reset()`) bumps the id, and any
    // in-flight pass that resolves afterward checks its own id against
    // the latest before mutating state. Without this guard, the
    // delete dialog could surface a stale count if the user reopened
    // it for a different pattern while the first lookup was still
    // walking the templates / parts list.
    const requestIdRef = useRef(0);

    const reset = useCallback((): void => {
        requestIdRef.current += 1;
        setUsage(null);
        setIsLoading(false);
        setError(null);
    }, []);

    const run = useCallback(
        async (patternId: number | string): Promise<UsageBreakdown> => {
            const requestId = ++requestIdRef.current;
            const isStale = (): boolean =>
                requestIdRef.current !== requestId;

            setIsLoading(true);
            setError(null);

            try {
                const breakdown: UsageBreakdown = {
                    total: 0,
                    perKind: { template: 0, 'template-part': 0 },
                };

                for (const kind of TARGET_KINDS) {
                    // H7 (#432). H6's list endpoints return a flat
                    // array — no pagination wrapper, so a single fetch
                    // sees every record. If H6 grows pagination later
                    // this loop reverts to walking pages.
                    const list = await listEntities(apiConfig, kind, {
                        perPage: 100,
                    });

                    for (const summary of list) {
                        let blocks = blocksFromRecord(summary);

                        if (blocks.length === 0) {
                            // Some H6 list responses omit content to
                            // keep the payload small. Fall back to a
                            // per-row fetch when we don't see any
                            // blocks — the caller budget is small (a
                            // few extra HTTPs at delete time) and
                            // skipping would silently under-count the
                            // usage figure.
                            try {
                                const detail = await fetchEntity(
                                    apiConfig,
                                    kind,
                                    summary.id
                                );

                                blocks = blocksFromRecord(detail);
                            } catch {
                                continue;
                            }
                        }

                        breakdown.perKind[kind] += countReferences(
                            blocks,
                            patternId
                        );
                    }
                }

                breakdown.total =
                    breakdown.perKind.template +
                    breakdown.perKind['template-part'];

                if (isStale()) {
                    return breakdown;
                }

                setUsage(breakdown);
                setIsLoading(false);

                return breakdown;
            } catch (caught: unknown) {
                if (isStale()) {
                    return EMPTY_USAGE;
                }

                setIsLoading(false);
                setUsage(EMPTY_USAGE);

                const message =
                    caught instanceof Error
                        ? caught.message
                        : 'Failed to count pattern usage.';

                setError(message);

                return EMPTY_USAGE;
            }
        },
        [apiConfig]
    );

    return { usage, isLoading, error, run, reset };
}
