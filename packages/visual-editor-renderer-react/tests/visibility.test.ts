/**
 * Client-side visibility helper tests (#491 · #492 · #493).
 */

import { describe, expect, it } from 'vitest';
import { filterVisibleBlocks, setBreakpoints, stampVisibilityScopes } from '../src/visibility';
import type { Block } from '../src/types';

describe('filterVisibleBlocks', () => {
    it('preserves visible blocks', () => {
        const tree: Block[] = [
            { name: 'artisanpack/paragraph', attributes: {}, innerBlocks: [] },
            { name: 'artisanpack/heading',   attributes: {}, innerBlocks: [] },
        ] as unknown as Block[];

        expect(filterVisibleBlocks(tree)).toHaveLength(2);
    });

    it('drops blocks whose _veHidden flag is true', () => {
        const tree: Block[] = [
            { name: 'artisanpack/paragraph', attributes: {}, innerBlocks: [] },
            { name: 'artisanpack/paragraph', attributes: { _veHidden: true }, innerBlocks: [] },
        ] as unknown as Block[];

        const out = filterVisibleBlocks(tree);
        expect(out).toHaveLength(1);
        expect(out[0].name).toBe('artisanpack/paragraph');
    });

    it('recursively filters inner blocks', () => {
        const tree: Block[] = [
            {
                name: 'artisanpack/group',
                attributes: {},
                innerBlocks: [
                    { name: 'artisanpack/paragraph', attributes: {}, innerBlocks: [] },
                    { name: 'artisanpack/paragraph', attributes: { _veHidden: true }, innerBlocks: [] },
                ],
            },
        ] as unknown as Block[];

        const out = filterVisibleBlocks(tree);
        expect(out).toHaveLength(1);
        expect(out[0].innerBlocks).toHaveLength(1);
    });
});

describe('stampVisibilityScopes', () => {
    it('emits no CSS for a tree without _veHiddenBreakpoints', () => {
        const tree: Block[] = [
            { name: 'artisanpack/paragraph', attributes: {}, innerBlocks: [] },
        ] as unknown as Block[];

        const result = stampVisibilityScopes(tree);
        expect(result.css).toBe('');
        expect(result.tree).toHaveLength(1);
    });

    it('stamps a scope class + emits @media rules for hidden breakpoints', () => {
        setBreakpoints([
            { key: 'sm', minWidthPx: 640 },
            { key: 'md', minWidthPx: 768 },
        ]);

        const tree: Block[] = [
            {
                name: 'artisanpack/paragraph',
                attributes: { _veHiddenBreakpoints: ['sm', 'md'] },
                innerBlocks: [],
            },
        ] as unknown as Block[];

        const result = stampVisibilityScopes(tree);

        expect(result.css).toContain('@media (min-width:640px)');
        expect(result.css).toContain('@media (min-width:768px)');
        expect(result.css).toContain('display:none !important');

        const scope = (result.tree[0].attributes as Record<string, unknown>)._veVisScope;
        expect(typeof scope).toBe('string');
        expect((scope as string).startsWith('ve-vis-')).toBe(true);
    });

    it('mints unique scope classes per block', () => {
        setBreakpoints([{ key: 'sm', minWidthPx: 640 }]);

        const tree: Block[] = [
            { name: 'a', attributes: { _veHiddenBreakpoints: ['sm'] }, innerBlocks: [] },
            { name: 'b', attributes: { _veHiddenBreakpoints: ['sm'] }, innerBlocks: [] },
        ] as unknown as Block[];

        const result = stampVisibilityScopes(tree);
        const scopeA = (result.tree[0].attributes as Record<string, unknown>)._veVisScope;
        const scopeB = (result.tree[1].attributes as Record<string, unknown>)._veVisScope;

        expect(scopeA).not.toBe(scopeB);
    });
});
