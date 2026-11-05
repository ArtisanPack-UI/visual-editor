import { describe, expect, it } from 'vitest';
import {
    QUERY_RESOLUTION_ERROR_NO_RESULTS,
    inlineQueries,
} from '../src/queries';
import type { ResolvedPost, ResolvedQuery } from '../src/queries';
import type { Block } from '../src/types';

function makeQueryBlock(template: Block[], queryId: string | number = 'q1'): Block {
    return {
        clientId: 'qry',
        name: 'core/query',
        attributes: { query: { queryId, postType: 'post' } },
        innerBlocks: [
            {
                clientId: 'tpl',
                name: 'core/post-template',
                attributes: {},
                innerBlocks: template,
            },
        ],
    };
}

function fakePost(id: number, title: string): ResolvedPost {
    return {
        id,
        title,
        permalink: `/posts/${id}`,
    };
}

describe('inlineQueries', () => {
    it('expands core/query into one post-template wrapping N post-template-item blocks', () => {
        const tree = [
            makeQueryBlock([
                { name: 'core/post-title', attributes: {}, innerBlocks: [] } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            { queryId: 'q1', posts: [fakePost(1, 'A'), fakePost(2, 'B')] },
        ];

        const result = inlineQueries(tree, { queries });
        const queryInner = result[0].innerBlocks ?? [];
        const postTemplate = queryInner[0];
        const items = postTemplate.innerBlocks ?? [];

        expect(queryInner).toHaveLength(1);
        expect(postTemplate.name).toBe('core/post-template');
        expect(items).toHaveLength(2);
        expect(items[0].name).toBe('core/post-template-item');
        expect(items[1].name).toBe('core/post-template-item');
        expect(
            (items[0].innerBlocks?.[0].attributes as Record<string, unknown>)?._resolvedTitle
        ).toBe('A');
        expect(
            (items[1].innerBlocks?.[0].attributes as Record<string, unknown>)?._resolvedTitle
        ).toBe('B');
    });

    it('marks core/query with _resolutionError when no resolved set is supplied', () => {
        const tree = [makeQueryBlock([])];

        const result = inlineQueries(tree, { queries: [] });

        expect((result[0].attributes as Record<string, unknown>)._resolutionError).toBe(
            QUERY_RESOLUTION_ERROR_NO_RESULTS
        );
        expect(result[0].innerBlocks).toEqual([]);
    });

    it('stamps post-context attributes for the supported core/post-* blocks', () => {
        const tree = [
            makeQueryBlock([
                { name: 'core/post-title', attributes: {}, innerBlocks: [] } as Block,
                { name: 'core/post-excerpt', attributes: {}, innerBlocks: [] } as Block,
                { name: 'core/post-author', attributes: {}, innerBlocks: [] } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            {
                queryId: 'q1',
                posts: [
                    {
                        id: 1,
                        title: 'Post',
                        excerpt: 'Excerpt',
                        permalink: '/posts/1',
                        author: { name: 'Jane Doe' },
                    },
                ],
            },
        ];

        const result = inlineQueries(tree, { queries });
        const stamped = result[0].innerBlocks?.[0].innerBlocks?.[0].innerBlocks ?? [];

        expect((stamped[0].attributes as Record<string, unknown>)._resolvedTitle).toBe('Post');
        expect((stamped[1].attributes as Record<string, unknown>)._resolvedExcerpt).toBe('Excerpt');
        expect((stamped[2].attributes as Record<string, unknown>)._resolvedAuthorName).toBe(
            'Jane Doe'
        );
    });

    it('formats core/post-date with the block format attribute', () => {
        const tree = [
            makeQueryBlock([
                { name: 'core/post-date', attributes: {}, innerBlocks: [] } as Block,
                { name: 'core/post-date', attributes: { format: 'M j' }, innerBlocks: [] } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            {
                queryId: 'q1',
                posts: [
                    {
                        id: 1,
                        title: 'Post',
                        permalink: '/posts/1',
                        publishedAt: '2026-10-02T13:00:00+00:00',
                    },
                ],
            },
        ];

        const result = inlineQueries(tree, { queries });
        const stamped = result[0].innerBlocks?.[0].innerBlocks?.[0].innerBlocks ?? [];

        expect((stamped[0].attributes as Record<string, unknown>)._resolvedDateFormatted).toBe(
            'October 2, 2026'
        );
        expect((stamped[1].attributes as Record<string, unknown>)._resolvedDateFormatted).toBe('Oct 2');
    });

    it('stamps the featured image on a cover set to use it (#942)', () => {
        const tree = [
            makeQueryBlock([
                { name: 'artisanpack/cover', attributes: { useFeaturedImage: true }, innerBlocks: [] } as Block,
                { name: 'artisanpack/cover', attributes: {}, innerBlocks: [] } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            {
                queryId: 'q1',
                posts: [
                    {
                        id: 1,
                        title: 'Post',
                        permalink: '/posts/1',
                        featuredImage: { url: 'https://example.test/featured.jpg', alt: 'Alt' },
                    },
                ],
            },
        ];

        const result = inlineQueries(tree, { queries });
        const stamped = result[0].innerBlocks?.[0].innerBlocks?.[0].innerBlocks ?? [];

        expect(stamped[0].attributes).toMatchObject({
            _resolvedImageUrl: 'https://example.test/featured.jpg',
            _resolvedImageAlt: 'Alt',
        });
        expect(stamped[1].attributes).not.toHaveProperty('_resolvedImageUrl');
    });

    it('recurses into nested queries and groups', () => {
        const tree = [
            {
                name: 'core/group',
                attributes: {},
                innerBlocks: [
                    makeQueryBlock(
                        [
                            { name: 'core/post-title', attributes: {}, innerBlocks: [] } as Block,
                        ],
                        'inner'
                    ),
                ],
            },
        ];

        const queries: ResolvedQuery[] = [
            { queryId: 'inner', posts: [fakePost(1, 'Inner')] },
        ];

        const result = inlineQueries(tree as Block[], { queries });
        const innerQuery = result[0].innerBlocks?.[0];
        const stamped = innerQuery?.innerBlocks?.[0]?.innerBlocks?.[0]?.innerBlocks?.[0];

        expect(innerQuery?.name).toBe('core/query');
        expect((stamped?.attributes as Record<string, unknown>)?._resolvedTitle).toBe('Inner');
    });

    it('stamps the result over a _resolved attr saved in the template (#1078)', () => {
        const tree = [
            makeQueryBlock([
                {
                    name: 'core/post-title',
                    attributes: { _resolvedTitle: 'Saved value' },
                    innerBlocks: [],
                } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            { queryId: 'q1', posts: [fakePost(1, 'Resolver value')] },
        ];

        const result = inlineQueries(tree, { queries });
        const stamped = result[0].innerBlocks?.[0].innerBlocks?.[0].innerBlocks?.[0];

        expect((stamped?.attributes as Record<string, unknown>)?._resolvedTitle).toBe(
            'Resolver value'
        );
    });
});

describe('inlineQueries saved _resolved* attributes (#1078)', () => {
    it('stamps each result over values saved in the template', () => {
        const tree = [
            makeQueryBlock([
                {
                    name: 'core/post-title',
                    attributes: {
                        level: 2,
                        _resolvedTitle: 'Spoofed',
                        _resolvedPermalink: 'https://evil.test/',
                    },
                    innerBlocks: [],
                } as Block,
                {
                    name: 'core/cover',
                    attributes: { useFeaturedImage: true, _resolvedImageUrl: 'https://evil.test/x.png' },
                    innerBlocks: [],
                } as Block,
            ]),
        ];

        const result = inlineQueries(tree, {
            queries: [{ queryId: 'q1', posts: [fakePost(1, 'A'), fakePost(2, 'B')] }],
        });
        const items = result[0].innerBlocks?.[0].innerBlocks ?? [];
        const title = (index: number) =>
            items[index].innerBlocks?.[0].attributes as Record<string, unknown>;
        const cover = items[0].innerBlocks?.[1].attributes as Record<string, unknown>;

        expect(title(0)).toMatchObject({ level: 2, _resolvedTitle: 'A', _resolvedPermalink: '/posts/1' });
        expect(title(1)).toMatchObject({ _resolvedTitle: 'B', _resolvedPermalink: '/posts/2' });
        expect(cover._resolvedImageUrl).toBe('');
    });

    it('drops saved links on blocks it does not stamp itself', () => {
        const tree = [
            makeQueryBlock([
                {
                    name: 'artisanpack/social-share-content',
                    attributes: {
                        socialIcons: ['facebook'],
                        _resolvedShareLinks: [{ slug: 'facebook', url: 'https://evil.test/' }],
                    },
                    innerBlocks: [],
                } as Block,
                {
                    name: 'artisanpack/post-title',
                    attributes: { _resolvedPermalink: 'https://evil.test/' },
                    innerBlocks: [],
                } as Block,
                {
                    name: 'core/paragraph',
                    attributes: { _resolvedSomething: 'kept' },
                    innerBlocks: [],
                } as Block,
            ]),
        ];

        const result = inlineQueries(tree, {
            queries: [{ queryId: 'q1', posts: [fakePost(1, 'A')] }],
        });
        const children = result[0].innerBlocks?.[0].innerBlocks?.[0].innerBlocks ?? [];
        const attrs = (index: number) => children[index].attributes as Record<string, unknown>;

        expect(attrs(0)).toEqual({ socialIcons: ['facebook'] });
        expect(attrs(1)).toEqual({});
        expect(attrs(2)).toEqual({ _resolvedSomething: 'kept' });
    });
});
