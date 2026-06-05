/**
 * Transforms tests for `artisanpack/quote`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes,
        innerBlocks: innerBlocks ?? [],
    }),
}));

import transforms from '../transforms';

describe('quote transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('block transform from core/quote → artisanpack/quote', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[]; isMultiBlock?: boolean }) =>
                t.type === 'block' &&
                !t.isMultiBlock &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/quote'
        ) as {
            transform: (
                attrs: Record<string, unknown>,
                innerBlocks: unknown[]
            ) => { name: string; attributes: Record<string, unknown>; innerBlocks: unknown[] };
        };
        const inner = [{ name: 'core/paragraph', attributes: { content: 'hi' }, innerBlocks: [] }];
        const result = fromBlock.transform({ citation: 'Author' }, inner);
        expect(result.name).toBe('artisanpack/quote');
        expect(result.attributes).toEqual({ citation: 'Author' });
        expect(result.innerBlocks).toEqual(inner);
    });

    it('block transform to core/quote', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/quote'
        ) as {
            transform: (
                attrs: Record<string, unknown>,
                innerBlocks: unknown[]
            ) => { name: string; attributes: Record<string, unknown>; innerBlocks: unknown[] };
        };
        const inner = [{ name: 'core/paragraph', attributes: { content: 'hi' }, innerBlocks: [] }];
        const result = toBlock.transform({ citation: 'Author' }, inner);
        expect(result.name).toBe('core/quote');
        expect(result.attributes).toEqual({ citation: 'Author' });
    });

    it('prefix transform on > creates a quote wrapping a paragraph', () => {
        const prefix = transforms.from.find(
            (t: { type: string; prefix?: string }) =>
                t.type === 'prefix' && t.prefix === '>'
        ) as {
            transform: (content: string) => {
                name: string;
                innerBlocks: unknown[];
            };
        };
        const result = prefix.transform('quoted');
        expect(result.name).toBe('artisanpack/quote');
        expect(result.innerBlocks).toHaveLength(1);
    });
});
