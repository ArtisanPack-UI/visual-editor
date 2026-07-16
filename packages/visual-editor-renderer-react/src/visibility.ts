/**
 * Client-side helpers for the Block Visibility feature (#491 · #492 · #493).
 *
 * Trees are expected to have already passed through the server-side
 * {@link https://gitlab.com/…/visual-editor/-/blob/main/packages/visual-editor/src/Visibility/TreePruner.php `VisibilityTreePruner`}
 * before being embedded in the page payload, so hidden blocks never
 * reach the client. This module handles the two cases that still need
 * client work:
 *
 *   1. **CSS-hidden blocks** — blocks whose screen-size rule flagged
 *      breakpoints server-side carry a `_veHiddenBreakpoints` side-
 *      channel attribute. We collect them and emit a single
 *      `<style data-ve-visibility>` block that hides them at the
 *      configured min-widths, without any runtime JavaScript.
 *   2. **Defensive filtering** — hosts that construct a tree client-
 *      side (e.g. dev-only preview harnesses) can call
 *      `filterVisibleBlocks()` to drop anything marked hidden.
 *
 * The client renderer never runs visibility rules itself — server is
 * the authority. This keeps the two evaluators from drifting and
 * avoids leaking rule logic (which could disclose the fact that a
 * hidden block exists) to unauthenticated visitors.
 */

import type { Block } from './types';

/**
 * Ordered breakpoint definitions used by the CSS emitter. Order and
 * pixel values must match the server's `BreakpointRegistry` — hosts
 * that override breakpoints server-side should call `setBreakpoints()`
 * at bootstrap to keep both sides in sync.
 */
export interface BreakpointDefinition {
    key: string;
    minWidthPx: number;
}

const DEFAULT_BREAKPOINTS: BreakpointDefinition[] = [
    { key: 'sm', minWidthPx: 640 },
    { key: 'md', minWidthPx: 768 },
    { key: 'lg', minWidthPx: 1024 },
    { key: 'xl', minWidthPx: 1280 },
    { key: '2xl', minWidthPx: 1536 },
];

let breakpoints: BreakpointDefinition[] = [...DEFAULT_BREAKPOINTS];

export function setBreakpoints(next: BreakpointDefinition[]): void {
    breakpoints = next.filter((bp) => typeof bp.key === 'string' && bp.key !== '' && Number.isFinite(bp.minWidthPx));
}

export function getBreakpoints(): BreakpointDefinition[] {
    return [...breakpoints];
}

/**
 * Filter a tree, removing every block whose attributes flag it as
 * hidden. Defensive filter for host-assembled trees; server-pruned
 * trees are already free of `hidden` markers.
 */
export function filterVisibleBlocks(tree: Block[]): Block[] {
    const out: Block[] = [];

    for (const block of tree) {
        if (block === null || typeof block !== 'object') {
            continue;
        }

        const attrs = attributesOf(block);
        if (attrs !== null && attrs._veHidden === true) {
            continue;
        }

        const innerBlocks = Array.isArray(block.innerBlocks) ? filterVisibleBlocks(block.innerBlocks as Block[]) : block.innerBlocks;

        out.push({
            ...block,
            innerBlocks,
        } as Block);
    }

    return out;
}

/**
 * Walk a tree and stamp a unique per-block `_veVisScope` class onto
 * every block whose `_veHiddenBreakpoints` list is non-empty. The
 * class is later joined with the block's rendered wrapper element by
 * the client renderer and matched by the emitted `@media` rules.
 */
export function stampVisibilityScopes(tree: Block[]): { tree: Block[]; css: string } {
    let counter = 0;
    const css: string[] = [];
    const bpByKey = new Map(breakpoints.map((bp) => [bp.key, bp.minWidthPx]));

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const attrs = attributesOf(block);
            const bpKeys = extractHiddenBreakpoints(attrs);

            const innerBlocks = Array.isArray(block.innerBlocks)
                ? walk(block.innerBlocks as Block[])
                : block.innerBlocks;

            if (bpKeys.length === 0) {
                return { ...block, innerBlocks } as Block;
            }

            counter += 1;
            const scopeClass = `ve-vis-${counter}`;
            const rules: string[] = [];

            for (const key of bpKeys) {
                const minPx = bpByKey.get(key);
                if (typeof minPx === 'number' && minPx > 0) {
                    rules.push(`@media (min-width:${minPx}px){.${scopeClass}{display:none !important;}}`);
                }
            }

            if (rules.length > 0) {
                css.push(rules.join(''));
            }

            return {
                ...block,
                attributes: {
                    ...(attrs ?? {}),
                    _veVisScope: scopeClass,
                },
                innerBlocks,
            } as Block;
        });
    }

    return { tree: walk(tree), css: css.join('') };
}

function attributesOf(block: Block): Record<string, unknown> | null {
    if (block === null || typeof block !== 'object') {
        return null;
    }

    const attrs = (block as { attributes?: unknown }).attributes;

    if (attrs === null || typeof attrs !== 'object' || Array.isArray(attrs)) {
        return null;
    }

    return attrs as Record<string, unknown>;
}

function extractHiddenBreakpoints(attrs: Record<string, unknown> | null): string[] {
    if (attrs === null) {
        return [];
    }

    const raw = attrs._veHiddenBreakpoints;

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.filter((k): k is string => typeof k === 'string' && k !== '');
}
