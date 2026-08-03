import { describe, expect, it } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

import type { AppliedTemplatePart } from '../api';
import { splitTemplateAroundContentSlot } from '../split';

function block(
    name: string,
    attributes: Record<string, unknown> = {},
    innerBlocks: BlockInstance[] = []
): BlockInstance {
    return {
        clientId: `${name}-${Math.random().toString(36).slice(2, 8)}`,
        name,
        isValid: true,
        attributes,
        innerBlocks,
    } as unknown as BlockInstance;
}

function part(
    slug: string,
    blocks: BlockInstance[],
    area = 'uncategorized'
): AppliedTemplatePart {
    return { slug, area, title: slug, source: 'theme', blocks };
}

describe('splitTemplateAroundContentSlot', () => {
    it('splits chrome around the content slot', () => {
        const result = splitTemplateAroundContentSlot(
            [
                block('core/site-title'),
                block('core/post-content'),
                block('core/paragraph'),
            ],
            {},
            'Single Post'
        );

        expect(result.header.map((b) => b.name)).toEqual(['core/site-title']);
        expect(result.footer.map((b) => b.name)).toEqual(['core/paragraph']);
        expect(result.templateName).toBe('Single Post');
    });

    it('treats a slot-less template as all header chrome', () => {
        const result = splitTemplateAroundContentSlot(
            [block('core/site-title')],
            {},
            'No Slot'
        );

        expect(result.header).toHaveLength(1);
        expect(result.footer).toHaveLength(0);
    });

    it('expands template-part refs inline', () => {
        const result = splitTemplateAroundContentSlot(
            [block('core/template-part', { slug: 'header' })],
            { header: part('header', [block('core/site-title')]) },
            'T'
        );

        expect(result.header.map((b) => b.name)).toEqual(['core/site-title']);
    });

    it('terminates on a self-referencing template part', () => {
        expect(() =>
            splitTemplateAroundContentSlot(
                [block('core/template-part', { slug: 'header' })],
                {
                    header: part('header', [
                        block('core/template-part', { slug: 'header' }),
                    ]),
                },
                'T'
            )
        ).not.toThrow();
    });

    it('terminates on mutually-referencing template parts', () => {
        expect(() =>
            splitTemplateAroundContentSlot(
                [block('core/template-part', { slug: 'a' })],
                {
                    a: part('a', [block('core/template-part', { slug: 'b' })]),
                    b: part('b', [block('core/template-part', { slug: 'a' })]),
                },
                'T'
            )
        ).not.toThrow();
    });

    it('still expands a shared part referenced on both sides of the slot', () => {
        const result = splitTemplateAroundContentSlot(
            [
                block('core/template-part', { slug: 'shared' }),
                block('core/post-content'),
                block('core/template-part', { slug: 'shared' }),
            ],
            { shared: part('shared', [block('core/site-title')]) },
            'T'
        );

        // Cycle protection tracks the current branch only — sibling refs to
        // the same part must both still expand.
        expect(result.header.map((b) => b.name)).toEqual(['core/site-title']);
        expect(result.footer.map((b) => b.name)).toEqual(['core/site-title']);
    });
});
