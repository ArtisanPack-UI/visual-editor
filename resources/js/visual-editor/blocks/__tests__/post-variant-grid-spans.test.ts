/**
 * Issue #592 — variable grid spans on `artisanpack/post-variant`.
 *
 * Covers the editor side: the new `gridColumnSpan` / `gridRowSpan`
 * attributes default to 1, both opt into the responsive cascade, and
 * the renderer-side span emitters in the Vue / React `support/attributes`
 * helpers translate a stamped `_resolvedGridSpan` into the same class
 * list the Blade post-template-item partial produces.
 */

import { describe, expect, it } from 'vitest';

import postVariantMeta from '../post-variant/block.json';
import { postTemplateItemSpanClasses as reactSpanClasses } from '../../../../../packages/visual-editor-renderer-react/src/support/attributes';
import { postTemplateItemSpanClasses as vueSpanClasses } from '../../../../../packages/visual-editor-renderer-vue/src/support/attributes';

interface AttributeDefinition {
    type?: string;
    default?: unknown;
}

interface PostVariantSupports {
    artisanpackResponsive?: {
        attributes?: readonly string[];
    };
}

describe('artisanpack/post-variant block.json — grid spans (#592)', () => {
    const attributes = postVariantMeta.attributes as Record<string, AttributeDefinition>;
    const supports = postVariantMeta.supports as PostVariantSupports;

    it('declares gridColumnSpan and gridRowSpan as numeric attributes with default 1', () => {
        expect(attributes.gridColumnSpan).toEqual({ type: 'number', default: 1 });
        expect(attributes.gridRowSpan).toEqual({ type: 'number', default: 1 });
    });

    it('opts both span attributes into the artisanpackResponsive cascade', () => {
        const responsive = supports.artisanpackResponsive?.attributes ?? [];
        expect(responsive).toContain('gridColumnSpan');
        expect(responsive).toContain('gridRowSpan');
    });
});

describe('postTemplateItemSpanClasses — renderer parity for `_resolvedGridSpan`', () => {
    const expectIdenticalOutput = (input: unknown): string[] => {
        const fromVue = vueSpanClasses(input);
        const fromReact = reactSpanClasses(input);
        expect(fromReact).toEqual(fromVue);
        return fromVue;
    };

    it('returns no classes when the attribute is absent or empty', () => {
        expect(expectIdenticalOutput(undefined)).toEqual([]);
        expect(expectIdenticalOutput(null)).toEqual([]);
        expect(expectIdenticalOutput({})).toEqual([]);
    });

    it('emits base column and row classes from the base values', () => {
        const result = expectIdenticalOutput({
            columns: { base: 2 },
            rows: { base: 3 },
        });

        expect(result).toContain('ap-post-span-2-base-columns');
        expect(result).toContain('ap-post-span-3-base-row');
    });

    it('emits one class per defined breakpoint for both axes', () => {
        const result = expectIdenticalOutput({
            columns: { base: 2, md: 3, lg: 4 },
            rows: { base: 1, md: 2 },
        });

        expect(result).toEqual(expect.arrayContaining([
            'ap-post-span-2-base-columns',
            'ap-post-span-3-md-columns',
            'ap-post-span-4-lg-columns',
            'ap-post-span-1-base-row',
            'ap-post-span-2-md-row',
        ]));
    });

    it('drops unknown breakpoint keys instead of emitting orphan classes', () => {
        const result = expectIdenticalOutput({
            columns: { base: 2, bogus: 9 },
            rows: {},
        });

        expect(result).toEqual(['ap-post-span-2-base-columns']);
    });

    it('clamps span values into the 1..12 stylesheet range', () => {
        const result = expectIdenticalOutput({
            columns: { base: 0, md: 99 },
            rows: { base: -5 },
        });

        expect(result).toEqual(expect.arrayContaining([
            'ap-post-span-1-base-columns',
            'ap-post-span-12-md-columns',
            'ap-post-span-1-base-row',
        ]));
    });

    it('parses numeric strings the same way both renderers do', () => {
        const result = expectIdenticalOutput({
            columns: { base: '2' },
            rows: { base: '3' },
        });

        expect(result).toContain('ap-post-span-2-base-columns');
        expect(result).toContain('ap-post-span-3-base-row');
    });

    it('skips overrides whose values are not finite numbers or numeric strings', () => {
        const result = expectIdenticalOutput({
            columns: { base: 2, md: null, lg: 'not a number' },
            rows: { base: 1, md: undefined },
        });

        expect(result).toEqual(expect.arrayContaining([
            'ap-post-span-2-base-columns',
            'ap-post-span-1-base-row',
        ]));
        expect(result).not.toContain('ap-post-span-null-md-columns');
    });
});
