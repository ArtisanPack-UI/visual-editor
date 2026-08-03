import { describe, expect, it } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

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

const names = (blocks: readonly BlockInstance[]): string[] =>
    blocks.map((b) => b.name);

describe('splitTemplateAroundContentSlot', () => {
    it('splits chrome around a top-level content slot', () => {
        const result = splitTemplateAroundContentSlot(
            [
                block('artisanpack/template-part', { slug: 'header' }),
                block('artisanpack/post-content'),
                block('artisanpack/template-part', { slug: 'footer' }),
            ],
            'Single Post'
        );

        expect(names(result.header)).toEqual(['artisanpack/template-part']);
        expect(names(result.footer)).toEqual(['artisanpack/template-part']);
        expect(result.slotFound).toBe(true);
        expect(result.templateName).toBe('Single Post');
    });

    it('splits around a slot nested inside a layout wrapper', () => {
        // The conventional template shape: header part, a `main` group
        // holding the slot, footer part. A top-level-only scan would find
        // no slot here and dump the whole template into the header.
        const result = splitTemplateAroundContentSlot(
            [
                block('artisanpack/template-part', { slug: 'header' }),
                block('artisanpack/group', { tagName: 'main' }, [
                    block('artisanpack/heading'),
                    block('artisanpack/post-content'),
                    block('artisanpack/paragraph'),
                ]),
                block('artisanpack/template-part', { slug: 'footer' }),
            ],
            'T'
        );

        expect(result.slotFound).toBe(true);

        // The wrapper is cloned onto both sides, keeping the children that
        // fell either side of the slot.
        expect(names(result.header)).toEqual([
            'artisanpack/template-part',
            'artisanpack/group',
        ]);
        expect(names(result.header[1].innerBlocks)).toEqual([
            'artisanpack/heading',
        ]);

        expect(names(result.footer)).toEqual([
            'artisanpack/group',
            'artisanpack/template-part',
        ]);
        expect(names(result.footer[0].innerBlocks)).toEqual([
            'artisanpack/paragraph',
        ]);
    });

    it('preserves wrapper attributes on both cloned sides', () => {
        const result = splitTemplateAroundContentSlot(
            [
                block('artisanpack/group', { tagName: 'main', className: 'x' }, [
                    block('artisanpack/heading'),
                    block('artisanpack/post-content'),
                    block('artisanpack/paragraph'),
                ]),
            ],
            'T'
        );

        expect(result.header[0].attributes).toMatchObject({
            tagName: 'main',
            className: 'x',
        });
        expect(result.footer[0].attributes).toMatchObject({
            tagName: 'main',
            className: 'x',
        });
    });

    it('drops a wrapper side that would render empty', () => {
        // Slot is the wrapper's first child, so nothing precedes it.
        const result = splitTemplateAroundContentSlot(
            [
                block('artisanpack/group', { tagName: 'main' }, [
                    block('artisanpack/post-content'),
                    block('artisanpack/paragraph'),
                ]),
            ],
            'T'
        );

        expect(result.header).toHaveLength(0);
        expect(names(result.footer)).toEqual(['artisanpack/group']);
    });

    it('reports slotFound=false and keeps the whole template as header', () => {
        const blocks = [
            block('artisanpack/template-part', { slug: 'header' }),
            block('artisanpack/group'),
        ];

        const result = splitTemplateAroundContentSlot(blocks, 'No Slot');

        expect(result.slotFound).toBe(false);
        expect(result.header).toHaveLength(2);
        expect(result.footer).toHaveLength(0);
    });

    it('accepts the un-forked core slot name', () => {
        const result = splitTemplateAroundContentSlot(
            [block('core/site-title'), block('core/post-content')],
            'T'
        );

        expect(result.slotFound).toBe(true);
        expect(names(result.header)).toEqual(['core/site-title']);
    });

    it('splits at the first slot only', () => {
        const result = splitTemplateAroundContentSlot(
            [
                block('artisanpack/post-content'),
                block('artisanpack/paragraph'),
                block('artisanpack/post-content'),
            ],
            'T'
        );

        // The second slot is chrome as far as the split is concerned — it
        // stays in the footer rather than truncating it.
        expect(names(result.footer)).toEqual([
            'artisanpack/paragraph',
            'artisanpack/post-content',
        ]);
    });

    it('leaves template-part refs unexpanded for the block to resolve', () => {
        const result = splitTemplateAroundContentSlot(
            [
                block('artisanpack/template-part', {
                    slug: 'header',
                    theme: 'x',
                }),
                block('artisanpack/post-content'),
            ],
            'T'
        );

        // Since #675 the fork resolves its own part; expanding here would
        // render every part twice.
        expect(names(result.header)).toEqual(['artisanpack/template-part']);
        expect(result.header[0].innerBlocks).toHaveLength(0);
    });
});
