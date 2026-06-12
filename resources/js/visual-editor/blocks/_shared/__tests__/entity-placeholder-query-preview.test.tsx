/**
 * Tests for the `artisanpack/postPreview` block-context fallback added in
 * #483 — when an `artisanpack/post-*` block is inside an
 * `artisanpack/query` loop, the editor canvas previews the resolved
 * first post's data instead of the placeholder label.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'fallback-wrapper' }),
    // The post-title edit exposes an InspectorControls panel for the
    // isLink / linkTarget / rel attributes — stub it out as a no-op
    // wrapper so the test environment doesn't have to mount the real
    // sidebar slot.
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    // Minimal PlainText stub; the query-preview tests never reach the
    // editable post-title path, but the import has to resolve when the
    // edit module loads.
    PlainText: ({
        value,
        onChange,
        placeholder,
    }: {
        value: string;
        onChange: (next: string) => void;
        placeholder?: string;
    }) => (
        <textarea
            aria-label="Post title"
            value={value}
            placeholder={placeholder}
            onChange={(event) => onChange(event.target.value)}
        />
    ),
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    ToggleControl: () => null,
    TextControl: () => null,
}));

import { createEntityPlaceholderEdit } from '../entity-placeholder-edit';

import PostTitleEdit from '../../post-title/edit';
import PostDateEdit from '../../post-date/edit';
import PostExcerptEdit from '../../post-excerpt/edit';
import PostAuthorEdit from '../../post-author/edit';
import PostAuthorNameEdit from '../../post-author-name/edit';
import PostAuthorBiographyEdit from '../../post-author-biography/edit';
import AvatarEdit from '../../avatar/edit';
import PostFeaturedImageEdit from '../../post-featured-image/edit';

import postTitleMeta from '../../post-title/block.json';
import postDateMeta from '../../post-date/block.json';
import postExcerptMeta from '../../post-excerpt/block.json';
import postAuthorMeta from '../../post-author/block.json';
import postAuthorNameMeta from '../../post-author-name/block.json';
import postAuthorBiographyMeta from '../../post-author-biography/block.json';
import avatarMeta from '../../avatar/block.json';
import postFeaturedImageMeta from '../../post-featured-image/block.json';

const previewKey = 'artisanpack/postPreview';

describe('createEntityPlaceholderEdit — query-preview context', () => {
    it('renders the value pulled from `artisanpack/postPreview` when no resolved attribute is present', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Title',
            resolvedKey: '_resolvedTitle',
            kind: 'text',
            fromQueryPreview: (post) =>
                typeof post.title === 'string' && post.title !== ''
                    ? { text: post.title }
                    : null,
        });

        const { getByText, queryByText } = render(
            <Edit
                attributes={{}}
                context={{ [previewKey]: { id: 1, title: 'A real post' } }}
            />
        );

        expect(getByText('A real post')).not.toBeNull();
        // The placeholder label must not appear when the context value resolves.
        expect(queryByText('Post Title')).toBeNull();
    });

    it('prefers the stamped `_resolved*` attribute over the context value', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Title',
            resolvedKey: '_resolvedTitle',
            kind: 'text',
            fromQueryPreview: (post) =>
                typeof post.title === 'string' ? { text: post.title } : null,
        });

        const { getByText, queryByText } = render(
            <Edit
                attributes={{ _resolvedTitle: 'Stamped title' }}
                context={{ [previewKey]: { id: 1, title: 'Context title' } }}
            />
        );

        expect(getByText('Stamped title')).not.toBeNull();
        expect(queryByText('Context title')).toBeNull();
    });

    it('falls back to the placeholder when neither the attribute nor the context value is present', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Title',
            resolvedKey: '_resolvedTitle',
            kind: 'text',
            fromQueryPreview: (post) =>
                typeof post.title === 'string' && post.title !== ''
                    ? { text: post.title }
                    : null,
        });

        const { getByText } = render(<Edit attributes={{}} context={{}} />);

        expect(getByText('Post Title')).not.toBeNull();
    });

    it('falls back to the placeholder when the extractor returns null', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Title',
            resolvedKey: '_resolvedTitle',
            kind: 'text',
            fromQueryPreview: () => null,
        });

        const { getByText } = render(
            <Edit
                attributes={{}}
                context={{ [previewKey]: { id: 1, title: 'A real post' } }}
            />
        );

        expect(getByText('Post Title')).not.toBeNull();
    });

    it('renders an image from the context value for `image` kind blocks', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Featured Image',
            resolvedKey: '_resolvedImageUrl',
            kind: 'image',
            altKey: '_resolvedImageAlt',
            fromQueryPreview: (post) => {
                if (!post.featuredImage) {
                    return null;
                }
                return {
                    imageUrl: post.featuredImage.url,
                    imageAlt: post.featuredImage.alt ?? '',
                };
            },
        });

        const { container } = render(
            <Edit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        featuredImage: {
                            url: 'https://example.test/hero.jpg',
                            alt: 'Hero',
                        },
                    },
                }}
            />
        );

        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('https://example.test/hero.jpg');
        expect(img?.getAttribute('alt')).toBe('Hero');
    });

    it('treats a non-object context value as absent', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Title',
            resolvedKey: '_resolvedTitle',
            kind: 'text',
            fromQueryPreview: (post) =>
                typeof post.title === 'string' && post.title !== ''
                    ? { text: post.title }
                    : null,
        });

        const { getByText } = render(
            <Edit attributes={{}} context={{ [previewKey]: 'not-an-object' }} />
        );

        expect(getByText('Post Title')).not.toBeNull();
    });
});

describe('post-* edit components — query-preview integration', () => {
    it('post-title shows the title from context', () => {
        const { getByText } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ [previewKey]: { id: 1, title: 'My first post' } }}
            />
        );
        expect(getByText('My first post')).not.toBeNull();
    });

    it('post-date prefers `dateFormatted` from context', () => {
        const { getByText } = render(
            <PostDateEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        dateFormatted: 'May 1, 2026',
                        publishedAt: '2026-05-01T12:00:00+00:00',
                    },
                }}
            />
        );
        expect(getByText('May 1, 2026')).not.toBeNull();
    });

    it('post-date falls back to the ISO date portion when `dateFormatted` is absent', () => {
        const { getByText } = render(
            <PostDateEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        publishedAt: '2026-05-01T12:00:00+00:00',
                    },
                }}
            />
        );
        expect(getByText('2026-05-01')).not.toBeNull();
    });

    it('post-excerpt shows the excerpt from context', () => {
        const { getByText } = render(
            <PostExcerptEdit
                attributes={{}}
                context={{
                    [previewKey]: { id: 1, excerpt: 'A short summary.' },
                }}
            />
        );
        expect(getByText('A short summary.')).not.toBeNull();
    });

    it('post-author shows the author name from context', () => {
        const { getByText } = render(
            <PostAuthorEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        author: { name: 'Jane Doe' },
                    },
                }}
            />
        );
        expect(getByText('Jane Doe')).not.toBeNull();
    });

    it('post-author falls back to placeholder when context has no author', () => {
        const { getByText } = render(
            <PostAuthorEdit
                attributes={{}}
                context={{ [previewKey]: { id: 1 } }}
            />
        );
        expect(getByText('Post Author')).not.toBeNull();
    });

    it('post-author-name shows the author name from context (#518)', () => {
        const { getByText } = render(
            <PostAuthorNameEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        author: { name: 'Jane Doe' },
                    },
                }}
            />
        );
        expect(getByText('Jane Doe')).not.toBeNull();
    });

    it('post-author-name falls back to placeholder when context has no author name (#518)', () => {
        const { getByText } = render(
            <PostAuthorNameEdit
                attributes={{}}
                context={{ [previewKey]: { id: 1 } }}
            />
        );
        expect(getByText('Author Name')).not.toBeNull();
    });

    it('post-author-biography shows the author bio from context (#518)', () => {
        const { getByText } = render(
            <PostAuthorBiographyEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        author: { bio: 'A passionate writer.' },
                    },
                }}
            />
        );
        expect(getByText('A passionate writer.')).not.toBeNull();
    });

    it('post-author-biography falls back to placeholder when context has no author bio (#518)', () => {
        const { getByText } = render(
            <PostAuthorBiographyEdit
                attributes={{}}
                context={{ [previewKey]: { id: 1, author: { name: 'Jane Doe' } } }}
            />
        );
        expect(getByText('Author Biography')).not.toBeNull();
    });

    it('avatar renders the author avatar from context (#518)', () => {
        const { container } = render(
            <AvatarEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        author: {
                            name: 'Jane Doe',
                            avatarUrl: 'https://example.test/avatar.jpg',
                        },
                    },
                }}
            />
        );
        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('https://example.test/avatar.jpg');
        expect(img?.getAttribute('alt')).toBe('Jane Doe');
    });

    it('avatar falls back to placeholder when context has no avatar URL (#518)', () => {
        const { getByText } = render(
            <AvatarEdit
                attributes={{}}
                context={{ [previewKey]: { id: 1, author: { name: 'Jane Doe' } } }}
            />
        );
        expect(getByText('Avatar')).not.toBeNull();
    });

    it('post-featured-image renders the image from context', () => {
        const { container } = render(
            <PostFeaturedImageEdit
                attributes={{}}
                context={{
                    [previewKey]: {
                        id: 1,
                        featuredImage: {
                            url: 'https://cdn.example/cover.jpg',
                            alt: 'Cover photo',
                        },
                    },
                }}
            />
        );
        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('https://cdn.example/cover.jpg');
        expect(img?.getAttribute('alt')).toBe('Cover photo');
    });

    it('post-featured-image falls back to placeholder when context has no featured image', () => {
        const { getByText } = render(
            <PostFeaturedImageEdit
                attributes={{}}
                context={{ [previewKey]: { id: 1, featuredImage: null } }}
            />
        );
        expect(getByText('Featured Image')).not.toBeNull();
    });

    it('post-author is hidden from the inserter now that the replacement blocks ship (#518)', () => {
        // Upstream `core/post-author` sets `supports.inserter: false`
        // because it's deprecated in favor of post-author-name /
        // post-author-biography / avatar. The fork dropped the flag
        // during I5 (#413) because none of the replacements were
        // forked yet. Now that all three ship via #518, the fork
        // restores the flag to match upstream's deprecation contract.
        const supports = (postAuthorMeta as { supports?: Record<string, unknown> })
            .supports ?? {};
        expect(supports.inserter).toBe(false);
    });

    it('each post-* block.json declares `artisanpack/postPreview` in usesContext', () => {
        const blocks = [
            postTitleMeta,
            postDateMeta,
            postExcerptMeta,
            postAuthorMeta,
            postAuthorNameMeta,
            postAuthorBiographyMeta,
            avatarMeta,
            postFeaturedImageMeta,
        ];

        for (const meta of blocks) {
            expect((meta as { usesContext?: string[] }).usesContext).toContain(
                'artisanpack/postPreview'
            );
        }
    });

    it('post-* blocks fall back to placeholder when used outside a query loop', () => {
        const { getByText: getTitle } = render(
            <PostTitleEdit attributes={{}} context={{}} />
        );
        expect(getTitle('Post Title')).not.toBeNull();

        const { getByText: getDate } = render(
            <PostDateEdit attributes={{}} context={{}} />
        );
        expect(getDate('Post Date')).not.toBeNull();

        const { getByText: getExcerpt } = render(
            <PostExcerptEdit attributes={{}} context={{}} />
        );
        expect(getExcerpt('Post Excerpt')).not.toBeNull();
    });
});
