/**
 * Tests for the live-entity preview path added in #481 — when an
 * `artisanpack/post-*` block is placed at the page level (outside
 * a query loop) or an `artisanpack/site-*` block is dropped on the
 * canvas, the editor reads the live page / site entity through the
 * core-data shim's `useEntityRecord` and renders its real value
 * instead of the generic placeholder.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render } from '@testing-library/react';
import { select } from '@wordpress/data';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'fallback-wrapper' }),
    // The post-title edit exposes an InspectorControls panel for the
    // isLink / linkTarget / rel attributes — stub it out as a no-op
    // wrapper so the test environment doesn't have to mount the real
    // sidebar slot.
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    // Minimal PlainText stub: renders a controlled textarea so the
    // editable post-title path can be asserted with RTL queries.
    PlainText: ({
        value,
        onChange,
        placeholder,
        tagName: _tagName,
        __experimentalVersion: _version,
        ...rest
    }: {
        value: string;
        onChange: (next: string) => void;
        placeholder?: string;
        tagName?: string;
        __experimentalVersion?: number;
        [key: string]: unknown;
    }) => {
        const className =
            typeof rest.className === 'string' ? rest.className : undefined;
        return (
            <textarea
                aria-label="Post title"
                value={value}
                placeholder={placeholder}
                className={className}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    },
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    ToggleControl: () => null,
    TextControl: () => null,
}));

import {
    SITE_ENTITY_ID,
    __resetCoreDataShimConfig,
    configureCoreDataShim,
} from '../../../vendor/core-data-shim';
import { dispatch } from '@wordpress/data';

import PostTitleEdit from '../../post-title/edit';
import PostExcerptEdit from '../../post-excerpt/edit';
import PostDateEdit from '../../post-date/edit';
import PostAuthorEdit from '../../post-author/edit';
import PostFeaturedImageEdit from '../../post-featured-image/edit';
import SiteTitleEdit from '../../site-title/edit';
import SiteTaglineEdit from '../../site-tagline/edit';
import SiteLogoEdit from '../../site-logo/edit';

const previewKey = 'artisanpack/postPreview';

type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;

async function resetStore(): Promise<void> {
    // Drain in-flight resolvers from the previous test so their
    // pending dispatches don't land mid-reset. Mirrors the cleanup
    // pattern in `core-data-shim.test.tsx`.
    configureCoreDataShim({
        apiBase: '/_drain_',
        fetcher: async () =>
            new Response(null, {
                status: 503,
                headers: { 'Content-Type': 'application/json' },
            }),
    });

    await new Promise((resolve) => setTimeout(resolve, 50));

    coreDispatch().reset();
    coreDispatch().invalidateResolutionForStore();
}

beforeEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
    // Stub the fetcher so any speculative resolver dispatch fired
    // by `useEntityRecord` during a render that we haven't pre-
    // populated doesn't hit the network. Records the test cares
    // about are seeded via `receiveEntityRecords` below.
    configureCoreDataShim({
        apiBase: '/visual-editor/api',
        fetcher: async () =>
            new Response(null, {
                status: 404,
                headers: { 'Content-Type': 'application/json' },
            }),
    });
});

afterEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
});

function seedPostEntity(
    postType: 'post' | 'page',
    id: number,
    record: Record<string, unknown>,
): void {
    // Merge the explicit `id` argument last so a future test that
    // happens to pass `id` inside `record` cannot silently override
    // the seed's primary-key slot — `receiveEntityRecords` keys by
    // the entity's `key` field (`'id'`) and a mismatched record
    // would be cached under the wrong slot.
    coreDispatch().receiveEntityRecords('postType', postType, [
        { ...record, id },
    ]);
}

function seedSiteEntity(record: Record<string, unknown>): void {
    coreDispatch().receiveEntityRecords('root', '__unstableBase', [
        { ...record, id: SITE_ENTITY_ID },
    ]);
}

describe('post-* live entity preview (#481)', () => {
    it('post-title renders an editable input bound to the live page entity title', () => {
        seedPostEntity('page', 42, { title: 'About us' });

        const { getByDisplayValue, queryByText } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        // Editable PlainText surfaces the loaded title as its value (#546).
        expect(getByDisplayValue('About us')).not.toBeNull();
        // The placeholder label must not appear when the entity resolves.
        expect(queryByText('Post Title')).toBeNull();
    });

    it('post-title accepts a `{ raw, rendered }` shaped title from the unflattened record', () => {
        seedPostEntity('page', 42, {
            title: { raw: 'Raw title', rendered: 'Rendered title' },
        });

        const { getByDisplayValue } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        // `readEntityString` prefers the `raw` form.
        expect(getByDisplayValue('Raw title')).not.toBeNull();
    });

    it('post-excerpt reads the live entity excerpt', () => {
        seedPostEntity('post', 7, { excerpt: 'A brief blurb.' });

        const { getByText } = render(
            <PostExcerptEdit
                attributes={{}}
                context={{ postId: 7, postType: 'post' }}
            />
        );

        expect(getByText('A brief blurb.')).not.toBeNull();
    });

    it('post-date reads `_preview.dateFormatted` from the live entity', () => {
        seedPostEntity('page', 42, {
            _preview: { dateFormatted: 'June 3, 2026' },
        });

        const { getByText } = render(
            <PostDateEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        expect(getByText('June 3, 2026')).not.toBeNull();
    });

    it('post-date falls back to the ISO date portion when `_preview.dateFormatted` is absent', () => {
        seedPostEntity('page', 42, { date: '2026-05-15T08:30:00+00:00' });

        const { getByText } = render(
            <PostDateEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        expect(getByText('2026-05-15')).not.toBeNull();
    });

    it('post-author reads `_preview.author.name` from the live entity', () => {
        seedPostEntity('page', 42, {
            _preview: { author: { name: 'Jane Doe' } },
        });

        const { getByText } = render(
            <PostAuthorEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        expect(getByText('Jane Doe')).not.toBeNull();
    });

    it('post-featured-image renders the image from `_preview.featuredImage` on the live entity', () => {
        seedPostEntity('page', 42, {
            _preview: {
                featuredImage: {
                    url: 'https://cdn.example/hero.jpg',
                    alt: 'Hero',
                },
            },
        });

        const { container } = render(
            <PostFeaturedImageEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('https://cdn.example/hero.jpg');
        expect(img?.getAttribute('alt')).toBe('Hero');
    });

    it('prefers the stamped `_resolved*` attribute over the live entity value', () => {
        seedPostEntity('page', 42, { title: 'Live entity title' });

        const { getByText, queryByText } = render(
            <PostTitleEdit
                attributes={{ _resolvedTitle: 'Stamped title' }}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        expect(getByText('Stamped title')).not.toBeNull();
        expect(queryByText('Live entity title')).toBeNull();
    });

    it('prefers the query-preview context over the live entity value', () => {
        seedPostEntity('page', 42, { title: 'Live entity title' });

        const { getByText, queryByText } = render(
            <PostTitleEdit
                attributes={{}}
                context={{
                    postId: 42,
                    postType: 'page',
                    [previewKey]: { id: 99, title: 'Query preview title' },
                }}
            />
        );

        expect(getByText('Query preview title')).not.toBeNull();
        expect(queryByText('Live entity title')).toBeNull();
    });

    it('falls back to the placeholder when no entity record has resolved', () => {
        // No `receiveEntityRecords` — the fetcher 404s and the record
        // stays null.
        const { getByText } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ postId: 42, postType: 'page' }}
            />
        );

        expect(getByText('Post Title')).not.toBeNull();
    });

    it('falls back to the placeholder when `postId`/`postType` block context is absent', () => {
        seedPostEntity('page', 42, { title: 'About us' });

        const { getByText } = render(
            <PostTitleEdit attributes={{}} context={{}} />
        );

        expect(getByText('Post Title')).not.toBeNull();
    });

    it('rejects malformed `postId` values (non-numeric strings) instead of crashing', () => {
        const { getByText } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ postId: 'abc', postType: 'page' }}
            />
        );

        expect(getByText('Post Title')).not.toBeNull();
    });

    it('accepts a numeric-string `postId` from block context', () => {
        seedPostEntity('page', 42, { title: 'About us' });

        const { getByDisplayValue } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ postId: '42', postType: 'page' }}
            />
        );

        expect(getByDisplayValue('About us')).not.toBeNull();
    });

    it('post-title typing updates the displayed value and stages an entity edit (#546)', () => {
        seedPostEntity('post', 1, { title: 'Hello' });

        const { getByDisplayValue } = render(
            <PostTitleEdit
                attributes={{}}
                context={{ postId: 1, postType: 'post' }}
            />
        );

        const textarea = getByDisplayValue('Hello') as HTMLTextAreaElement;

        act(() => {
            fireEvent.change(textarea, { target: { value: 'Hello, world' } });
        });

        // The mounted consumer must reflect the new value on re-render
        // — this is the regression #546 addresses.
        expect(
            (getByDisplayValue('Hello, world') as HTMLTextAreaElement).value
        ).toBe('Hello, world');

        // And the typed value must round-trip through `editEntityRecord`
        // so the metadata-save loop picks it up.
        const coreStore = select('core') as {
            getEntityRecordEdits: (
                kind: string,
                name: string,
                id: number
            ) => Record<string, unknown> | null;
        };
        expect(coreStore.getEntityRecordEdits('postType', 'post', 1)).toEqual({
            title: 'Hello, world',
        });
    });

    it('renders a readonly preview (not editable) when the block is inside a query loop (#546)', () => {
        seedPostEntity('page', 42, { title: 'About us' });

        const { container } = render(
            <PostTitleEdit
                attributes={{}}
                context={{
                    postId: 42,
                    postType: 'page',
                    queryId: 3,
                    [previewKey]: { id: 99, title: 'Query preview title' },
                }}
            />
        );

        // Query-loop preview wins over the editable live-entity path and
        // renders as a non-editable element.
        expect(container.querySelector('textarea')).toBeNull();
        expect(container.textContent).toContain('Query preview title');
    });
});

describe('site-* live entity preview (#481)', () => {
    it('site-title reads the live site title from the shim', () => {
        seedSiteEntity({ title: 'ArtisanPack Studios' });

        const { getByText, queryByText } = render(
            <SiteTitleEdit attributes={{}} />
        );

        expect(getByText('ArtisanPack Studios')).not.toBeNull();
        expect(queryByText('Site Title')).toBeNull();
    });

    it('site-title accepts the `{ raw, rendered }` site title shape', () => {
        seedSiteEntity({
            title: { raw: 'Raw site title', rendered: 'Rendered site title' },
        });

        const { getByText } = render(<SiteTitleEdit attributes={{}} />);

        expect(getByText('Raw site title')).not.toBeNull();
    });

    it('site-tagline reads the live site description', () => {
        seedSiteEntity({ description: 'Crafting beautiful interfaces.' });

        const { getByText } = render(<SiteTaglineEdit attributes={{}} />);

        expect(getByText('Crafting beautiful interfaces.')).not.toBeNull();
    });

    it('site-logo renders the live `logoUrl` with the site title as alt text', () => {
        seedSiteEntity({
            title: 'ArtisanPack',
            logoUrl: 'https://cdn.example/logo.png',
        });

        const { container } = render(<SiteLogoEdit attributes={{}} />);

        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('https://cdn.example/logo.png');
        expect(img?.getAttribute('alt')).toBe('ArtisanPack');
    });

    it('site-logo falls back to the placeholder when `logoUrl` is empty', () => {
        seedSiteEntity({ title: 'ArtisanPack', logoUrl: '' });

        const { getByText } = render(<SiteLogoEdit attributes={{}} />);

        expect(getByText('Site Logo')).not.toBeNull();
    });

    it('prefers the stamped `_resolvedSiteTitle` attribute over the live entity', () => {
        seedSiteEntity({ title: 'Live site title' });

        const { getByText, queryByText } = render(
            <SiteTitleEdit
                attributes={{ _resolvedSiteTitle: 'Stamped site title' }}
            />
        );

        expect(getByText('Stamped site title')).not.toBeNull();
        expect(queryByText('Live site title')).toBeNull();
    });

    it('falls back to the placeholder when the site entity has no title', () => {
        seedSiteEntity({ title: '' });

        const { getByText } = render(<SiteTitleEdit attributes={{}} />);

        expect(getByText('Site Title')).not.toBeNull();
    });
});
