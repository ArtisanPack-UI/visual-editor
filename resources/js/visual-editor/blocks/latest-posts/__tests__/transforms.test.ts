/**
 * Transforms tests for `artisanpack/latest-posts`.
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

interface BlockTransform {
    type: string;
    blocks: string[];
    transform: (attrs: Record<string, unknown>) => { name: string; attributes: Record<string, unknown> };
}

describe('artisanpack/latest-posts transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('converts core/latest-posts → artisanpack/latest-posts (from), preserving attributes', () => {
        const t = transforms.from.find(
            (entry) => (entry as BlockTransform).blocks?.includes('core/latest-posts')
        ) as BlockTransform;
        expect(t).toBeDefined();
        const block = t.transform({ postsToShow: 8, order: 'asc' });
        expect(block.name).toBe('artisanpack/latest-posts');
        expect(block.attributes).toEqual({ postsToShow: 8, order: 'asc' });
    });

    it('converts artisanpack/latest-posts → core/latest-posts (to), preserving attributes', () => {
        const t = transforms.to.find(
            (entry) => (entry as BlockTransform).blocks?.includes('core/latest-posts')
        ) as BlockTransform;
        expect(t).toBeDefined();
        const block = t.transform({ displayAuthor: true });
        expect(block.name).toBe('core/latest-posts');
        expect(block.attributes).toEqual({ displayAuthor: true });
    });
});
