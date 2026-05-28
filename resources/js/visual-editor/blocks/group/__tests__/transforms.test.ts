/**
 * Transforms tests for `artisanpack/group`.
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

describe('artisanpack/group transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('keeps the upstream multi-block __experimentalConvert', () => {
        const wildcard = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('*')
        ) as {
            __experimentalConvert: (blocks: unknown[]) => { name: string; attributes: { align?: string; layout?: { type: string } } };
        };
        expect(wildcard).toBeDefined();
        const result = wildcard.__experimentalConvert([
            { name: 'core/paragraph', attributes: { align: 'wide' }, innerBlocks: [] },
            { name: 'core/paragraph', attributes: { align: 'full' }, innerBlocks: [] },
        ]);
        expect(result.name).toBe('artisanpack/group');
        expect(result.attributes.align).toBe('full');
        expect(result.attributes.layout?.type).toBe('constrained');
    });

    it('converts core/group → artisanpack/group (from)', () => {
        const blockFrom = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/group')
        ) as {
            transform: (attrs: Record<string, unknown>, inner: unknown[]) => { name: string };
        };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({ tagName: 'section' }, []);
        expect(block.name).toBe('artisanpack/group');
    });

    it('converts artisanpack/group → core/group (to)', () => {
        const blockTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/group')
        ) as {
            transform: (attrs: Record<string, unknown>, inner: unknown[]) => { name: string };
        };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({ tagName: 'section' }, []);
        expect(block.name).toBe('core/group');
    });
});
