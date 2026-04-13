import { useCallback, useMemo, useState } from 'react';
import type { BlockEditProps } from '../../registry';
import { BlockContextProvider } from '../../primitives/BlockContext';
import { BlockPreview } from '../../primitives/useBlockPreview';
import { InnerBlocks } from '../../primitives/useInnerBlocksProps';
import { fetchQueryResults, type Query } from '../../mocks/mockApi';

const DEFAULT_QUERY: Query = { postType: 'post', perPage: 5 };

function readQuery(attributes: Record<string, unknown>): Query {
    const postType =
        typeof attributes.postType === 'string' ? attributes.postType : DEFAULT_QUERY.postType;
    const perPage =
        typeof attributes.perPage === 'number' ? attributes.perPage : DEFAULT_QUERY.perPage;

    return { postType, perPage };
}

export default function QueryLoopEdit({ clientId, attributes, block }: BlockEditProps) {
    const query = useMemo(() => readQuery(attributes), [attributes]);
    const posts = useMemo(() => fetchQueryResults(query), [query]);

    const [activePostId, setActivePostId] = useState<number | null>(
        () => posts[0]?.id ?? null
    );
    const [templateVersion, setTemplateVersion] = useState(0);

    const bumpTemplate = useCallback(() => {
        setTemplateVersion((v) => v + 1);
    }, []);

    return (
        <div
            data-client-id={clientId}
            data-block-name="ve/query-loop"
            className="ve-query-loop"
        >
            {posts.map((post) => {
                const isActive = post.id === activePostId;

                return (
                    <BlockContextProvider
                        key={post.id}
                        value={{
                            postId: post.id,
                            postType: post.type,
                            __bumpTemplate: bumpTemplate,
                        }}
                    >
                        <article
                            className={`ve-query-loop__post${
                                isActive ? ' ve-query-loop__post--active' : ''
                            }`}
                            data-post-id={post.id}
                            data-active={isActive ? 'true' : 'false'}
                        >
                            {isActive ? (
                                <InnerBlocks
                                    parentClientId={clientId}
                                    className="ve-query-loop__content"
                                />
                            ) : (
                                <>
                                    <BlockPreview
                                        key={templateVersion}
                                        blocks={block.innerBlocks}
                                        className="ve-query-loop__content ve-query-loop__content--preview"
                                    />
                                    <button
                                        type="button"
                                        className="ve-query-loop__activate"
                                        onClick={() => setActivePostId(post.id)}
                                    >
                                        Edit this post
                                    </button>
                                </>
                            )}
                        </article>
                    </BlockContextProvider>
                );
            })}
        </div>
    );
}
