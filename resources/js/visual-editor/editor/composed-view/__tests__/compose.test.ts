import { describe, expect, it } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

import type { AppliedTemplate } from '../api';
import {
    COMPOSED_CHROME_MARKER,
    COMPOSED_CONTENT_SLOT_MARKER,
    composeBlocks,
} from '../compose';
import { extractContentBlocks } from '../extract';

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

function template(
    blocks: BlockInstance[],
    templateParts: AppliedTemplate['template_parts'] = {}
): AppliedTemplate {
    return {
        slug: 'single-post',
        name: 'Single Post',
        source: 'theme',
        blocks,
        template_parts: templateParts,
    };
}

describe('composeBlocks (#621)', () => {
    it('slots the content list into a core/post-content template block', () => {
        const content = [block('core/paragraph', { content: 'Hello' })];
        const applied = template([
            block('core/heading', { content: 'Header' }),
            block('core/post-content'),
            block('core/paragraph', { content: 'Footer' }),
        ]);

        const composed = composeBlocks(content, applied);

        expect(composed).toHaveLength(3);
        expect(composed[0].name).toBe('core/heading');
        expect(composed[1].name).toBe('core/post-content');
        expect(
            (composed[1].attributes as Record<string, unknown>)[
                COMPOSED_CONTENT_SLOT_MARKER
            ]
        ).toBe(true);
        expect(composed[1].innerBlocks).toHaveLength(1);
        expect(composed[1].innerBlocks[0]).toBe(content[0]);
    });

    it('locks every chrome block with the standard lock attributes', () => {
        const content = [block('core/paragraph')];
        const applied = template([
            block('core/heading'),
            block('core/post-content'),
        ]);

        const composed = composeBlocks(content, applied);

        const heading = composed[0];
        const headingAttrs = heading.attributes as Record<string, unknown>;

        expect(headingAttrs.lock).toEqual({
            move: true,
            remove: true,
            edit: true,
        });
        expect(headingAttrs[COMPOSED_CHROME_MARKER]).toBe(true);

        // Slot host is not locked — its inner blocks stay editable.
        expect(
            (composed[1].attributes as Record<string, unknown>).lock
        ).toBeUndefined();
    });

    it('leaves content blocks unlocked and untouched', () => {
        const paragraph = block('core/paragraph', { content: 'Editable' });
        const applied = template([block('core/post-content')]);

        const composed = composeBlocks([paragraph], applied);

        expect(composed[0].innerBlocks[0]).toBe(paragraph);
        expect(
            (composed[0].innerBlocks[0].attributes as Record<string, unknown>)
                .lock
        ).toBeUndefined();
    });

    it('expands core/template-part refs from the parts map inline', () => {
        const content = [block('core/paragraph')];
        const partHeader = {
            slug: 'header',
            area: 'header',
            title: 'Header',
            source: 'theme' as const,
            blocks: [block('core/site-title'), block('core/site-tagline')],
        };
        const applied = template(
            [
                block('core/template-part', { slug: 'header' }),
                block('core/post-content'),
            ],
            { header: partHeader }
        );

        const composed = composeBlocks(content, applied);

        // Two header blocks + content slot.
        expect(composed).toHaveLength(3);
        expect(composed[0].name).toBe('core/site-title');
        expect(composed[1].name).toBe('core/site-tagline');
        expect(composed[2].name).toBe('core/post-content');
    });

    it('keeps unresolved template-part refs in place (locked, empty)', () => {
        const content = [block('core/paragraph')];
        const applied = template([
            block('core/template-part', { slug: 'missing' }),
            block('core/post-content'),
        ]);

        const composed = composeBlocks(content, applied);

        expect(composed).toHaveLength(2);
        expect(composed[0].name).toBe('core/template-part');
        expect(
            (composed[0].attributes as Record<string, unknown>).lock
        ).toEqual({ move: true, remove: true, edit: true });
    });

    it('appends a content slot when the template has no core/post-content', () => {
        const content = [block('core/paragraph', { content: 'lonely' })];
        const applied = template([block('core/heading', { content: 'Just chrome' })]);

        const composed = composeBlocks(content, applied);

        expect(composed).toHaveLength(2);
        expect(
            (composed[0].attributes as Record<string, unknown>).lock
        ).toBeDefined();
        expect(
            (composed[1].attributes as Record<string, unknown>)[
                COMPOSED_CONTENT_SLOT_MARKER
            ]
        ).toBe(true);
        expect(composed[1].innerBlocks[0]).toBe(content[0]);
    });

    it('round-trips with extractContentBlocks', () => {
        const content = [
            block('core/paragraph', { content: 'a' }),
            block('core/paragraph', { content: 'b' }),
        ];
        const applied = template([
            block('core/heading'),
            block('core/post-content'),
        ]);

        const composed = composeBlocks(content, applied);
        const extracted = extractContentBlocks(composed);

        expect(extracted).toEqual(content);
    });

    it('returns null from extractContentBlocks when the tree has no slot', () => {
        const raw = [block('core/paragraph')];

        expect(extractContentBlocks(raw)).toBeNull();
    });
});
