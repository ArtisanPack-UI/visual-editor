export type Post = {
    id: number;
    type: string;
    title: string;
    excerpt: string;
    featuredImage: string;
};

export const posts: Post[] = [
    {
        id: 1,
        type: 'post',
        title: 'Lorem ipsum dolor sit amet',
        excerpt:
            'Consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum cras venenatis euismod malesuada.',
        featuredImage: 'https://picsum.photos/seed/post-1/800/450',
    },
    {
        id: 2,
        type: 'post',
        title: 'Sed do eiusmod tempor incididunt',
        excerpt:
            'Ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi.',
        featuredImage: 'https://picsum.photos/seed/post-2/800/450',
    },
    {
        id: 3,
        type: 'post',
        title: 'Duis aute irure dolor in reprehenderit',
        excerpt:
            'In voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident.',
        featuredImage: 'https://picsum.photos/seed/post-3/800/450',
    },
    {
        id: 4,
        type: 'post',
        title: 'Curabitur pretium tincidunt lacus',
        excerpt:
            'Nulla gravida orci a odio. Nullam varius, turpis et commodo pharetra, est eros bibendum elit, nec luctus magna.',
        featuredImage: 'https://picsum.photos/seed/post-4/800/450',
    },
    {
        id: 5,
        type: 'post',
        title: 'Praesent dapibus, neque id cursus faucibus',
        excerpt:
            'Tortor neque egestas augue, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis.',
        featuredImage: 'https://picsum.photos/seed/post-5/800/450',
    },
];
