/**
 * Transforms tests for `artisanpack/details`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: Array<Record<string, unknown>>
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
    cloneBlock: (block: Record<string, unknown>) => ({ ...block }),
}));

import transforms from '../transforms';

describe('artisanpack/details transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('preserves the upstream multi-block wildcard converter', () => {
        const wildcard = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('*')
        ) as {
            isMatch: (
                attrs: Record<string, unknown>,
                blocks: Array<{ name: string }>
            ) => boolean;
            __experimentalConvert: (
                blocks: Array<{ name: string }>
            ) => { name: string; innerBlocks: unknown[] };
        };
        expect(wildcard).toBeDefined();
        // Single existing details block should NOT match.
        expect(
            wildcard.isMatch({}, [{ name: 'artisanpack/details' }])
        ).toBe(false);
        expect(wildcard.isMatch({}, [{ name: 'core/details' }])).toBe(false);
        // Mixed / multi-block content SHOULD match.
        expect(wildcard.isMatch({}, [{ name: 'core/paragraph' }])).toBe(true);
        const wrapped = wildcard.__experimentalConvert([
            { name: 'core/paragraph' },
        ]);
        expect(wrapped.name).toBe('artisanpack/details');
        expect(wrapped.innerBlocks).toHaveLength(1);
    });

    it('converts core/details → artisanpack/details (from)', () => {
        const blockFrom = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/details') &&
                !(t as { blocks: string[] }).blocks.includes('*')
        ) as {
            transform: (
                attrs: Record<string, unknown>,
                innerBlocks: Array<{ name: string }>
            ) => { name: string; innerBlocks: unknown[] };
        };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({ showContent: true }, [
            { name: 'core/paragraph' },
        ]);
        expect(block.name).toBe('artisanpack/details');
        expect(block.innerBlocks).toHaveLength(1);
    });

    it('converts artisanpack/details → core/details (to)', () => {
        const blockTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/details')
        ) as {
            transform: (
                attrs: Record<string, unknown>,
                innerBlocks: Array<{ name: string }>
            ) => { name: string; innerBlocks: unknown[] };
        };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({ showContent: false }, [
            { name: 'core/paragraph' },
            { name: 'core/image' },
        ]);
        expect(block.name).toBe('core/details');
        expect(block.innerBlocks).toHaveLength(2);
    });
});
