import { Post, posts } from './posts';

export type Query = {
    postType?: string;
    perPage?: number;
};

export function fetchQueryResults(query: Query = {}): Post[] {
    const filtered = query.postType
        ? posts.filter((post) => post.type === query.postType)
        : posts;

    if (typeof query.perPage === 'number') {
        return filtered.slice(0, query.perPage);
    }

    return filtered;
}

export function fetchPostField(
    postType: string,
    postId: number,
    field: keyof Post
): string {
    const post = posts.find((p) => p.type === postType && p.id === postId);

    if (!post) {
        return '';
    }

    return String(post[field] ?? '');
}
