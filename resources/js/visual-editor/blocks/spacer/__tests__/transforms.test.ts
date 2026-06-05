/**
 * Transforms tests for `artisanpack/spacer`.
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

describe('artisanpack/spacer transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('converts core/spacer → artisanpack/spacer (from)', () => {
        const blockFrom = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/spacer')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({ height: '120px' });
        expect(block.name).toBe('artisanpack/spacer');
    });

    it('converts artisanpack/spacer → core/spacer (to)', () => {
        const blockTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/spacer')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({ height: '120px' });
        expect(block.name).toBe('core/spacer');
    });

    it('keeps the upstream → core/separator transform', () => {
        const separatorTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/separator')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(separatorTo).toBeDefined();
        const block = separatorTo.transform({ anchor: 'gap' });
        expect(block.name).toBe('core/separator');
        expect(
            (block as unknown as { attributes: { anchor?: string } })
                .attributes.anchor
        ).toBe('gap');
    });
});
