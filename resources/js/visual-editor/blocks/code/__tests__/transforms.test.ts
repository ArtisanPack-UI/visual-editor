/**
 * Transforms tests for `artisanpack/code`.
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
    create: (input: { text: string }) => ({ text: input.text }),
    toHTMLString: ({ value }: { value: { text: string } }) => value.text,
}));

import transforms from '../transforms';

describe('code transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('input transform on ``` creates an empty code block', () => {
        const input = transforms.from.find(
            (t: { type: string }) => t.type === 'input'
        ) as { transform: () => { name: string } };
        expect(input.transform().name).toBe('artisanpack/code');
    });

    it('paragraph → code preserves content', () => {
        const fromParagraph = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as {
            transform: (a: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = fromParagraph.transform({ content: 'hi' });
        expect(result.name).toBe('artisanpack/code');
        expect(result.attributes).toMatchObject({ content: 'hi' });
    });

    it('block transform from core/code → artisanpack/code', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/code'
        ) as {
            transform: (a: Record<string, unknown>) => { name: string };
        };
        expect(fromBlock.transform({ content: 'x' }).name).toBe('artisanpack/code');
    });

    it('block transform to core/code', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/code'
        ) as {
            transform: (a: Record<string, unknown>) => { name: string };
        };
        expect(toBlock.transform({ content: 'x' }).name).toBe('core/code');
    });
});
