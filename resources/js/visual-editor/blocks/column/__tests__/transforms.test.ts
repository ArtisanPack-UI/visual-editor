/**
 * Transforms tests for `artisanpack/column`.
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

import transforms from '../transforms';

describe('artisanpack/column transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('converts core/column → artisanpack/column (from)', () => {
        const blockFrom = transforms.from.find(
            (t: any) =>
                t.type === 'block' &&
                Array.isArray(t.blocks) &&
                t.blocks.includes('core/column')
        ) as { transform: (a: any, i: any) => { name: string } };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({ width: '50%' }, []);
        expect(block.name).toBe('artisanpack/column');
    });

    it('converts artisanpack/column → core/column (to)', () => {
        const blockTo = transforms.to.find(
            (t: any) =>
                t.type === 'block' &&
                Array.isArray(t.blocks) &&
                t.blocks.includes('core/column')
        ) as { transform: (a: any, i: any) => { name: string } };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({ width: '50%' }, []);
        expect(block.name).toBe('core/column');
    });
});
