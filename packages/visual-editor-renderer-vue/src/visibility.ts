/**
 * Vue-side mirror of the React renderer's visibility helpers.
 *
 * See {@link ../../visual-editor-renderer-react/src/visibility.ts} for
 * the full contract — the two files must stay behavior-identical
 * because the render parity tests exercise both from the same
 * fixtures. Duplicating rather than re-exporting keeps the packages'
 * dependency graphs independent (parity plan #647).
 */

import type { Block } from './types';

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
