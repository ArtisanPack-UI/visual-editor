/**
 * Transforms tests for `artisanpack/button`.
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

describe('artisanpack/button transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('converts core/button → artisanpack/button (from)', () => {
        const blockFrom = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/button')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({ text: 'Hi', url: '/x' });
        expect(block.name).toBe('artisanpack/button');
    });

    it('converts artisanpack/button → core/button (to)', () => {
        const blockTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/button')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({ text: 'Hi' });
        expect(block.name).toBe('core/button');
    });
});
