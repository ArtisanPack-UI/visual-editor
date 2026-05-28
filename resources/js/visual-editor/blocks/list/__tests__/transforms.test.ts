/**
 * Transforms tests for `artisanpack/list`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
}));

vi.mock('@wordpress/rich-text', () => ({
    create: (input: { html: string }) => ({ text: input.html }),
    split: (value: { text: string }) => [value],
    toHTMLString: ({ value }: { value: { text: string } }) => value.text,
}));

import transforms from '../transforms';

describe('list transforms', () => {
    it('paragraph → list creates a list with list-item children', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as {
            transform: (attrs: Array<Record<string, unknown>>) => {
                name: string;
                innerBlocks: Array<{ name: string }>;
            };
        };
        const result = fromBlock.transform([{ content: 'hi' }]);
        expect(result.name).toBe('artisanpack/list');
        expect(result.innerBlocks[0].name).toBe('artisanpack/list-item');
    });

    it('block transform from core/list → artisanpack/list preserves innerBlocks', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/list'
        ) as {
            transform: (
                a: Record<string, unknown>,
                inner: unknown[]
            ) => { name: string; innerBlocks: unknown[] };
        };
        const inner = [{ name: 'core/list-item', attributes: { content: 'x' }, innerBlocks: [] }];
        const result = fromBlock.transform({ ordered: true }, inner);
        expect(result.name).toBe('artisanpack/list');
        expect(result.innerBlocks).toEqual(inner);
    });

    it('block transform to core/list', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/list'
        ) as { transform: (a: Record<string, unknown>, inner: unknown[]) => { name: string } };
        expect(toBlock.transform({}, []).name).toBe('core/list');
    });

    it('prefix transform on `*` creates an unordered list', () => {
        const prefix = transforms.from.find(
            (t: { type: string; prefix?: string }) =>
                t.type === 'prefix' && t.prefix === '*'
        ) as {
            transform: (content: string) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = prefix.transform('item');
        expect(result.name).toBe('artisanpack/list');
        expect(result.attributes.ordered).toBeUndefined();
    });

    it('prefix transform on `1.` creates an ordered list', () => {
        const prefix = transforms.from.find(
            (t: { type: string; prefix?: string }) =>
                t.type === 'prefix' && t.prefix === '1.'
        ) as {
            transform: (content: string) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = prefix.transform('item');
        expect(result.attributes.ordered).toBe(true);
    });
});
