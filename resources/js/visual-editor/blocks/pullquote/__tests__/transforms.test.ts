/**
 * Transforms tests for `artisanpack/pullquote`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

vi.mock('@wordpress/rich-text', () => ({
    create: (input: { html: string }) => ({ text: input.html }),
    join: (values: Array<{ text: string }>, sep: string) => ({
        text: values.map((v) => v.text).join(sep),
    }),
    toHTMLString: ({ value }: { value: { text: string } }) => value.text,
}));

import transforms from '../transforms';

describe('pullquote transforms', () => {
    it('heading → pullquote preserves content + anchor', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/heading')
        ) as {
            transform: (a: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = fromBlock.transform({ content: 'Hi', anchor: 'a' });
        expect(result.name).toBe('artisanpack/pullquote');
        expect(result.attributes).toEqual({ value: 'Hi', anchor: 'a' });
    });

    it('block transform from core/pullquote → artisanpack/pullquote', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/pullquote'
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(fromBlock.transform({}).name).toBe('artisanpack/pullquote');
    });

    it('block transform to core/pullquote', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/pullquote'
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(toBlock.transform({}).name).toBe('core/pullquote');
    });

    it('pullquote → paragraph emits two paragraphs when both value and citation are present', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/paragraph'
        ) as {
            transform: (a: Record<string, unknown>) =>
                | Array<{ name: string; attributes: Record<string, unknown> }>
                | { name: string };
        };
        const result = toBlock.transform({ value: 'Hi', citation: 'Author' });
        expect(Array.isArray(result)).toBe(true);
        expect((result as unknown[]).length).toBe(2);
    });
});
