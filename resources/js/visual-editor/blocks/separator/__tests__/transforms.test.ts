/**
 * Transforms tests for `artisanpack/separator`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    getDefaultBlockName: () => 'core/paragraph',
}));

import transforms from '../transforms';

describe('artisanpack/separator transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('preserves the upstream --- input shortcut', () => {
        const input = transforms.from.find((t) => t.type === 'input') as {
            type: string;
            regExp: RegExp;
            transform: () => Array<{ name: string }>;
        };
        expect(input).toBeDefined();
        expect(input.regExp.test('---')).toBe(true);
        expect(input.regExp.test('-----')).toBe(true);
        expect(input.regExp.test('--')).toBe(false);
        const blocks = input.transform();
        expect(blocks).toHaveLength(2);
        expect(blocks[0].name).toBe('artisanpack/separator');
    });

    it('preserves the upstream raw <hr> transform', () => {
        const raw = transforms.from.find((t) => t.type === 'raw');
        expect(raw).toBeDefined();
    });

    it('converts core/separator → artisanpack/separator (from)', () => {
        const blockFrom = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/separator')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({ opacity: 'css' });
        expect(block.name).toBe('artisanpack/separator');
    });

    it('converts artisanpack/separator → core/separator (to)', () => {
        const blockTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/separator')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({ opacity: 'css' });
        expect(block.name).toBe('core/separator');
    });

    it('keeps the upstream → core/spacer transform', () => {
        const spacerTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/spacer')
        ) as {
            transform: (attrs: Record<string, unknown>) => { name: string };
        };
        expect(spacerTo).toBeDefined();
        const block = spacerTo.transform({ anchor: 'rule' });
        expect(block.name).toBe('core/spacer');
    });
});
