/**
 * Transforms tests for `artisanpack/columns`.
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
    createBlocksFromInnerBlocksTemplate: (tpl: unknown[]) => tpl,
}));

import transforms from '../transforms';

describe('artisanpack/columns transforms', () => {
    it('declares both directions and ungroup', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
        expect(typeof transforms.ungroup).toBe('function');
    });

    it('converts core/columns → artisanpack/columns (from)', () => {
        const blockFrom = transforms.from.find(
            (t: any) =>
                t.type === 'block' &&
                Array.isArray(t.blocks) &&
                t.blocks.includes('core/columns')
        ) as { transform: (a: any, i: any) => { name: string } };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform(
            { verticalAlignment: 'top' },
            []
        );
        expect(block.name).toBe('artisanpack/columns');
    });

    it('converts artisanpack/columns → core/columns (to)', () => {
        const blockTo = transforms.to.find(
            (t: any) =>
                t.type === 'block' &&
                Array.isArray(t.blocks) &&
                t.blocks.includes('core/columns')
        ) as { transform: (a: any, i: any) => { name: string } };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({}, []);
        expect(block.name).toBe('core/columns');
    });

    it('multi-block convert wraps each block in an artisanpack/column inner block', () => {
        const multi = transforms.from.find(
            (t: any) => t.isMultiBlock
        ) as { __experimentalConvert: (b: any[]) => any };
        const result = multi.__experimentalConvert([
            { name: 'core/paragraph', attributes: {}, innerBlocks: [] },
            { name: 'core/paragraph', attributes: {}, innerBlocks: [] },
        ]);
        expect(result.name).toBe('artisanpack/columns');
        // innerBlocks is the template returned by createBlocksFromInnerBlocksTemplate mock
        expect(result.innerBlocks[0][0]).toBe('artisanpack/column');
    });

    it('media-text transform produces artisanpack/column inner blocks', () => {
        const mediaText = transforms.from.find(
            (t: any) =>
                Array.isArray(t.blocks) && t.blocks.includes('core/media-text')
        ) as { transform: (a: any, i: any) => any };
        const result = mediaText.transform(
            { mediaWidth: 40, mediaType: 'image' },
            []
        );
        expect(result.name).toBe('artisanpack/columns');
        expect(result.innerBlocks[0][0]).toBe('artisanpack/column');
        expect(result.innerBlocks[1][0]).toBe('artisanpack/column');
    });

    it('ungroup flattens nested inner blocks', () => {
        const result = transforms.ungroup({}, [
            { innerBlocks: [{ name: 'a' }, { name: 'b' }] },
            { innerBlocks: [{ name: 'c' }] },
        ]);
        expect(result.map((b: any) => b.name)).toEqual(['a', 'b', 'c']);
    });
});
