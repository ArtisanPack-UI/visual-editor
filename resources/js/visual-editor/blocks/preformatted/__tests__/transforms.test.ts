/**
 * Transforms tests for `artisanpack/preformatted`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

import transforms from '../transforms';

describe('preformatted transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('paragraph → preformatted preserves content + anchor', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as {
            transform: (a: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = fromBlock.transform({ content: 'x', anchor: 'a' });
        expect(result.name).toBe('artisanpack/preformatted');
        expect(result.attributes).toEqual({ content: 'x', anchor: 'a' });
    });

    it('block transform from core/preformatted → artisanpack/preformatted', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/preformatted'
        ) as {
            transform: (a: Record<string, unknown>) => { name: string };
        };
        expect(fromBlock.transform({}).name).toBe('artisanpack/preformatted');
    });

    it('block transform to core/preformatted', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/preformatted'
        ) as {
            transform: (a: Record<string, unknown>) => { name: string };
        };
        expect(toBlock.transform({}).name).toBe('core/preformatted');
    });
});
