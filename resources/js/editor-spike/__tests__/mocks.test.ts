import { blockTree } from '../mocks/blockTree';
import { fetchPostField, fetchQueryResults } from '../mocks/mockApi';
import { posts } from '../mocks/posts';

describe('mock data layer', () => {
    it('exports 5 mock posts with the expected shape', () => {
        expect(posts).toHaveLength(5);

        posts.forEach((post) => {
            expect(post).toEqual(
                expect.objectContaining({
                    id: expect.any(Number),
                    type: expect.any(String),
                    title: expect.any(String),
                    excerpt: expect.any(String),
                    featuredImage: expect.any(String),
                })
            );
        });
    });

    it('returns mock posts from fetchQueryResults', () => {
        expect(fetchQueryResults()).toEqual(posts);
        expect(fetchQueryResults({ postType: 'post' })).toHaveLength(5);
        expect(fetchQueryResults({ perPage: 2 })).toHaveLength(2);
        expect(fetchQueryResults({ postType: 'page' })).toEqual([]);
    });

    it('looks up fields on a mock post', () => {
        expect(fetchPostField('post', 1, 'title')).toBe(posts[0].title);
        expect(fetchPostField('post', 3, 'excerpt')).toBe(posts[2].excerpt);
        expect(fetchPostField('post', 999, 'title')).toBe('');
    });

    it('exports a query-loop block tree with two child blocks', () => {
        expect(blockTree).toHaveLength(1);

        const queryLoop = blockTree[0];
        expect(queryLoop.name).toBe('ve/query-loop');
        expect(queryLoop.innerBlocks).toHaveLength(2);
        expect(queryLoop.innerBlocks.map((b) => b.name)).toEqual([
            've/post-title',
            've/paragraph',
        ]);
    });
});
