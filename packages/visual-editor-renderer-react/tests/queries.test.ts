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
    it('expands core/query into one post-template instance per result', () => {
        const tree = [
            makeQueryBlock([
                { name: 'core/post-title', attributes: {}, innerBlocks: [] } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            { queryId: 'q1', posts: [fakePost(1, 'A'), fakePost(2, 'B')] },
        ];

        const result = inlineQueries(tree, { queries });
        const innerBlocks = result[0].innerBlocks ?? [];

        expect(innerBlocks).toHaveLength(2);
        expect(
            (innerBlocks[0].innerBlocks?.[0].attributes as Record<string, unknown>)?._resolvedTitle
        ).toBe('A');
        expect(
            (innerBlocks[1].innerBlocks?.[0].attributes as Record<string, unknown>)?._resolvedTitle
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
        const stamped = result[0].innerBlocks?.[0].innerBlocks ?? [];

        expect((stamped[0].attributes as Record<string, unknown>)._resolvedTitle).toBe('Post');
        expect((stamped[1].attributes as Record<string, unknown>)._resolvedExcerpt).toBe('Excerpt');
        expect((stamped[2].attributes as Record<string, unknown>)._resolvedAuthorName).toBe(
            'Jane Doe'
        );
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
        const stamped = innerQuery?.innerBlocks?.[0]?.innerBlocks?.[0];

        expect(innerQuery?.name).toBe('core/query');
        expect((stamped?.attributes as Record<string, unknown>)?._resolvedTitle).toBe('Inner');
    });

    it('passes through pre-existing _resolved attrs without overwriting', () => {
        const tree = [
            makeQueryBlock([
                {
                    name: 'core/post-title',
                    attributes: { _resolvedTitle: 'Host override' },
                    innerBlocks: [],
                } as Block,
            ]),
        ];

        const queries: ResolvedQuery[] = [
            { queryId: 'q1', posts: [fakePost(1, 'Resolver value')] },
        ];

        const result = inlineQueries(tree, { queries });
        const stamped = result[0].innerBlocks?.[0].innerBlocks?.[0];

        expect((stamped?.attributes as Record<string, unknown>)?._resolvedTitle).toBe(
            'Host override'
        );
    });
});
