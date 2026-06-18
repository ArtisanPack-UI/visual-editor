import { defineComponent, h } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import {
    registerBlockRenderer,
    resetBlockRegistry,
    unregisterBlockRenderer,
} from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import { makeBlock, normalizeHtml } from './helpers';
import type { Block } from '../src/types';

function renderTree(tree: unknown): string {
    const wrapper = mount(BlockTree, {
        props: {
            tree: tree as Block[] | string | null | undefined,
        },
    });

    return normalizeHtml(wrapper.html());
}

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
});

describe('BlockTree normalization', () => {
    it('renders nothing for an empty tree', () => {
        expect(renderTree([])).toBe('');
    });

    it('renders nothing for null or undefined', () => {
        expect(renderTree(null)).toBe('');
        expect(renderTree(undefined)).toBe('');
    });

    it('parses a JSON-encoded string tree', () => {
        const json = JSON.stringify([makeBlock('core/paragraph', { content: 'Hi' })]);

        expect(renderTree(json)).toBe('<p class="wp-block-paragraph">Hi</p>');
    });

    it('skips entries that are not block-shaped objects', () => {
        const tree = [
            'not-a-block',
            null,
            42,
            makeBlock('core/paragraph', { content: 'Good' }),
        ];

        expect(renderTree(tree)).toContain('Good');
    });

    it('skips blocks with missing or empty names', () => {
        const tree = [
            { clientId: 'a', name: '', attributes: {}, innerBlocks: [] },
            { clientId: 'b', attributes: {}, innerBlocks: [] } as unknown,
        ];

        expect(renderTree(tree)).toBe('');
    });

    it('filters non-block entries out of innerBlocks before recursion', () => {
        const tree = [
            makeBlock(
                'core/group',
                {},
                [
                    makeBlock('core/paragraph', { content: 'Real' }, [], 'p-1'),
                    'not-a-block' as unknown as never,
                    null as unknown as never,
                    { clientId: 'x', name: '', attributes: {}, innerBlocks: [] },
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<p class="wp-block-paragraph">Real</p>');
    });
});

describe('Core text blocks', () => {
    it('renders a paragraph block', () => {
        const tree = [makeBlock('core/paragraph', { content: 'Hello <strong>world</strong>' })];

        expect(renderTree(tree)).toBe('<p class="wp-block-paragraph">Hello <strong>world</strong></p>');
    });

    it('renders a heading at the configured level', () => {
        const tree = [makeBlock('core/heading', { level: 3, content: 'Section' })];

        expect(renderTree(tree)).toBe('<h3 class="wp-block-heading">Section</h3>');
    });

    it('clamps invalid heading levels to the allowed range', () => {
        expect(renderTree([makeBlock('core/heading', { level: 99, content: 'X' })])).toContain('<h6');
        expect(renderTree([makeBlock('core/heading', { level: 0, content: 'Y' })])).toContain('<h1');
    });

    it('renders a quote with citation', () => {
        const tree = [
            makeBlock('core/quote', { citation: 'Someone Famous' }, [
                makeBlock('core/paragraph', { content: 'Quoted text' }, [], 'p1'),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<blockquote class="wp-block-quote">');
        expect(html).toContain('<p class="wp-block-paragraph">Quoted text</p>');
        expect(html).toContain('<cite>Someone Famous</cite>');
    });

    it('renders a code block', () => {
        const tree = [makeBlock('core/code', { content: 'echo 1;' })];

        expect(renderTree(tree)).toBe('<pre class="wp-block-code"><code>echo 1;</code></pre>');
    });

    it('renders a preformatted block preserving whitespace', () => {
        const tree = [makeBlock('core/preformatted', { content: 'line 1\nline 2' })];

        const wrapper = mount(BlockTree, { props: { tree } });
        const html = wrapper.html();

        expect(html).toContain('<pre class="wp-block-preformatted">');
        expect(html).toContain('line 1\nline 2');
    });

    it('renders a verse block', () => {
        const tree = [makeBlock('core/verse', { content: 'Roses are red' })];

        expect(renderTree(tree)).toContain('<pre class="wp-block-verse">Roses are red</pre>');
    });

    it('renders a pullquote with value and citation', () => {
        const tree = [
            makeBlock('core/pullquote', {
                value: 'Be bold',
                citation: 'Editor',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<figure class="wp-block-pullquote">');
        expect(html).toContain('Be bold');
        expect(html).toContain('<cite>Editor</cite>');
    });

    it('renders an unordered list with list items', () => {
        const tree = [
            makeBlock('core/list', { ordered: false }, [
                makeBlock('core/list-item', { content: 'One' }, [], 'li-1'),
                makeBlock('core/list-item', { content: 'Two' }, [], 'li-2'),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<ul class="wp-block-list">');
        expect(html).toContain('<li>One</li>');
        expect(html).toContain('<li>Two</li>');
        expect(html).toContain('</ul>');
    });

    it('renders an ordered list with start + reversed attributes', () => {
        const tree = [
            makeBlock('core/list', { ordered: true, start: 5, reversed: true }, [
                makeBlock('core/list-item', { content: 'A' }, [], 'li-1'),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<ol ');
        expect(html).toContain('start="5"');
        expect(html).toContain('reversed');
    });
});

describe('Core media blocks', () => {
    it('renders an image block with caption and link', () => {
        const tree = [
            makeBlock('core/image', {
                url: 'https://example.test/image.jpg',
                alt: 'An image',
                caption: 'A <em>caption</em>',
                href: 'https://example.test/full.jpg',
                id: 42,
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<figure class="wp-block-image">');
        expect(html).toContain('src="https://example.test/image.jpg"');
        expect(html).toContain('alt="An image"');
        expect(html).toContain('class="wp-image-42"');
        expect(html).toContain('href="https://example.test/full.jpg"');
        expect(html).toContain('<figcaption>A <em>caption</em></figcaption>');
    });

    it('drops unsafe URL schemes from image hrefs', () => {
        const tree = [
            makeBlock('core/image', {
                url: 'https://example.test/safe.jpg',
                href: 'javascript:void(0)',
                alt: 'safe',
            }),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).toContain('src="https://example.test/safe.jpg"');
        expect(html).not.toContain('<a ');
    });

    it('renders a video block with controls + poster', () => {
        const tree = [
            makeBlock('core/video', {
                src: 'https://example.test/movie.mp4',
                poster: 'https://example.test/poster.jpg',
                controls: true,
                loop: true,
                muted: true,
            }),
        ];

        const wrapper = mount(BlockTree, { props: { tree } });
        const video = wrapper.element.querySelector('video');

        expect(video).not.toBeNull();
        expect(video?.getAttribute('src')).toBe('https://example.test/movie.mp4');
        expect(video?.getAttribute('poster')).toBe('https://example.test/poster.jpg');
        expect(video?.controls).toBe(true);
        expect(video?.loop).toBe(true);
        expect(video?.muted).toBe(true);
    });

    it('renders an audio block', () => {
        const tree = [
            makeBlock('core/audio', {
                src: 'https://example.test/track.mp3',
                preload: 'metadata',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<figure class="wp-block-audio">');
        expect(html).toContain('src="https://example.test/track.mp3"');
        expect(html).toContain('preload="metadata"');
    });

    it('renders a file block with download button', () => {
        const tree = [
            makeBlock('core/file', {
                href: 'https://example.test/doc.pdf',
                fileName: 'doc.pdf',
                downloadButtonText: 'Download PDF',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<div class="wp-block-file">');
        expect(html).toContain('<a href="https://example.test/doc.pdf">doc.pdf</a>');
        expect(html).toContain('class="wp-block-file__button"');
        expect(html).toContain('Download PDF');
    });

    it('renders a gallery with columns class and nested images', () => {
        const tree = [
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
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<figure class="wp-block-gallery has-nested-images columns-3 is-cropped">');
        expect(html).toContain('src="https://example.test/a.jpg"');
        expect(html).toContain('src="https://example.test/b.jpg"');
    });

    it('renders an embed block with provider and aspect classes', () => {
        const tree = [
            makeBlock('core/embed', {
                url: 'https://youtu.be/abc123',
                providerNameSlug: 'youtube',
                aspectRatio: '16/9',
                type: 'video',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('is-provider-youtube');
        expect(html).toContain('wp-block-embed-youtube');
        expect(html).toContain('is-type-video');
        expect(html).toContain('wp-embed-aspect-16-9');
        expect(html).toContain('wp-has-aspect-ratio');
    });
});

describe('Core layout blocks', () => {
    it('renders nested inner blocks for a group', () => {
        const tree = [
            makeBlock('core/group', {}, [
                makeBlock('core/paragraph', { content: 'Inner' }, [], 'inner-1'),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<div class="wp-block-group is-layout-flow">');
        expect(html).toContain('<p class="wp-block-paragraph">Inner</p>');
    });

    it('renders a row with is-horizontal class', () => {
        const tree = [makeBlock('core/row', {}, [makeBlock('core/paragraph', { content: 'Row' }, [], 'r1')])];

        expect(renderTree(tree)).toContain('<div class="wp-block-group is-layout-flex is-horizontal">');
    });

    it('renders a stack with is-vertical class', () => {
        const tree = [makeBlock('core/stack', {}, [makeBlock('core/paragraph', { content: 'Stacked' }, [], 's1')])];

        expect(renderTree(tree)).toContain('<div class="wp-block-group is-layout-flex is-vertical">');
    });

    it('renders columns with column widths', () => {
        const tree = [
            makeBlock('core/columns', {}, [
                makeBlock(
                    'core/column',
                    { width: 60 },
                    [makeBlock('core/paragraph', { content: 'Left' }, [], 'l-1')],
                    'col-1'
                ),
                makeBlock(
                    'core/column',
                    {},
                    [makeBlock('core/paragraph', { content: 'Right' }, [], 'r-1')],
                    'col-2'
                ),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('wp-block-columns');
        expect(html).toContain('flex-basis: 60%');
        expect(html).toContain('<p class="wp-block-paragraph">Left</p>');
        expect(html).toContain('<p class="wp-block-paragraph">Right</p>');
    });

    it('renders a button with safe URL and forces noopener on _blank', () => {
        const tree = [
            makeBlock('core/button', {
                text: 'External',
                url: 'https://example.com',
                linkTarget: '_blank',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('target="_blank"');
        expect(html).toContain('rel="noopener noreferrer"');
    });

    it('renders a span fallback when a button URL is unsafe', () => {
        const tree = [
            makeBlock('core/button', { text: 'Click', url: 'javascript:alert(1)' }),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).not.toContain('<a ');
        expect(html).toContain('<span class="wp-block-button__link');
    });

    it('renders buttons wrapper with justify class', () => {
        const tree = [
            makeBlock(
                'core/buttons',
                { layout: { justifyContent: 'center' } },
                [
                    makeBlock(
                        'core/button',
                        { text: 'Go', url: 'https://example.com' },
                        [],
                        'b1'
                    ),
                ]
            ),
        ];

        expect(renderTree(tree)).toContain('is-content-justification-center');
    });
});

describe('Core design blocks', () => {
    it('renders a separator block with style class', () => {
        const tree = [makeBlock('core/separator', { style: 'wide' })];

        expect(renderTree(tree)).toBe('<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide">');
    });

    it('renders a spacer with numeric height as px', () => {
        const tree = [makeBlock('core/spacer', { height: 48 })];

        expect(renderTree(tree)).toContain('height: 48px');
    });

    it('drops unsafe cover background URLs', () => {
        const tree = [
            makeBlock('core/cover', {
                url: 'javascript:alert(1)',
            }),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).not.toContain('<img');
    });

    it('clamps cover dimRatio and whitelists minHeightUnit', () => {
        const tree = [
            makeBlock('core/cover', {
                dimRatio: 250,
                minHeight: 40,
                minHeightUnit: 'javascript:alert(1)',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('opacity: 1');
        expect(html).toContain('min-height: 40px');
        expect(html).not.toContain('javascript:');
    });

    it('validates table cell alignment against an allowlist', () => {
        const tree = [
            makeBlock('core/table', {
                body: [
                    {
                        cells: [
                            { content: 'A', tag: 'td', align: 'center' },
                            { content: 'B', tag: 'td', align: 'expression(alert(1))' },
                        ],
                    },
                ],
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('text-align: center');
        expect(html).not.toContain('expression');
    });

    it('renders media-text with right-side grid template', () => {
        const tree = [
            makeBlock('core/media-text', {
                mediaUrl: 'https://example.test/photo.jpg',
                mediaPosition: 'right',
                mediaWidth: 30,
            }),
        ];

        expect(renderTree(tree)).toContain('grid-template-columns: auto 30%');
    });

    it('drops unsafe media-text mediaUrl schemes', () => {
        const tree = [
            makeBlock('core/media-text', {
                mediaUrl: 'javascript:alert(1)',
                mediaType: 'image',
                mediaAlt: 'x',
            }),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).not.toContain('<img');
    });

    it('renders a details block with showContent=true open', () => {
        const tree = [
            makeBlock('core/details', { summary: 'Click me', showContent: true }, [
                makeBlock('core/paragraph', { content: 'Hidden' }, [], 'p1'),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toMatch(/<details class="wp-block-details" open(="")?>/);
        expect(html).toContain('<summary>Click me</summary>');
        expect(html).toContain('Hidden');
    });

    it('generates unique ids for multiple search blocks on the same page', () => {
        const tree = [
            makeBlock('core/search', { label: 'First', buttonText: 'Go' }, [], 's-1'),
            makeBlock('core/search', { label: 'Second', buttonText: 'Find' }, [], 's-2'),
        ];

        const html = renderTree(tree);
        const ids = Array.from(html.matchAll(/id="(wp-block-search-input-[\w-]+)"/g)).map((m) => m[1]);

        expect(ids).toHaveLength(2);
        expect(ids[0]).not.toBe(ids[1]);
    });

    it('gives two search blocks with identical attributes different ids', () => {
        const identicalAttrs = { label: 'Same', buttonText: 'Go' };
        const tree = [
            makeBlock('core/search', identicalAttrs, [], 'a'),
            makeBlock('core/search', identicalAttrs, [], 'b'),
        ];

        const html = renderTree(tree);
        const ids = Array.from(html.matchAll(/id="(wp-block-search-input-[\w-]+)"/g)).map((m) => m[1]);

        expect(ids).toHaveLength(2);
        expect(ids[0]).not.toBe(ids[1]);
    });

    it('renders an icon-only submit button with accessible name when buttonUseIcon is true', () => {
        const tree = [makeBlock('core/search', { buttonText: 'Go', buttonUseIcon: true })];

        const html = renderTree(tree);

        expect(html).toContain('class="wp-block-search__button has-icon"');
        expect(html).toContain('aria-label="Go"');
        expect(html).toContain('<svg class="wp-block-search__button-icon"');
        expect(html).not.toContain('<button type="submit" class="wp-block-search__button"></button>');
    });

    it('falls back to label when buttonText is empty and buttonUseIcon is true', () => {
        const tree = [
            makeBlock('core/search', { label: 'Find stuff', buttonText: '', buttonUseIcon: true }),
        ];

        expect(renderTree(tree)).toContain('aria-label="Find stuff"');
    });

    it('carries the #338 a11y fix forward to artisanpack/search (I4 fork)', () => {
        const tree = [makeBlock('artisanpack/search', { buttonText: 'Go', buttonUseIcon: true })];

        const html = renderTree(tree);

        expect(html).toContain('class="wp-block-search__button has-icon"');
        expect(html).toContain('aria-label="Go"');
        expect(html).toContain('<svg class="wp-block-search__button-icon"');
        expect(html).not.toContain('<button type="submit" class="wp-block-search__button"></button>');
    });

    it('renders the artisanpack/callout reference block', () => {
        const tree = [
            makeBlock('artisanpack/callout', {
                severity: 'success',
                icon: 'check',
                content: 'Nice work.',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('class="ap-callout ap-callout--success"');
        expect(html).toContain('data-severity="success"');
        expect(html).toContain('<div class="ap-callout__body">Nice work.</div>');
        expect(html).toContain('<svg');
    });

    it('normalizes invalid callout severity and icon to safe defaults', () => {
        const tree = [
            makeBlock('artisanpack/callout', {
                severity: 'galactic',
                icon: 'rocket',
                content: 'fallback',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('ap-callout--info');
        expect(html).toContain('data-severity="info"');
    });

    it('renders the artisanpack/breadcrumbs block with a resolved trail and schema microdata', () => {
        const tree = [
            makeBlock('artisanpack/breadcrumbs', {
                separatorIcon: 'chevron-right',
                breadcrumbsSchema: true,
                _resolvedTrail: [
                    { label: 'Home', url: '/' },
                    { label: 'Blog', url: '/blog' },
                    { label: 'Hello World', current: true },
                ],
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<nav');
        expect(html).toContain('class="ap-breadcrumbs"');
        expect(html).toContain('aria-label="Breadcrumb"');
        expect(html).toContain('itemtype="https://schema.org/BreadcrumbList"');
        expect(html).toContain('itemtype="https://schema.org/ListItem"');
        expect(html).toContain('<a class="ap-breadcrumbs__link" href="/"');
        expect(html).toContain('<a class="ap-breadcrumbs__link" href="/blog"');
        expect(html).toContain('Hello World');
        expect(html).toContain('aria-current="page"');
        expect(html).toContain('itemprop="position" content="1"');
        expect(html).toContain('itemprop="position" content="3"');
        expect(html).toContain('d="m9 6 6 6-6 6"');
    });

    it('normalizes invalid breadcrumbs separator and omits schema when disabled', () => {
        const tree = [
            makeBlock('artisanpack/breadcrumbs', {
                separatorIcon: 'spinning-rocket',
                breadcrumbsSchema: false,
                _resolvedTrail: [
                    { label: 'Home', url: '/' },
                    { label: 'Page', current: true },
                ],
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('d="m9 6 6 6-6 6"');
        expect(html).not.toContain('schema.org/BreadcrumbList');
        expect(html).not.toContain('itemprop="position"');
    });

    it('drops unsafe URLs from breadcrumbs trail entries', () => {
        const tree = [
            makeBlock('artisanpack/breadcrumbs', {
                _resolvedTrail: [
                    { label: 'Home', url: '/' },
                    { label: 'XSS', url: 'javascript:alert(1)' },
                    { label: 'Page', current: true },
                ],
            }),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).toContain('href="/"');
        expect(html).toContain('>XSS<');
        expect((html.match(/ap-breadcrumbs__current/g) ?? []).length).toBe(1);
    });

    it('renders an empty breadcrumbs list when the trail is missing', () => {
        const tree = [makeBlock('artisanpack/breadcrumbs', {})];

        const html = renderTree(tree);

        expect(html).toContain('ap-breadcrumbs__list');
        expect(html).not.toContain('<li class="ap-breadcrumbs__item');
    });

    describe('artisanpack/copyright (year is read at render time)', () => {
        // Pin the clock so these specs don't drift on the Dec 31 → Jan 1
        // boundary; the renderer reads `new Date().getUTCFullYear()` so
        // any test that hard-codes a year would otherwise need a real
        // calendar to stay accurate.
        const PINNED_YEAR = 2030;
        const PINNED_DATE = new Date(Date.UTC(PINNED_YEAR, 5, 15));

        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(PINNED_DATE);
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('renders the artisanpack/copyright block with © text and the current year', () => {
            const tree = [
                makeBlock('artisanpack/copyright', {
                    copyrightType: 'icon-text',
                    copyrightText: 'Acme, Inc.',
                }),
            ];

            const html = renderTree(tree);

            expect(html).toContain('class="ap-copyright"');
            expect(html).toContain(`© Acme, Inc. ${PINNED_YEAR}`);
        });

        it('omits the text when copyright type is icon-only and drops the © when text-only', () => {
            const iconOnly = renderTree([
                makeBlock('artisanpack/copyright', {
                    copyrightType: 'icon-only',
                    copyrightText: 'Should not appear',
                }),
            ]);
            const textOnly = renderTree([
                makeBlock('artisanpack/copyright', {
                    copyrightType: 'text-only',
                    copyrightText: 'Plain',
                }),
            ]);

            expect(iconOnly).toContain(`© ${PINNED_YEAR}`);
            expect(iconOnly).not.toContain('Should not appear');
            expect(textOnly).toContain(`Plain ${PINNED_YEAR}`);
            expect(textOnly).not.toContain('©');
        });

        it('falls back to icon-text when copyright type is invalid', () => {
            const html = renderTree([
                makeBlock('artisanpack/copyright', {
                    copyrightType: 'made-up-mode',
                    copyrightText: 'Fallback',
                }),
            ]);

            expect(html).toContain(`© Fallback ${PINNED_YEAR}`);
        });
    });

    it('renders the artisanpack/marquee block with width + animation styles applied', () => {
        const tree = [
            makeBlock('artisanpack/marquee', {
                marqueeContent: 'Breaking news',
                marqueeWidth: 60,
                marqueeSpeed: 10,
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('class="ap-marquee"');
        expect(html).toContain('width: 60%');
        expect(html).toContain('animation: ap-marquee-scroll 10s linear infinite');
        expect(html).toContain('class="ap-marquee__text"');
        expect(html).toContain('Breaking news');
    });

    it('clamps marquee width and speed to their valid range and falls back on non-numeric input', () => {
        const html = renderTree([
            makeBlock('artisanpack/marquee', {
                marqueeContent: 'x',
                marqueeWidth: 999,
                marqueeSpeed: 'fast',
            }),
        ]);

        expect(html).toContain('width: 100%');
        expect(html).toContain('animation: ap-marquee-scroll 5s linear infinite');
    });

    it('renders the artisanpack/comments-number block with resolved count + plural label', () => {
        const html = renderTree([
            makeBlock('artisanpack/comments-number', {
                _resolvedCommentCount: 5,
                singularCommentText: 'Reply',
                pluralCommentText: 'Replies',
            }),
        ]);

        expect(html).toContain('class="ap-comments-number"');
        expect(html).toContain('5 Replies');
        expect(html).not.toContain('Reply<');
    });

    it('uses the singular comments-number label when the resolved count is exactly one', () => {
        const html = renderTree([
            makeBlock('artisanpack/comments-number', {
                _resolvedCommentCount: 1,
                singularCommentText: 'Reply',
                pluralCommentText: 'Replies',
            }),
        ]);

        expect(html).toContain('1 Reply');
    });

    it('falls back to zero comments and default labels when the resolved count is missing', () => {
        const html = renderTree([makeBlock('artisanpack/comments-number', {})]);

        expect(html).toContain('0 Comments');
    });

    it('renders the artisanpack/accordions family with nested grandchildren', () => {
        const tree = [
            makeBlock('artisanpack/accordions', {}, [
                makeBlock(
                    'artisanpack/accordion',
                    { panelId: 'faq-1', panelIcon: 'arrows' },
                    [
                        makeBlock('artisanpack/accordion-title', {}, [
                            makeBlock('core/heading', {
                                level: 3,
                                content: 'Question',
                            }),
                        ]),
                        makeBlock('artisanpack/accordion-body', {}, [
                            makeBlock('core/paragraph', { content: 'Answer.' }),
                        ]),
                    ]
                ),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('class="ap-accordions"');
        expect(html).toContain('class="ap-accordion"');
        expect(html).toContain('data-panel-id="faq-1"');
        expect(html).toContain('data-panel-icon="arrows"');
        expect(html).toContain('id="faq-1-control"');
        expect(html).toContain('aria-controls="faq-1"');
        expect(html).toContain('aria-expanded="false"');
        expect(html).toContain('ap-accordion__icon--arrows');
        expect(html).toContain('id="faq-1"');
        expect(html).toContain('aria-labelledby="faq-1-control"');
        expect(html).toContain('<p class="wp-block-paragraph">Answer.</p>');
    });

    it('falls back to safe defaults when accordion panelIcon is invalid', () => {
        const tree = [
            makeBlock('artisanpack/accordion', {
                panelId: 'faq-1',
                panelIcon: 'evil"><script>',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('data-panel-icon="plus-minus"');
        expect(html).not.toContain('<script>');
    });

    it('omits accordion aria wiring when the parent panel id is empty', () => {
        const tree = [
            makeBlock(
                'artisanpack/accordion',
                { panelId: '', panelIcon: 'plus-minus' },
                [
                    makeBlock('artisanpack/accordion-title', {}),
                    makeBlock('artisanpack/accordion-body', {}),
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('aria-controls=');
        expect(html).not.toContain('aria-labelledby=');
        expect(html).not.toContain('id="-control"');
    });

    it('renders the artisanpack/tabs family with triggers derived from tab-section children', () => {
        const tree = [
            makeBlock(
                'artisanpack/tabs',
                {
                    tabsAlign: 'horizontal',
                    tabsSpacing: 'center',
                },
                [
                    makeBlock(
                        'artisanpack/tab-section',
                        { label: 'Overview', tabId: 'overview' },
                        [makeBlock('core/paragraph', { content: 'Tab body' })]
                    ),
                    makeBlock('artisanpack/tab-section', {
                        label: 'Specs',
                        tabId: 'specs',
                    }),
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('align-tabs-horizontal');
        expect(html).toContain('space-tabs-center');
        expect(html).toContain('data-ap-tabs');
        expect(html).toContain('role="tablist"');
        expect(html).toContain('href="#tabs-panel-overview"');
        expect(html).toContain('aria-controls="tabs-panel-overview"');
        expect(html).toContain('id="tabs-tab-overview"');
        expect(html).toContain('aria-selected="true"');
        expect(html).toContain('aria-selected="false"');
        expect(html).toContain('>Overview</a>');
        expect(html).toContain('>Specs</a>');
        expect(html).toContain('id="tabs-panel-overview"');
        expect(html).toContain('aria-labelledby="tabs-tab-overview"');
        expect(html).toContain('role="tabpanel"');
        expect(html).toContain('Tab body');
    });

    it('falls back to safe defaults when tabsAlign / tabsSpacing are invalid', () => {
        const tree = [
            makeBlock('artisanpack/tabs', {
                tabsAlign: 'diagonal',
                tabsSpacing: 'sprawl',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('align-tabs-horizontal');
        expect(html).toContain('space-tabs-start');
    });

    it('deduplicates tab ids when two sections share the same slug', () => {
        const tree = [
            makeBlock('artisanpack/tabs', {}, [
                makeBlock('artisanpack/tab-section', {
                    label: 'One',
                    tabId: 'overview',
                }),
                makeBlock('artisanpack/tab-section', {
                    label: 'Two',
                    tabId: 'overview',
                }),
                makeBlock('artisanpack/tab-section', {
                    label: 'Three',
                    tabId: 'overview',
                }),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('id="tabs-panel-overview"');
        expect(html).toContain('id="tabs-panel-overview-2"');
        expect(html).toContain('id="tabs-panel-overview-3"');
        expect(html).toContain('href="#tabs-panel-overview-2"');
        expect(html).toContain('href="#tabs-panel-overview-3"');
    });

    it('auto-fills tab labels and ids by position when sections leave them blank', () => {
        const tree = [
            makeBlock('artisanpack/tabs', {}, [
                makeBlock('artisanpack/tab-section', {}),
                makeBlock('artisanpack/tab-section', {}),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).toContain('>Tab 1</a>');
        expect(html).toContain('>Tab 2</a>');
        expect(html).toContain('href="#tabs-panel-tab-1"');
        expect(html).toContain('href="#tabs-panel-tab-2"');
        // Triggers and panels MUST share the same resolved id so the
        // aria-controls / id hookup actually resolves.
        expect(html).toContain('id="tabs-panel-tab-1"');
        expect(html).toContain('id="tabs-panel-tab-2"');
        expect(html).toContain('aria-labelledby="tabs-tab-tab-1"');
        expect(html).toContain('aria-labelledby="tabs-tab-tab-2"');
    });

    it('renders a tab-section without aria wiring when tabId is empty', () => {
        const tree = [
            makeBlock('artisanpack/tab-section', { tabId: '' }, [
                makeBlock('core/paragraph', { content: 'Naked' }),
            ]),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('id="tabs-panel-"');
        expect(html).not.toContain('aria-labelledby=');
        expect(html).toContain('Naked');
    });

    it('renders an artisanpack/grid tree with per-breakpoint column + span classes', () => {
        const tree = [
            makeBlock(
                'artisanpack/grid',
                { numColumns: 4 },
                [
                    makeBlock(
                        'artisanpack/grid-item',
                        {
                            innerLayout: 'center',
                            gridColumnSpan: 2,
                            gridRowSpan: 1,
                        },
                        [makeBlock('core/paragraph', { content: 'Cell content' })]
                    ),
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('ap-grid');
        expect(html).toContain('ap-grid-has-4-base-columns');
        expect(html).toContain('ap-grid-item');
        expect(html).toContain('ap-grid-item-layout-center');
        expect(html).toContain('ap-grid-item-span-2-base-columns');
        expect(html).toContain('ap-grid-item-span-1-base-row');
        expect(html).toContain('Cell content');
    });

    it('emits per-breakpoint grid column classes from responsive.numColumns', () => {
        const html = renderTree([
            makeBlock('artisanpack/grid', {
                numColumns: 1,
                responsive: { numColumns: { md: 2, lg: 4 } },
            }),
        ]);

        expect(html).toContain('ap-grid-has-1-base-columns');
        expect(html).toContain('ap-grid-has-2-md-columns');
        expect(html).toContain('ap-grid-has-4-lg-columns');
    });

    it('emits per-breakpoint span classes from responsive.gridColumnSpan / gridRowSpan', () => {
        const html = renderTree([
            makeBlock('artisanpack/grid-item', {
                gridColumnSpan: 1,
                gridRowSpan: 1,
                responsive: {
                    gridColumnSpan: { md: 2, lg: 3 },
                    gridRowSpan: { md: 2 },
                },
            }),
        ]);

        expect(html).toContain('ap-grid-item-span-1-base-columns');
        expect(html).toContain('ap-grid-item-span-1-base-row');
        expect(html).toContain('ap-grid-item-span-2-md-columns');
        expect(html).toContain('ap-grid-item-span-3-lg-columns');
        expect(html).toContain('ap-grid-item-span-2-md-row');
    });

    it('clamps grid column counts outside 1-12 to safe defaults', () => {
        const tooHigh = renderTree([
            makeBlock('artisanpack/grid', { numColumns: 99 }),
        ]);
        const tooLow = renderTree([
            makeBlock('artisanpack/grid', { numColumns: 0 }),
        ]);

        expect(tooHigh).toContain('ap-grid-has-12-base-columns');
        expect(tooLow).toContain('ap-grid-has-1-base-columns');
    });

    it('clamps grid-item spans outside 1-12 to safe defaults', () => {
        const tooHigh = renderTree([
            makeBlock('artisanpack/grid-item', {
                gridColumnSpan: 99,
                gridRowSpan: 0,
            }),
        ]);

        expect(tooHigh).toContain('ap-grid-item-span-12-base-columns');
        expect(tooHigh).toContain('ap-grid-item-span-1-base-row');
    });

    it('skips non-numeric responsive.numColumns overrides instead of clamping them to 1', () => {
        const html = renderTree([
            makeBlock('artisanpack/grid', {
                numColumns: 3,
                responsive: { numColumns: { md: 'evil', lg: 4 } },
            }),
        ]);

        expect(html).toContain('ap-grid-has-3-base-columns');
        expect(html).not.toContain('ap-grid-has-1-md-columns');
        expect(html).not.toContain('md-columns');
        expect(html).toContain('ap-grid-has-4-lg-columns');
    });

    it('emits the masonry layout-mode class + data-ap-cols on the grid wrapper when layoutMode is masonry (#593)', () => {
        const html = renderTree([
            makeBlock('artisanpack/grid', {
                numColumns: 3,
                layoutMode: 'masonry',
            }),
        ]);

        expect(html).toContain('ap-grid-layout-masonry');
        expect(html).toContain('data-ap-cols="3"');
        expect(html).not.toContain('ap-grid-layout-fixed');
    });

    it('emits the fixed layout-mode class on the grid wrapper by default (#593)', () => {
        const html = renderTree([
            makeBlock('artisanpack/grid', { numColumns: 3 }),
        ]);

        expect(html).toContain('ap-grid-layout-fixed');
        expect(html).not.toContain('ap-grid-layout-masonry');
        expect(html).not.toContain('data-ap-cols');
    });

    it('falls back to a safe default when grid-item innerLayout is invalid', () => {
        const tree = [
            makeBlock('artisanpack/grid-item', {
                innerLayout: 'evil"><script>',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('ap-grid-item-layout-normal');
        expect(html).not.toContain('<script>');
        expect(html).not.toContain('ap-grid-item-layout-evil');
    });

    it('renders artisanpack/next-post wrapper around its inner blocks when an adjacent post is resolved', () => {
        const tree = [
            makeBlock(
                'artisanpack/next-post',
                { _resolvedHasAdjacent: true },
                [makeBlock('core/paragraph', { content: 'Adjacent body' })]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('wp-block-artisanpack-next-post');
        expect(html).toContain('navigation-post');
        expect(html).toContain('Adjacent body');
    });

    it('emits nothing for artisanpack/next-post when no adjacent post is resolved', () => {
        const tree = [
            makeBlock(
                'artisanpack/next-post',
                { _resolvedHasAdjacent: false },
                [makeBlock('core/paragraph', { content: 'Hidden' })]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toBe('');
    });

    it('renders artisanpack/previous-post wrapper around its inner blocks when an adjacent post is resolved', () => {
        const tree = [
            makeBlock(
                'artisanpack/previous-post',
                { _resolvedHasAdjacent: true },
                [makeBlock('core/paragraph', { content: 'Older neighbor' })]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('wp-block-artisanpack-previous-post');
        expect(html).toContain('navigation-post');
        expect(html).toContain('Older neighbor');
    });

    it('emits nothing for artisanpack/previous-post when no adjacent post is resolved', () => {
        const tree = [
            makeBlock(
                'artisanpack/previous-post',
                { _resolvedHasAdjacent: false },
                [makeBlock('core/paragraph', { content: 'Hidden' })]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toBe('');
    });
});

describe('Registry + dynamic fallback', () => {
    it('invokes a custom registered renderer instead of the default', () => {
        const stickerRenderer = defineComponent({
            props: {
                name: { type: String, required: true },
                attributes: { type: Object, required: true },
                innerBlocks: { type: Array, required: true },
            },
            setup(props) {
                return () =>
                    h(
                        'span',
                        { 'data-sticker': 'yes' },
                        (props.attributes.label as string) ?? ''
                    );
            },
        });

        registerBlockRenderer('acme/sticker', stickerRenderer);

        const tree = [makeBlock('acme/sticker', { label: 'Ship it' })];

        expect(renderTree(tree)).toBe('<span data-sticker="yes">Ship it</span>');

        unregisterBlockRenderer('acme/sticker');
    });

    it('falls back to DynamicBlock when no renderer is registered', async () => {
        const fetchFn = vi.fn().mockRejectedValue(new TypeError('offline'));
        vi.stubGlobal('fetch', fetchFn);

        try {
            const tree = [makeBlock('acme/unregistered', { label: 'test' })];
            const wrapper = mount(BlockTree, { props: { tree } });

            expect(wrapper.html()).toContain('data-ve-dynamic-block="acme/unregistered"');

            await flushPromises();

            expect(wrapper.html()).toContain('data-ve-unknown-block="acme/unregistered"');
        } finally {
            vi.unstubAllGlobals();
        }
    });
});
