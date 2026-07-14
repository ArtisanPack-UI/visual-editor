/**
 * #622 — Bindings resolution inside the composed view.
 *
 * The composed view relies on the existing bindings pipeline: the editor
 * app sets `bindingsResourceContext(resource, id)` on mount, which every
 * bound block (whether inside the raw content list or inside template
 * chrome) reads via the sentinel host. The compose function only needs
 * to preserve each block's `bindings` attribute through composition so
 * the chrome blocks pick up the current-content overlay.
 *
 * These tests pin that guarantee.
 */

import { describe, expect, it } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

import type { AppliedTemplate } from '../api';
import { composeBlocks } from '../compose';

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

function template(blocks: BlockInstance[]): AppliedTemplate {
    return {
        slug: 't',
        name: 'T',
        source: 'theme',
        blocks,
        template_parts: {},
    };
}

describe('composed-view bindings (#622)', () => {
    it('preserves a bindings map on core/post-title in the template chrome', () => {
        const title = block('core/post-title', {
            content: 'Sample',
            bindings: {
                content: {
                    source: 'cms/entity-field',
                    args: { field: 'title' },
                },
            },
        });
        const applied = template([title, block('core/post-content')]);

        const composed = composeBlocks([], applied);

        const composedTitle = composed[0];

        expect(composedTitle.name).toBe('core/post-title');
        expect(
            (composedTitle.attributes as Record<string, unknown>).bindings
        ).toEqual({
            content: {
                source: 'cms/entity-field',
                args: { field: 'title' },
            },
        });
    });

    it('preserves a bindings map on core/post-author in the template chrome', () => {
        const author = block('core/post-author', {
            byline: 'x',
            bindings: {
                byline: {
                    source: 'cms/entity-field',
                    args: { field: 'author' },
                },
            },
        });
        const applied = template([author, block('core/post-content')]);

        const composed = composeBlocks([], applied);

        const composedAuthor = composed[0];

        expect(composedAuthor.name).toBe('core/post-author');
        expect(
            (composedAuthor.attributes as Record<string, unknown>).bindings
        ).toBeDefined();
    });

    it('preserves bindings when chrome blocks are locked', () => {
        // Locking must not overwrite `bindings` — it only adds `lock`
        // and the internal chrome marker. Use `core/post-author` so
        // the block is chrome, not a content-slot candidate.
        const bound = block('core/post-author', {
            byline: '',
            bindings: {
                byline: { source: 'cms/entity-field', args: { field: 'author' } },
            },
        });
        const applied = template([bound, block('core/post-content')]);

        const composed = composeBlocks([], applied);
        const attrs = composed[0].attributes as Record<string, unknown>;

        expect(attrs.bindings).toBeDefined();
        expect(attrs.lock).toEqual({ move: true, remove: true, edit: true });
    });

    it('preserves bindings on nested chrome blocks inside a template part', () => {
        const dateBlock = block('core/post-date', {
            format: 'F j, Y',
            bindings: {
                content: { source: 'cms/entity-field', args: { field: 'date' } },
            },
        });
        const featured = block('core/post-featured-image', {
            bindings: {
                src: { source: 'cms/entity-field', args: { field: 'featured' } },
            },
        });
        const applied: AppliedTemplate = {
            slug: 't',
            name: 'T',
            source: 'theme',
            blocks: [
                block('core/template-part', { slug: 'header' }),
                block('core/post-content'),
            ],
            template_parts: {
                header: {
                    slug: 'header',
                    area: 'header',
                    title: 'Header',
                    source: 'theme',
                    blocks: [dateBlock, featured],
                },
            },
        };

        const composed = composeBlocks([], applied);

        expect(composed[0].name).toBe('core/post-date');
        expect(
            (composed[0].attributes as Record<string, unknown>).bindings
        ).toBeDefined();
        expect(composed[1].name).toBe('core/post-featured-image');
        expect(
            (composed[1].attributes as Record<string, unknown>).bindings
        ).toBeDefined();
    });
});
