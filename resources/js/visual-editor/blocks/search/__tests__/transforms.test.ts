/**
 * Transforms tests for `artisanpack/search`.
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

describe('artisanpack/search transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('converts core/search → artisanpack/search (from), preserving attributes', () => {
        const t = transforms.from.find(
            (entry) => (entry as BlockTransform).blocks?.includes('core/search')
        ) as BlockTransform;
        expect(t).toBeDefined();
        const block = t.transform({ buttonUseIcon: true, label: 'Find' });
        expect(block.name).toBe('artisanpack/search');
        expect(block.attributes).toEqual({ buttonUseIcon: true, label: 'Find' });
    });

    it('converts artisanpack/search → core/search (to), preserving attributes', () => {
        const t = transforms.to.find(
            (entry) => (entry as BlockTransform).blocks?.includes('core/search')
        ) as BlockTransform;
        expect(t).toBeDefined();
        const block = t.transform({ buttonText: 'Go' });
        expect(block.name).toBe('core/search');
        expect(block.attributes).toEqual({ buttonText: 'Go' });
    });
});
