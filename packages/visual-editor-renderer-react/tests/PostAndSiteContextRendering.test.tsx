import { render } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { resetBlockRegistry } from '../src/registry';
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

describe('Core post-context blocks', () => {
    it('renders post-title at the configured level', () => {
        const tree = [
            makeBlock('core/post-title', { level: 3, _resolvedTitle: 'Hello World' }),
        ];

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

        const html = renderTree(tree);

        expect(html).toContain('<a href="https://example.test/post">Linked</a>');
    });

    it('renders post-content with the resolved HTML body', () => {
        const tree = [makeBlock('core/post-content', { _resolvedContent: '<p>Body</p>' })];

        expect(renderTree(tree)).toBe(
            '<div class="entry-content wp-block-post-content"><p>Body</p></div>'
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
            '<a class="wp-block-post-excerpt__more-link" href="https://example.test/post">Read more</a>'
        );
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

describe('Core site-context blocks', () => {
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
        const tree = [
            makeBlock('core/site-tagline', { _resolvedSiteTagline: 'A small site' }),
        ];

        expect(renderTree(tree)).toBe('<p class="wp-block-site-tagline">A small site</p>');
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

describe('Core navigation blocks', () => {
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

        expect(html).toContain('class="wp-block-navigation is-horizontal is-responsive"');
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
});
