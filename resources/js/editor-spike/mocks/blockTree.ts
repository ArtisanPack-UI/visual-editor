export type Block = {
    clientId: string;
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: Block[];
};

export const blockTree: Block[] = [
    {
        clientId: 'block-query-loop-1',
        name: 've/query-loop',
        attributes: {
            postType: 'post',
            perPage: 5,
        },
        innerBlocks: [
            {
                clientId: 'block-post-title-1',
                name: 've/post-title',
                attributes: {
                    level: 2,
                },
                innerBlocks: [],
            },
            {
                clientId: 'block-paragraph-1',
                name: 've/paragraph',
                attributes: {
                    field: 'excerpt',
                },
                innerBlocks: [],
            },
        ],
    },
];
