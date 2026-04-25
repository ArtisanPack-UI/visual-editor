/**
 * React/Vue renderer parity test.
 *
 * Renders the same fixture block trees through the React and Vue renderers
 * using each framework's SSR entry point, then asserts the HTML output is
 * byte-for-byte identical after normalization. When this test fails, one of
 * the two renderers has drifted from the shared Blade partial contract —
 * that's a bug in the renderer that diverged, not in the test.
 */

import { createSSRApp, h as vueH } from 'vue';
import { renderToString as vueRenderToString } from '@vue/server-renderer';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree as VueBlockTree } from '../src/BlockTree';
import { BlockTree as ReactBlockTree } from '../../visual-editor-renderer-react/src/BlockTree';
import '../../visual-editor-renderer-react/src/index';
import type { Block } from '../src/types';
import { makeBlock, normalizeHtml } from './helpers';

async function renderVue(tree: Block[]): Promise<string> {
    const app = createSSRApp({
        render: () => vueH(VueBlockTree, { tree }),
    });

    const html = await vueRenderToString(app);

    return domNormalize(stripVueServerMarkers(html));
}

function renderReact(tree: Block[]): string {
    return domNormalize(renderToStaticMarkup(createElement(ReactBlockTree, { tree })));
}

function stripVueServerMarkers(html: string): string {
    return html.replace(/<!--\[-->|<!--\]-->|<!---->/g, '');
}

/**
 * Re-parses `html` through the DOM and serializes it back out, then
 * normalizes whitespace + collapses framework-level style serialization
 * differences. Vue's server renderer emits a trailing `;` after the last
 * style declaration (`style="height:48px;"`) while React omits it; both
 * serialize to identical CSS, so we strip the trailing `;` before
 * comparing.
 */
function domNormalize(html: string): string {
    const wrapper = document.createElement('div');

    wrapper.innerHTML = html;

    return normalizeHtml(wrapper.innerHTML).replace(
        /(style="[^"]*?);"/g,
        '$1"'
    );
}

