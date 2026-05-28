/**
 * Transforms tests for `artisanpack/table`.
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
import { normalizeRowColSpan } from '../utils';

describe('normalizeRowColSpan', () => {
    it('returns undefined for the default value (1)', () => {
        expect(normalizeRowColSpan('1')).toBeUndefined();
        expect(normalizeRowColSpan(1)).toBeUndefined();
    });

    it('returns undefined for non-numeric input', () => {
        expect(normalizeRowColSpan('abc')).toBeUndefined();
        expect(normalizeRowColSpan(null)).toBeUndefined();
        expect(normalizeRowColSpan(undefined)).toBeUndefined();
    });

    it('returns the string form of integers > 1', () => {
        expect(normalizeRowColSpan('3')).toBe('3');
        expect(normalizeRowColSpan(5)).toBe('5');
    });

    it('returns undefined for negative values', () => {
        expect(normalizeRowColSpan('-2')).toBeUndefined();
    });

    it('returns undefined for zero (an explicit colspan/rowspan of 0)', () => {
        expect(normalizeRowColSpan(0)).toBeUndefined();
        expect(normalizeRowColSpan('0')).toBeUndefined();
    });
});

describe('table transforms', () => {
    it('block transform from core/table → artisanpack/table', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/table'
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(fromBlock.transform({}).name).toBe('artisanpack/table');
    });

    it('block transform to core/table', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/table'
        ) as { transform: (a: Record<string, unknown>) => { name: string } };
        expect(toBlock.transform({}).name).toBe('core/table');
    });
});
