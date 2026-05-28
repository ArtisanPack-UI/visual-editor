/**
 * Transforms tests for `artisanpack/verse`.
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

describe('verse transforms', () => {
    it('paragraph → verse', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(fromBlock.transform({ content: 'x' }).name).toBe(
            'artisanpack/verse'
        );
    });

    it('core/verse → artisanpack/verse', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/verse'
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(fromBlock.transform({}).name).toBe('artisanpack/verse');
    });

    it('artisanpack/verse → core/verse', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/verse'
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(toBlock.transform({}).name).toBe('core/verse');
    });
});
