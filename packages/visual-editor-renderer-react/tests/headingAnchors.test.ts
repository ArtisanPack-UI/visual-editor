/**
 * Heading anchors (M-13, M-14, R-20) — pins the React and Vue twins of
 * `TocResolver` to the PHP behaviour: auto anchors are reduced to the id
 * they render before the uniqueness check, post-content headings claim
 * and receive ids in document order, and tags are stripped the way PHP's
 * `strip_tags()` strips them.
 */

import { describe, expect, it } from 'vitest';

import * as reactAnchors from '../src/support/headingAnchors';
import * as vueAnchors from '../../visual-editor-renderer-vue/src/support/headingAnchors';
import type { Block } from '../src/types';

function block(name: string, attributes: Record<string, unknown>, innerBlocks: Block[] = []): Block {
    return { name, attributes, innerBlocks } as unknown as Block;
}

function attr(node: Block, key: string): unknown {
    return (node.attributes as Record<string, unknown>)[key];
}

describe.each([
    ['react', reactAnchors],
    ['vue', vueAnchors],
])('%s heading anchors', (_name, impl) => {
    it('reduces auto anchors to the rendered id before the uniqueness check (M-13)', () => {
        const tree = impl.stampHeadingAnchors([
            block('core/heading', { content: 'Crème ½ x' }),
            block('core/heading', { content: 'Crème ¼ x' }),
            block('core/heading', { content: '日本語' }),
            block('core/heading', { content: 'Résumé 日本' }),
            block('artisanpack/heading', { content: 'Résumé 中文' }),
            block('core/heading', { content: 'Привет' }),
        ]);

        expect(tree.map((node) => attr(node, 'anchor'))).toEqual([
            'creme-x',
            'creme-x-1',
            'heading',
            'resume',
            'resume-1',
            'heading-1',
        ]);
    });

    it('still skips a heading whose text has no letters or numbers (M-13)', () => {
        expect(impl.headingSlug('— ? —')).toBe('');
        expect(attr(impl.stampHeadingAnchors([block('core/heading', { content: '???' })])[0], 'anchor')).toBeUndefined();
    });

    it('claims a numeric author anchor as the id it renders', () => {
        const tree = impl.stampHeadingAnchors([
            block('core/heading', { content: 'Numbered', anchor: 42 }),
            block('core/heading', { content: '42' }),
        ]);

        expect(tree.map((node) => attr(node, 'anchor'))).toEqual([42, '42-1']);
    });

    it('claims post-content heading ids before later headings (M-14)', () => {
        const tree = impl.stampHeadingAnchors([
            block('core/post-content', { _resolvedContent: '<h2 id="intro">Intro</h2>' }),
            block('core/heading', { content: 'Intro' }),
        ]);

        expect(attr(tree[0], '_resolvedContent')).toBe('<h2 id="intro">Intro</h2>');
        expect(attr(tree[1], 'anchor')).toBe('intro-1');
    });

    it('stamps ids into post-content headings like Blade (F-7)', () => {
        const tree = impl.stampHeadingAnchors([
            block('core/heading', { content: 'Intro' }),
            block('artisanpack/post-content', {
                _resolvedContent:
                    '<p>Lead</p><h2 class="wp-block-heading">Intro</h2><H3>日本語</H3><h4 data-x="1"> </h4>' +
                    '<h2>???</h2><h5 data-id="kept">Kept</h5>',
            }),
            block('core/heading', { content: 'Kept' }),
        ]);

        expect(attr(tree[1], '_resolvedContent')).toBe(
            '<p>Lead</p><h2 id="intro-1" class="wp-block-heading">Intro</h2><h3 id="heading">日本語</h3>' +
                '<h4 data-x="1"> </h4><h2>???</h2><h5 data-id="kept">Kept</h5>'
        );
        expect(attr(tree[2], 'anchor')).toBe('kept-1');
    });

    it('leaves post-content without headings untouched', () => {
        const original = block('core/post-content', { _resolvedContent: '<p>Body</p>' });

        expect(attr(impl.stampHeadingAnchors([original])[0], '_resolvedContent')).toBe('<p>Body</p>');
    });

    it('strips tags like PHP strip_tags (R-20)', () => {
        expect(impl.headingSlug('a < b > c')).toBe('a-b-c');
        expect(impl.headingSlug('x <b title="1>2">y</b> z')).toBe('x-y-z');
        expect(impl.headingSlug('1 <2 and 3> 4')).toBe('1-4');
        expect(impl.headingSlug('a <!-- c --> b')).toBe('a-b');
        expect(impl.headingSlug('a<br>b')).toBe('ab');
        expect(impl.headingSlug('a <b')).toBe('a');
        expect(impl.stripTags('<<b>>c a <b <c> d> e')).toBe('c a  e');
        expect(impl.stripTags('a <!-- open')).toBe('a ');
    });
});
