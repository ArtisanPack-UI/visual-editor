import { render, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import {
    registerBlockRenderer,
    resetBlockRegistry,
    unregisterBlockRenderer,
} from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import { makeBlock, normalizeHtml } from './helpers';

function renderTree(tree: unknown): string {
    const { container } = render(
        <BlockTree tree={tree as Parameters<typeof BlockTree>[0]['tree']} />
    );

    return normalizeHtml(container.innerHTML);
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

        const html = render(
            <BlockTree tree={tree} />
        ).container.innerHTML;

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

        const { container } = render(<BlockTree tree={tree} />);
        const video = container.querySelector('video');

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

    it('uses an empty button label when buttonUseIcon is true', () => {
        const tree = [makeBlock('core/search', { buttonText: 'Go', buttonUseIcon: true })];

        expect(renderTree(tree)).toContain('<button type="submit" class="wp-block-search__button"></button>');
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
});

describe('Registry + dynamic fallback', () => {
    it('invokes a custom registered renderer instead of the default', () => {
        registerBlockRenderer('acme/sticker', ({ attributes }) => (
            <span data-sticker="yes">{(attributes.label as string) ?? ''}</span>
        ));

        const tree = [makeBlock('acme/sticker', { label: 'Ship it' })];

        expect(renderTree(tree)).toBe('<span data-sticker="yes">Ship it</span>');

        unregisterBlockRenderer('acme/sticker');
    });

    it('falls back to DynamicBlock when no renderer is registered', async () => {
        const fetchFn = vi.fn().mockRejectedValue(new TypeError('offline'));
        vi.stubGlobal('fetch', fetchFn);

        try {
            const tree = [makeBlock('acme/unregistered', { label: 'test' })];
            const { container } = render(<BlockTree tree={tree} />);

            expect(container.innerHTML).toContain('data-ve-dynamic-block="acme/unregistered"');

            await waitFor(() => {
                expect(container.innerHTML).toContain('data-ve-unknown-block="acme/unregistered"');
            });
        } finally {
            vi.unstubAllGlobals();
        }
    });
});