const FIXTURES: Array<{ name: string; tree: Block[] }> = [
    {
        name: 'paragraph',
        tree: [makeBlock('core/paragraph', { content: 'Hello <strong>world</strong>' }, [], 'p1')],
    },
    {
        name: 'heading with alignment',
        tree: [
            makeBlock(
                'core/heading',
                { level: 3, textAlign: 'center', content: 'Section' },
                [],
                'h1'
            ),
        ],
    },
    {
        name: 'code + preformatted + verse',
        tree: [
            makeBlock('core/code', { content: 'echo 1;' }, [], 'c1'),
            makeBlock('core/preformatted', { content: 'line 1\nline 2' }, [], 'pre1'),
            makeBlock('core/verse', { content: 'Roses are red', textAlign: 'right' }, [], 'v1'),
        ],
    },
    {
        name: 'quote with citation',
        tree: [
            makeBlock(
                'core/quote',
                { citation: 'Someone' },
                [makeBlock('core/paragraph', { content: 'Quoted' }, [], 'p1')],
                'q1'
            ),
        ],
    },
    {
        name: 'pullquote',
        tree: [
            makeBlock(
                'core/pullquote',
                { value: 'Be bold', citation: 'Editor' },
                [],
                'pq1'
            ),
        ],
    },
    {
        name: 'unordered list',
        tree: [
            makeBlock(
                'core/list',
                { ordered: false },
                [
                    makeBlock('core/list-item', { content: 'One' }, [], 'li-1'),
                    makeBlock('core/list-item', { content: 'Two' }, [], 'li-2'),
                ],
                'list-1'
            ),
        ],
    },
    {
        name: 'ordered list with start + reversed',
        tree: [
            makeBlock(
                'core/list',
                { ordered: true, start: 5, reversed: true },
                [makeBlock('core/list-item', { content: 'A' }, [], 'li-1')],
                'list-2'
            ),
        ],
    },
    {
        name: 'image with caption + link',
        tree: [
            makeBlock(
                'core/image',
                {
                    url: 'https://example.test/image.jpg',
                    alt: 'An image',
                    caption: 'A <em>caption</em>',
                    href: 'https://example.test/full.jpg',
                    id: 42,
                    width: 800,
                    height: 600,
                },
                [],
                'img-1'
            ),
        ],
    },
    {
        name: 'gallery',
        tree: [
            makeBlock(
                'core/gallery',
                { columns: 3, imageCrop: true },
                [
                    makeBlock(
                        'core/image',
                        { url: 'https://example.test/a.jpg', alt: 'a' },
                        [],
                        'img-a'
                    ),
                    makeBlock(
                        'core/image',
                        { url: 'https://example.test/b.jpg', alt: 'b' },
                        [],
                        'img-b'
                    ),
                ],
                'gal-1'
            ),
        ],
    },
    {
        name: 'embed',
        tree: [
            makeBlock(
                'core/embed',
                {
                    url: 'https://youtu.be/abc123',
                    providerNameSlug: 'youtube',
                    aspectRatio: '16/9',
                    type: 'video',
                },
                [],
                'emb-1'
            ),
        ],
    },
    {
        name: 'file',
        tree: [
            makeBlock(
                'core/file',
                {
                    href: 'https://example.test/doc.pdf',
                    fileName: 'doc.pdf',
                    downloadButtonText: 'Download PDF',
                },
                [],
                'file-1'
            ),
        ],
    },
    {
        name: 'group with inner paragraph',
        tree: [
            makeBlock(
                'core/group',
                {},
                [makeBlock('core/paragraph', { content: 'Inner' }, [], 'p1')],
                'g1'
            ),
        ],
    },
    {
        name: 'row / stack containers',
        tree: [
            makeBlock(
                'core/row',
                {},
                [makeBlock('core/paragraph', { content: 'Row' }, [], 'rp')],
                'r1'
            ),
            makeBlock(
                'core/stack',
                {},
                [makeBlock('core/paragraph', { content: 'Stacked' }, [], 'sp')],
                's1'
            ),
        ],
    },
    {
        name: 'columns with widths',
        tree: [
            makeBlock(
                'core/columns',
                {},
                [
                    makeBlock(
                        'core/column',
                        { width: 60 },
                        [makeBlock('core/paragraph', { content: 'Left' }, [], 'lp')],
                        'col-1'
                    ),
                    makeBlock(
                        'core/column',
                        {},
                        [makeBlock('core/paragraph', { content: 'Right' }, [], 'rp')],
                        'col-2'
                    ),
                ],
                'cols-1'
            ),
        ],
    },
    {
        name: 'buttons + button (safe URL)',
        tree: [
            makeBlock(
                'core/buttons',
                { layout: { justifyContent: 'center' } },
                [
                    makeBlock(
                        'core/button',
                        {
                            text: 'Go',
                            url: 'https://example.com',
                            linkTarget: '_blank',
                        },
                        [],
                        'b1'
                    ),
                ],
                'btns-1'
            ),
        ],
    },
    {
        name: 'button with unsafe URL',
        tree: [
            makeBlock(
                'core/button',
                { text: 'Click', url: 'javascript:alert(1)' },
                [],
                'b-evil'
            ),
        ],
    },
    {
        name: 'separator',
        tree: [makeBlock('core/separator', { style: 'wide' }, [], 'sep-1')],
    },
    {
        name: 'spacer',
        tree: [makeBlock('core/spacer', { height: 48 }, [], 'sp-1')],
    },
    {
        name: 'cover with clamp + inner container',
        tree: [
            makeBlock(
                'core/cover',
                {
                    url: 'https://example.test/bg.jpg',
                    dimRatio: 60,
                    minHeight: 40,
                    minHeightUnit: 'vh',
                },
                [makeBlock('core/paragraph', { content: 'Hero' }, [], 'hp')],
                'cov-1'
            ),
        ],
    },
    {
        name: 'media-text',
        tree: [
            makeBlock(
                'core/media-text',
                {
                    mediaUrl: 'https://example.test/photo.jpg',
                    mediaAlt: 'Photo',
                    mediaPosition: 'right',
                    mediaWidth: 30,
                },
                [makeBlock('core/paragraph', { content: 'Caption' }, [], 'cp')],
                'mt-1'
            ),
        ],
    },
    {
        name: 'details',
        tree: [
            makeBlock(
                'core/details',
                { summary: 'Click me', showContent: true },
                [makeBlock('core/paragraph', { content: 'Hidden' }, [], 'p1')],
                'det-1'
            ),
        ],
    },
    {
        name: 'table with head + body',
        tree: [
            makeBlock(
                'core/table',
                {
                    caption: 'Data',
                    head: [{ cells: [{ content: 'Name', tag: 'th', align: 'left' }] }],
                    body: [{ cells: [{ content: 'Alice', tag: 'td', align: 'center' }] }],
                },
                [],
                'tbl-1'
            ),
        ],
    },
    {
        name: 'template-part wrapper with inlined inner block',
        tree: [
            makeBlock(
                'core/template-part',
                { slug: 'header', theme: 'artisanpack-base' },
                [makeBlock('core/paragraph', { content: 'Inlined header' }, [], 'p-h')],
                'tp-1'
            ),
        ],
    },
];

describe('React/Vue renderer parity', () => {
    it.each(FIXTURES)('produces identical HTML for $name', async ({ tree }) => {
        const reactHtml = renderReact(tree);
        const vueHtml = await renderVue(tree);

        expect(vueHtml).toBe(reactHtml);
    });
});
