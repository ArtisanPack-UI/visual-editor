/**
 * Transforms tests for `artisanpack/list-item`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({ name, attributes: attributes ?? {}, innerBlocks: innerBlocks ?? [] }),
    cloneBlock: (block: { name: string; attributes: Record<string, unknown> }) => ({
        ...block,
    }),
}));

import transforms from '../transforms';

describe('list-item transforms', () => {
    it('block transform from core/list-item → artisanpack/list-item', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/list-item'
        ) as {
            transform: (
                a: Record<string, unknown>,
                inner: unknown[]
            ) => { name: string };
        };
        expect(fromBlock.transform({ content: 'x' }, []).name).toBe(
            'artisanpack/list-item'
        );
    });

    it('list-item → paragraph (with innerBlocks appended)', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/paragraph'
        ) as {
            transform: (
                a: Record<string, unknown>,
                inner: unknown[]
            ) => Array<{ name: string }>;
        };
        const result = toBlock.transform({ content: 'x' }, []);
        expect(Array.isArray(result)).toBe(true);
        expect(result[0].name).toBe('core/paragraph');
    });

    it('block transform to core/list-item', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/list-item'
        ) as {
            transform: (
                a: Record<string, unknown>,
                inner: unknown[]
            ) => { name: string };
        };
        expect(toBlock.transform({}, []).name).toBe('core/list-item');
    });
});
