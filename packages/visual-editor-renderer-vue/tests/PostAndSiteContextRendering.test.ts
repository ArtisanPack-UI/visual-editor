import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { resetBlockRegistry } from '../src/registry';
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

describe('Core post-context blocks (Vue)', () => {
    it('renders post-title at the configured level', () => {
        const tree = [makeBlock('core/post-title', { level: 3, _resolvedTitle: 'Hello World' })];

        expect(renderTree(tree)).toContain('<h3 class="wp-block-post-title">Hello World</h3>');
    });

    it('wraps post-title in a permalink when isLink is true', () => {
        const tree = [
            makeBlock('core/post-title', {
                isLink: true,
                _resolvedTitle: 'Linked',
                _resolvedPermalink: 'https://example.test/post',
            }),
        ];

        expect(renderTree(tree)).toContain(
            '<a href="https://example.test/post">Linked</a>'
        );
    });

    it('renders post-content with the resolved HTML body', () => {
        const tree = [makeBlock('core/post-content', { _resolvedContent: '<p>Body</p>' })];

        expect(renderTree(tree)).toBe(
            '<div class="entry-content wp-block-post-content is-layout-flow wp-block-post-content-is-layout-flow">' +
                '<p>Body</p></div>'
        );
    });

    it('renders post-excerpt with a more-text link', () => {
        const tree = [
            makeBlock('core/post-excerpt', {
                moreText: 'Read more',
                _resolvedExcerpt: 'A short excerpt',
                _resolvedPermalink: 'https://example.test/post',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('class="wp-block-post-excerpt"');
        expect(html).toContain(
            'class="wp-block-post-excerpt__more-link" href="https://example.test/post"'
        );
        expect(html).toContain('Read more');
    });

    it('renders post-date with datetime + formatted text', () => {
        const tree = [
            makeBlock('core/post-date', {
                _resolvedDate: '2026-04-20T12:00:00+00:00',
                _resolvedDateFormatted: 'April 20, 2026',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('datetime="2026-04-20T12:00:00+00:00"');
        expect(html).toContain('April 20, 2026');
    });

    it('uses the modified date when displayType=modified', () => {
        const tree = [
            makeBlock('core/post-date', {
                displayType: 'modified',
                _resolvedDate: '2026-01-01T00:00:00+00:00',
                _resolvedModifiedDate: '2026-04-20T12:00:00+00:00',
                _resolvedModifiedDateFormatted: 'Updated April 20',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('datetime="2026-04-20T12:00:00+00:00"');
        expect(html).toContain('Updated April 20');
    });

    it('renders post-author with avatar and bio', () => {
        const tree = [
            makeBlock('core/post-author', {
                showAvatar: true,
                showBio: true,
                avatarSize: 48,
                byline: 'Posted by',
                _resolvedAuthorName: 'Jane Doe',
                _resolvedAuthorBio: 'Writer.',
                _resolvedAuthorAvatar: 'https://example.test/avatar.jpg',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('Jane Doe');
        expect(html).toContain('Writer.');
        expect(html).toContain('src="https://example.test/avatar.jpg"');
        expect(html).toContain('Posted by');
    });

    it('renders post-featured-image with link wrapper', () => {
        const tree = [
            makeBlock('core/post-featured-image', {
                isLink: true,
                _resolvedImageUrl: 'https://example.test/featured.jpg',
                _resolvedImageAlt: 'Featured photo',
                _resolvedImageWidth: 800,
                _resolvedImageHeight: 600,
                _resolvedPermalink: 'https://example.test/post',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<figure class="wp-block-post-featured-image">');
        expect(html).toContain('href="https://example.test/post"');
        expect(html).toContain('src="https://example.test/featured.jpg"');
        expect(html).toContain('alt="Featured photo"');
    });

    it('covers a post-featured-image frame by default when scale is unsaved (#948)', () => {
        const html = renderTree([
            makeBlock('core/post-featured-image', {
                aspectRatio: '16/10',
                _resolvedImageUrl: 'https://example.test/wide.jpg',
            }),
        ]);

        expect(html).toMatch(/aspect-ratio:\s*16\/10;\s*object-fit:\s*cover/);
    });

    it('sizes the post-featured-image figure and image from width and height (#948)', () => {
        const html = renderTree([
            makeBlock('core/post-featured-image', {
                width: '130px',
                height: '100%',
                scale: 'contain',
                _resolvedImageUrl: 'https://example.test/wide.jpg',
            }),
        ]);

        expect(html).toMatch(/<figure[^>]*style="width:\s*130px;\s*height:\s*100%;?"/);
        expect(html).toMatch(/<img[^>]*style="height:\s*100%;\s*object-fit:\s*contain;?"/);
    });

    it('leaves an image-less post-featured-image figure unsized (#948)', () => {
        expect(renderTree([makeBlock('core/post-featured-image', { width: '130px', height: '100%' })]))
            .toBe('<figure class="wp-block-post-featured-image"></figure>');
    });

    it('paints the avatar border and radius on the image, not the wrapper (#952)', () => {
        const html = renderTree([
            makeBlock('core/avatar', {
                size: 28,
                borderColor: 'primary',
                style: { border: { radius: '50%', width: '2px' } },
                _resolvedAuthorAvatar: 'https://example.test/me.jpg',
            }),
        ]);

        expect(html).toContain('<div class="wp-block-avatar">');
        expect(html).toMatch(/<img[^>]*class="avatar wp-block-avatar__image has-border-color has-primary-border-color"[^>]*style="border-radius:\s*50%;\s*border-width:\s*2px;\s*border-style:\s*solid;?"/);
    });

    it('drops javascript: hrefs from post-featured-image', () => {
        const tree = [
            makeBlock('core/post-featured-image', {
                isLink: true,
                _resolvedImageUrl: 'https://example.test/safe.jpg',
                _resolvedPermalink: 'javascript:alert(1)',
            }),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).not.toContain('<a ');
    });

    it('renders an empty post-featured-image figure when no image is resolved', () => {
        const tree = [makeBlock('core/post-featured-image', {})];

        expect(renderTree(tree)).toBe('<figure class="wp-block-post-featured-image"></figure>');
    });
});

describe('Cover with "Use featured image" (#942)', () => {
    it('renders the stamped featured image instead of the saved url', () => {
        const html = renderTree([
            makeBlock('core/cover', {
                useFeaturedImage: true,
                url: 'https://example.test/saved.jpg',
                _resolvedImageUrl: 'https://example.test/featured.jpg',
            }),
        ]);

        expect(html).toContain('src="https://example.test/featured.jpg"');
        expect(html).not.toContain('saved.jpg');
    });

    it('renders no image when the post has none', () => {
        const html = renderTree([
            makeBlock('core/cover', { useFeaturedImage: true, url: 'https://example.test/saved.jpg' }),
        ]);

        expect(html).not.toContain('wp-block-cover__image-background');
    });
});

describe('Post Reading Time (#943)', () => {
    it('renders the minutes with the default suffix, or a custom prefix and suffix', () => {
        expect(renderTree([makeBlock('artisanpack/post-reading-time', { _resolvedReadingTime: 6 })])).toContain(
            '<div class="wp-block-artisanpack-post-reading-time">6 min read</div>'
        );
        expect(
            renderTree([
                makeBlock('artisanpack/post-reading-time', {
                    prefix: 'Reading time: ',
                    suffix: 'minutes',
                    _resolvedReadingTime: 6,
                }),
            ])
        ).toContain('>Reading time: 6 minutes</div>');
    });

    it('renders nothing without minutes', () => {
        expect(renderTree([makeBlock('artisanpack/post-reading-time', {})])).not.toContain(
            'wp-block-artisanpack-post-reading-time'
        );
    });
});

describe('Query Pagination on a single page (#955)', () => {
    const pages = (count: number) =>
        Array.from({ length: count }, (_, i) => ({ number: i + 1, url: `/blog?page=${i + 1}` }));
    const pagination = (totalPages: number) =>
        makeBlock('artisanpack/query-pagination', { _resolvedTotalPages: totalPages }, [
            makeBlock('artisanpack/query-pagination-numbers', {
                _resolvedCurrentPage: 1,
                _resolvedPageNumbers: pages(totalPages),
                _resolvedTotalPages: totalPages,
            }),
        ]);

    it('renders nothing when the results fit on one page', () => {
        expect(renderTree([pagination(1)])).not.toContain('wp-block-query-pagination');
        expect(
            renderTree([makeBlock('artisanpack/query-pagination-numbers', { _resolvedPageNumbers: pages(1) })])
        ).not.toContain('page-numbers');
    });

    it('renders the wrapper and numbers for two or more pages, and an unstamped wrapper', () => {
        const html = renderTree([pagination(2)]);

        expect(html).toContain('<nav class="wp-block-query-pagination');
        expect(html).toContain('<a class="page-numbers" href="/blog?page=2">2</a>');
        expect(renderTree([makeBlock('artisanpack/query-pagination')])).toContain('<nav class="wp-block-query-pagination');
    });

    it('keeps a one-page wrapper that holds other content', () => {
        const html = renderTree([
            makeBlock('artisanpack/query-pagination', { _resolvedTotalPages: 1 }, [
                makeBlock('core/paragraph', { content: 'Page one of one' }),
            ]),
        ]);

        expect(html).toContain('<nav class="wp-block-query-pagination');
        expect(html).toContain('Page one of one');
    });
});

describe('Core site-context blocks (Vue)', () => {
    it('renders site-title with default link to site URL', () => {
        const tree = [
            makeBlock('core/site-title', {
                level: 1,
                _resolvedSiteTitle: 'Acme',
                _resolvedSiteUrl: 'https://example.test',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<h1 class="wp-block-site-title">');
        expect(html).toContain('href="https://example.test"');
        expect(html).toContain('rel="home"');
        expect(html).toContain('Acme');
    });

    it('renders site-title as a paragraph when level is 0', () => {
        const tree = [
            makeBlock('core/site-title', {
                level: 0,
                isLink: false,
                _resolvedSiteTitle: 'Acme',
            }),
        ];

        expect(renderTree(tree)).toBe('<p class="wp-block-site-title">Acme</p>');
    });

    it('renders site-tagline', () => {
        const tree = [makeBlock('core/site-tagline', { _resolvedSiteTagline: 'A small site' })];

        expect(renderTree(tree)).toBe(
            '<p class="wp-block-site-tagline">A small site</p>'
        );
    });

    it('renders site-logo with link wrapper and width', () => {
        const tree = [
            makeBlock('core/site-logo', {
                width: 120,
                _resolvedLogoUrl: 'https://example.test/logo.svg',
                _resolvedSiteUrl: 'https://example.test',
                _resolvedSiteTitle: 'Acme',
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('class="wp-block-site-logo"');
        expect(html).not.toContain('is-default-size');
        expect(html).toContain('href="https://example.test"');
        expect(html).toContain('class="custom-logo-link"');
        expect(html).toContain('src="https://example.test/logo.svg"');
        expect(html).toContain('width="120"');
    });

    it('adds is-default-size to site-logo when no width is set', () => {
        const tree = [
            makeBlock('core/site-logo', {
                _resolvedLogoUrl: 'https://example.test/logo.svg',
                _resolvedSiteUrl: 'https://example.test',
            }),
        ];

        expect(renderTree(tree)).toContain('is-default-size');
    });
});

describe('Core navigation blocks (Vue)', () => {
    it('renders a navigation block with menu items', () => {
        const tree = [
            makeBlock(
                'core/navigation',
                { ariaLabel: 'Primary' },
                [
                    makeBlock(
                        'core/navigation-link',
                        { label: 'About', url: 'https://example.test/about' },
                        [],
                        'nl-1'
                    ),
                    makeBlock(
                        'core/navigation-submenu',
                        { label: 'More', url: 'https://example.test/more' },
                        [
                            makeBlock(
                                'core/navigation-link',
                                { label: 'Sub', url: 'https://example.test/sub' },
                                [],
                                'nl-sub'
                            ),
                        ],
                        'sub-1'
                    ),
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('class="wp-block-navigation is-layout-flex wp-block-navigation-is-layout-flex is-horizontal is-responsive"');
        expect(html).toContain('aria-label="Primary"');
        expect(html).toContain('<ul class="wp-block-navigation__container">');
        expect(html).toContain('href="https://example.test/about"');
        expect(html).toContain('wp-block-navigation-submenu');
        expect(html).toContain('href="https://example.test/sub"');
    });

    it('forces noopener when a navigation link opens in a new tab', () => {
        const tree = [
            makeBlock(
                'core/navigation',
                {},
                [
                    makeBlock(
                        'core/navigation-link',
                        {
                            label: 'External',
                            url: 'https://example.com',
                            opensInNewTab: true,
                        },
                        [],
                        'nl-1'
                    ),
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).toContain('target="_blank"');
        expect(html).toContain('rel="noopener noreferrer"');
    });

    it('maps a string Block spacing value onto the navigation wrapper (#814)', () => {
        const tree = [
            makeBlock('core/navigation', { style: { spacing: { blockGap: 'var:preset|spacing|40' } } }, [
                makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nl-1'),
            ]),
        ];

        expect(renderTree(tree)).toContain(
            'style="--wp--style--block-gap: var(--wp--preset--spacing--40);"'
        );
    });

    it.each([
        ['big_gap', 'big-gap'],
        ['x--y', 'x--y'],
        ['bigGap', 'big-gap'],
    ])('normalises the %s preset slug on the navigation wrapper like the declarations (#814)', (slug, expected) => {
        const tree = [
            makeBlock('core/navigation', { style: { spacing: { blockGap: `var:preset|spacing|${slug}` } } }, [
                makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nl-1'),
            ]),
        ];

        expect(renderTree(tree)).toContain(
            `style="--wp--style--block-gap: var(--wp--preset--spacing--${expected});"`
        );
    });

    it('normalises underscored preset slugs in the per-axis Block spacing form (#814)', () => {
        const tree = [
            makeBlock('core/navigation', { style: { spacing: { blockGap: { top: 'var:preset|spacing|big_gap', left: 'var:preset|spacing|a--b' } } } }, [
                makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nl-1'),
            ]),
        ];

        expect(renderTree(tree)).toContain(
            'style="row-gap: var(--wp--preset--spacing--big-gap); column-gap: var(--wp--preset--spacing--a--b);"'
        );
    });

    it('maps a per-axis Block spacing value to row-gap and column-gap (#814)', () => {
        const tree = [
            makeBlock('core/navigation', { style: { spacing: { blockGap: { top: '1rem', left: '2rem' } } } }, [
                makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nl-1'),
            ]),
        ];

        expect(renderTree(tree)).toContain('style="row-gap: 1rem; column-gap: 2rem;"');
    });

    it('leaves the navigation wrapper unstyled when no Block spacing is set (#814)', () => {
        const tree = [
            makeBlock('core/navigation', { style: { spacing: { blockGap: '' } } }, [
                makeBlock('core/navigation-link', { label: 'About', url: '/about' }, [], 'nl-1'),
            ]),
        ];

        expect(renderTree(tree)).toContain('<nav class="wp-block-navigation is-layout-flex wp-block-navigation-is-layout-flex is-horizontal is-responsive">');
    });

    it('drops javascript: navigation link URLs', () => {
        const tree = [
            makeBlock(
                'core/navigation',
                {},
                [
                    makeBlock(
                        'core/navigation-link',
                        { label: 'Bad', url: 'javascript:alert(1)' },
                        [],
                        'nl-1'
                    ),
                ]
            ),
        ];

        const html = renderTree(tree);

        expect(html).not.toContain('javascript:');
        expect(html).not.toContain('href=');
    });

    it('wraps each post term in its own element, linked or not (#939)', () => {
        const tree = [
            makeBlock('core/post-terms', {
                term: 'package-tag',
                _resolvedTermsByTaxonomy: {
                    'package-tag': [
                        { name: 'a11y', slug: 'a11y', url: '' },
                        { name: 'Core', slug: 'core', url: 'https://example.test/tag/core' },
                    ],
                },
            }),
        ];

        const html = renderTree(tree);

        expect(html).toContain('<span class="wp-block-post-terms__term">a11y</span><span class="wp-block-post-terms__separator">, </span>');
        expect(html).toMatch(/<a (class="wp-block-post-terms__term" href="https:\/\/example\.test\/tag\/core"|href="https:\/\/example\.test\/tag\/core" class="wp-block-post-terms__term")>Core<\/a>/);
    });
});

describe('Query Total (#962)', () => {
    const total = (attributes: Record<string, unknown>, name = 'artisanpack/query-total') =>
        renderTree([makeBlock(name, attributes)]);

    it('renders the total with the plural noun, and the singular for one result', () => {
        expect(total({ _resolvedTotal: 25 })).toContain(
            '<div class="wp-block-query-total"><strong>25</strong> results</div>'
        );
        expect(total({ _resolvedTotal: 1, singular: 'package', plural: 'packages' })).toContain(
            '<strong>1</strong> package</div>'
        );
    });

    it('renders zero results with the plural noun in either display type', () => {
        expect(total({ _resolvedTotal: 0, displayType: 'range-display' })).toContain(
            '<div class="wp-block-query-total"><strong>0</strong> results</div>'
        );
    });

    it('wraps the count in the prefix and suffix', () => {
        expect(total({ _resolvedTotal: 25, prefix: 'Showing ', plural: 'packages', suffix: '.' })).toContain(
            'Showing <strong>25</strong> packages.</div>'
        );
    });

    it('renders the current page range', () => {
        const range = (page: number, items: number) =>
            total({
                _resolvedTotal: 25,
                _resolvedCurrentPage: page,
                _resolvedPerPage: 9,
                _resolvedItems: items,
                displayType: 'range-display',
            });

        expect(range(1, 9)).toContain('<strong>1–9</strong> of <strong>25</strong> results</div>');
        expect(range(3, 7)).toContain('<strong>19–25</strong> of <strong>25</strong> results</div>');
    });

    it('renders nothing outside a resolved query, and the core alias like the fork', () => {
        expect(total({})).not.toContain('wp-block-query-total');
        expect(total({ _resolvedTotal: 4 }, 'core/query-total')).toBe(total({ _resolvedTotal: 4 }));
    });
});
