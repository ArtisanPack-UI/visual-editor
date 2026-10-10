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
import { LayoutBaseline as ReactLayoutBaseline } from '../../visual-editor-renderer-react/src/LayoutBaseline';
import { LayoutBaseline as VueLayoutBaseline } from '../src/LayoutBaseline';
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
        name: 'list and navigation items with a screen-size visibility scope',
        tree: [
            makeBlock(
                'core/list',
                { ordered: false },
                [
                    makeBlock('core/list-item', { content: 'One' }, [], 'li-1'),
                    makeBlock('core/list-item', { content: 'Two', className: 'is-featured', _veHiddenBreakpoints: ['md'] }, [], 'li-2'),
                ],
                'list-1'
            ),
            makeBlock(
                'core/navigation',
                {},
                [
                    makeBlock('core/navigation-link', { label: 'Contact', url: '/contact', _veHiddenBreakpoints: ['md'] }, [], 'nav-link-1'),
                    makeBlock('core/navigation-submenu', { label: 'Products', url: '/products', _veHiddenBreakpoints: ['lg'] }, [
                        makeBlock('core/navigation-link', { label: 'Plans', url: '/plans' }, [], 'nav-link-2'),
                    ], 'nav-submenu-1'),
                ],
                'nav-1'
            ),
        ],
    },
    {
        name: 'navigation Block spacing with underscore / double-dash / camelCase preset slugs (#814)',
        tree: [
            makeBlock(
                'core/navigation',
                { style: { spacing: { blockGap: 'var:preset|spacing|big_gap' } } },
                [makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nav-gap-link-1')],
                'nav-gap-1'
            ),
            makeBlock(
                'core/navigation',
                { style: { spacing: { blockGap: { top: 'var:preset|spacing|x--y', left: 'var:preset|spacing|bigGap' } } } },
                [makeBlock('core/navigation-link', { label: 'Contact', url: '/contact' }, [], 'nav-gap-link-2')],
                'nav-gap-2'
            ),
        ],
    },
    {
        name: 'navigation responsive overlay with aria label + overlay colors (#804)',
        tree: [
            makeBlock(
                'core/navigation',
                { ariaLabel: 'Primary', overlayBackgroundColor: 'primary', customOverlayTextColor: '#eeeeee' },
                [makeBlock('core/navigation-link', { label: 'Home', url: '/' }, [], 'nav-ov-link-1')],
                'nav-ov-1'
            ),
            makeBlock(
                'core/navigation',
                { overlayMenu: 'always', customOverlayBackgroundColor: '#111111' },
                [makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nav-ov-link-2')],
                'nav-ov-2'
            ),
            makeBlock(
                'core/navigation',
                { overlayMenu: 'never' },
                [makeBlock('core/navigation-link', { label: 'Contact', url: '/contact' }, [], 'nav-ov-link-3')],
                'nav-ov-3'
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
        // #702 — the constrained / grid layout branches and the
        // post-content layout attribute all mint per-block compound
        // classes, so they get their own parity fixture.
        name: 'group layout variants + post-content layout',
        tree: [
            makeBlock(
                'core/group',
                { layout: { type: 'constrained' } },
                [makeBlock('core/paragraph', { content: 'Constrained' }, [], 'cp')],
                'gc'
            ),
            makeBlock(
                'core/group',
                { layout: { type: 'grid' } },
                [makeBlock('core/paragraph', { content: 'Grid' }, [], 'gp')],
                'gg'
            ),
            makeBlock(
                'core/post-content',
                { _resolvedContent: '<p>Body</p>', layout: { type: 'constrained' } },
                [],
                'pc'
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
    {
        name: 'artisanpack/single-content with resolved host post',
        tree: [
            makeBlock(
                'artisanpack/single-content',
                { _resolvedHasPost: true },
                [makeBlock('core/paragraph', { content: 'Hello' }, [], 'p1')],
                'sc-1'
            ),
        ],
    },
    {
        name: 'artisanpack/single-content with no resolved post',
        tree: [
            makeBlock(
                'artisanpack/single-content',
                { _resolvedHasPost: false },
                [makeBlock('core/paragraph', { content: 'Hidden' }, [], 'p1')],
                'sc-2'
            ),
        ],
    },
    {
        name: 'artisanpack/related-posts with two resolved items',
        tree: [
            makeBlock(
                'artisanpack/related-posts',
                { _resolvedItems: 2, numColumns: 2 },
                [
                    makeBlock('core/paragraph', { content: 'Related A' }, [], 'pa'),
                    makeBlock('core/paragraph', { content: 'Related B' }, [], 'pb'),
                ],
                'rp-1'
            ),
        ],
    },
    {
        name: 'artisanpack/related-posts with zero items',
        tree: [
            makeBlock(
                'artisanpack/related-posts',
                { _resolvedItems: 0, numColumns: 3 },
                [],
                'rp-2'
            ),
        ],
    },
    {
        name: 'artisanpack/author-social-icons icon + label chips',
        tree: [
            makeBlock(
                'artisanpack/author-social-icons',
                {
                    socialIcons: ['facebook', 'email'],
                    iconStyle: 'show-label-icon',
                    iconsDirection: 'horizontal',
                    iconsStretch: 'auto-width',
                    iconsBorderRadius: 8,
                    _resolvedAuthorSocialLinks: [
                        { slug: 'facebook', url: 'https://example.test/fb' },
                        { slug: 'email', url: 'mailto:author@example.test' },
                    ],
                },
                [],
                'asi-1'
            ),
        ],
    },
    {
        name: 'artisanpack/author-social-icons drops unselected platforms',
        tree: [
            makeBlock(
                'artisanpack/author-social-icons',
                {
                    socialIcons: ['twitter'],
                    iconStyle: 'show-icon',
                    iconsDirection: 'vertical',
                    iconsStretch: 'full-width',
                    iconsBorderRadius: 0,
                    _resolvedAuthorSocialLinks: [
                        { slug: 'twitter', url: 'https://example.test/tw' },
                        { slug: 'instagram', url: 'https://example.test/ig' },
                    ],
                },
                [],
                'asi-2'
            ),
        ],
    },
    {
        name: 'artisanpack/social-share-content icon + label chips',
        tree: [
            makeBlock(
                'artisanpack/social-share-content',
                {
                    socialIcons: ['facebook', 'reddit'],
                    iconStyle: 'show-label-icon',
                    iconsDirection: 'horizontal',
                    iconsStretch: 'auto-width',
                    iconsBorderRadius: 4,
                    _resolvedShareLinks: [
                        {
                            slug: 'facebook',
                            url: 'https://www.facebook.com/sharer.php?u=https%3A%2F%2Fexample.test%2Fpost',
                        },
                        {
                            slug: 'reddit',
                            url: 'https://www.reddit.com/submit?url=https%3A%2F%2Fexample.test%2Fpost',
                        },
                    ],
                },
                [],
                'ssc-1'
            ),
        ],
    },
    {
        name: 'artisanpack/social-share-content drops unsafe javascript URLs',
        tree: [
            makeBlock(
                'artisanpack/social-share-content',
                {
                    socialIcons: ['twitter', 'pinterest'],
                    iconStyle: 'show-label',
                    iconsDirection: 'vertical',
                    iconsStretch: 'full-width',
                    iconsBorderRadius: 0,
                    _resolvedShareLinks: [
                        { slug: 'twitter', url: 'javascript:alert(1)' },
                        {
                            slug: 'pinterest',
                            url: 'https://pinterest.com/pin/create/bookmarklet/?url=https%3A%2F%2Fexample.test',
                        },
                    ],
                },
                [],
                'ssc-2'
            ),
        ],
    },
    {
        name: 'artisanpack/search-field with no resolved value',
        tree: [
            makeBlock(
                'artisanpack/search-field',
                { label: 'Find', placeholder: 'Type a query …' },
                [],
                'sf-1'
            ),
        ],
    },
    {
        name: 'artisanpack/search-field with a resolved query',
        tree: [
            makeBlock(
                'artisanpack/search-field',
                {
                    label: 'Find',
                    placeholder: 'Type a query …',
                    _resolvedSearchValue: 'hello',
                },
                [],
                'sf-2'
            ),
        ],
    },
    {
        name: 'artisanpack/search-filters wraps inner blocks in a GET form',
        tree: [
            makeBlock(
                'artisanpack/search-filters',
                { postType: 'project', _resolvedFormAction: 'https://example.test/' },
                [
                    makeBlock(
                        'artisanpack/search-field',
                        { label: 'Find', placeholder: 'Type …' },
                        [],
                        'sf-inner'
                    ),
                ],
                'sfilters-1'
            ),
        ],
    },
    {
        name: 'artisanpack/search-filters drops unsafe action URLs',
        tree: [
            makeBlock(
                'artisanpack/search-filters',
                {
                    postType: 'post',
                    _resolvedFormAction: 'javascript:alert(1)',
                },
                [],
                'sfilters-2'
            ),
        ],
    },
    {
        name: 'artisanpack/search-filters-buttons renders submit + reset pair',
        tree: [
            makeBlock(
                'artisanpack/search-filters-buttons',
                { searchLabel: 'Go', clearLabel: 'Reset' },
                [],
                'sfb-1'
            ),
        ],
    },
    {
        name: 'artisanpack/search-filters-taxonomy with resolved terms + selection',
        tree: [
            makeBlock(
                'artisanpack/search-filters-taxonomy',
                {
                    label: 'Topic',
                    taxonomy: 'topic',
                    taxonomyName: 'Topic',
                    _resolvedTerms: [
                        { slug: 'news', name: 'News' },
                        { slug: 'guides', name: 'Guides' },
                    ],
                    _resolvedSelectedTerm: 'guides',
                },
                [],
                'sft-1'
            ),
        ],
    },
    {
        name: 'artisanpack/search-filters-taxonomy with no resolved terms',
        tree: [
            makeBlock(
                'artisanpack/search-filters-taxonomy',
                { label: 'Topic', taxonomy: 'topic', taxonomyName: 'Topic' },
                [],
                'sft-2'
            ),
        ],
    },
    {
        name: 'artisanpack/post-types-search-results wraps active children only',
        tree: [
            makeBlock(
                'artisanpack/post-types-search-results',
                {},
                [
                    makeBlock(
                        'artisanpack/single-post-types-search-results',
                        { postType: 'post', _resolvedActive: true },
                        [
                            makeBlock(
                                'core/paragraph',
                                { content: 'Active section' },
                                [],
                                'p-active'
                            ),
                        ],
                        'spr-active'
                    ),
                    makeBlock(
                        'artisanpack/single-post-types-search-results',
                        { postType: 'project', _resolvedActive: false },
                        [
                            makeBlock(
                                'core/paragraph',
                                { content: 'Inactive section' },
                                [],
                                'p-inactive'
                            ),
                        ],
                        'spr-inactive'
                    ),
                ],
                'ptsr-1'
            ),
        ],
    },
    {
        name: 'artisanpack/skills-slider with defaults',
        tree: [makeBlock('artisanpack/skills-slider', {}, [], 'ss-1')],
    },
    {
        name: 'artisanpack/skills-slider with custom level + colours',
        tree: [
            makeBlock(
                'artisanpack/skills-slider',
                {
                    skillLevel: 75,
                    barHeight: 12,
                    barColor: '#ff0000',
                    trackColor: '#eeeeee',
                    ariaLabel: 'PHP proficiency',
                },
                [],
                'ss-2'
            ),
        ],
    },
    {
        name: 'artisanpack/skills-slider clamps out-of-range values',
        tree: [
            makeBlock(
                'artisanpack/skills-slider',
                { skillLevel: 250, barHeight: 0 },
                [],
                'ss-3'
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

    it('emits the same global-styles <style> tag on both renderers', async () => {
        const css = ':root { --wp--preset--color--brand: #abcdef; }';
        const tree: Block[] = [makeBlock('core/paragraph', { content: 'Hi' }, [], 'p1')];

        const reactApp = createElement(ReactBlockTree, { tree, globalStylesCss: css });
        const reactHtml = domNormalize(renderToStaticMarkup(reactApp));

        const vueApp = createSSRApp({
            render: () => vueH(VueBlockTree, { tree, globalStylesCss: css }),
        });
        const vueHtml = domNormalize(stripVueServerMarkers(await vueRenderToString(vueApp)));

        expect(vueHtml).toBe(reactHtml);
        expect(reactHtml).toContain('<style data-ve-global-styles="">');
        expect(reactHtml).toContain('--wp--preset--color--brand');
    });

    it('expands underscore / double-dash preset slugs to the same declared names (#814)', async () => {
        const tree: Block[] = [
            makeBlock('core/navigation', { style: { spacing: { blockGap: 'var:preset|spacing|big_gap' } } }, [], 'n1'),
            makeBlock('core/navigation', { style: { spacing: { blockGap: 'var:preset|spacing|a--b' } } }, [], 'n2'),
        ];

        const reactHtml = renderReact(tree);

        expect(await renderVue(tree)).toBe(reactHtml);
        expect(reactHtml).toContain('var(--wp--preset--spacing--big-gap)');
        expect(reactHtml).toContain('var(--wp--preset--spacing--a--b)');
    });

    it('renders the same navigation overlay template part on both renderers (#804)', async () => {
        const tree: Block[] = [
            makeBlock(
                'core/navigation',
                { overlay: 'mobile-overlay' },
                [makeBlock('core/navigation-link', { label: 'Home', url: '/' }, [], 'nav-tp-link')],
                'nav-tp'
            ),
        ];
        const templateParts = [
            {
                slug: 'mobile-overlay',
                area: 'navigation-overlay',
                blocks: [makeBlock('core/paragraph', { content: 'Call us today' }, [], 'nav-tp-p')],
            },
        ];

        const reactHtml = domNormalize(
            renderToStaticMarkup(createElement(ReactBlockTree, { tree, templateParts }))
        );
        const vueApp = createSSRApp({
            render: () => vueH(VueBlockTree, { tree, templateParts }),
        });
        const vueHtml = domNormalize(stripVueServerMarkers(await vueRenderToString(vueApp)));

        expect(vueHtml).toBe(reactHtml);
        expect(reactHtml).toContain('wp-block-navigation__responsive-container-content has-overlay-template');
        expect(reactHtml).toContain('<div class="wp-block-navigation__overlay-content"><p');
    });

    it('emits the same layout baseline (spacing preset defaults + navigation gap) on both renderers (#814)', async () => {
        const reactHtml = domNormalize(renderToStaticMarkup(createElement(ReactLayoutBaseline)));
        const vueApp = createSSRApp({ render: () => vueH(VueLayoutBaseline) });
        const vueHtml = domNormalize(stripVueServerMarkers(await vueRenderToString(vueApp)));

        expect(vueHtml).toBe(reactHtml);
        expect(reactHtml).toContain('--wp--preset--spacing--40: 1.5rem;');
        expect(reactHtml).toContain(':where(.wp-block-navigation)');
    });
});
