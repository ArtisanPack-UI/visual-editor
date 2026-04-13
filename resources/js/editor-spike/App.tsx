import { useMemo, useState } from 'react';
import type { Block } from './mocks/blockTree';
import { BlockContextProvider } from './primitives/BlockContext';
import { BlockTreeProvider } from './primitives/BlockTreeContext';
import { BlockPreview } from './primitives/useBlockPreview';
import { RenderBlock } from './primitives/useInnerBlocksProps';
import { PARAGRAPH_BLOCK_NAME, registerParagraphBlock } from './blocks/paragraph';
import { POST_TITLE_BLOCK_NAME, registerPostTitleBlock } from './blocks/post-title';

registerParagraphBlock();
registerPostTitleBlock();

export default function App() {
    const [block] = useState<Block>(() => ({
        clientId: 'spike-paragraph-1',
        name: PARAGRAPH_BLOCK_NAME,
        attributes: {
            content: '<p>Edit me. Try <strong>Cmd+B</strong> for bold or <em>Cmd+I</em> for italic.</p>',
        },
        innerBlocks: [],
    }));

    const [postTitleA] = useState<Block>(() => ({
        clientId: 'spike-post-title-a',
        name: POST_TITLE_BLOCK_NAME,
        attributes: { level: 2 },
        innerBlocks: [],
    }));

    const [postTitleB] = useState<Block>(() => ({
        clientId: 'spike-post-title-b',
        name: POST_TITLE_BLOCK_NAME,
        attributes: { level: 2 },
        innerBlocks: [],
    }));

    const [postTitleOrphan] = useState<Block>(() => ({
        clientId: 'spike-post-title-orphan',
        name: POST_TITLE_BLOCK_NAME,
        attributes: { level: 2 },
        innerBlocks: [],
    }));

    const tree = useMemo(
        () => [block, postTitleA, postTitleB, postTitleOrphan],
        [block, postTitleA, postTitleB, postTitleOrphan]
    );

    return (
        <main className="editor-spike">
            <h1>Visual Editor Spike</h1>
            <p>Phase 0.7: post-title block consuming BlockContext.</p>

            <section>
                <h2>Edit</h2>
                <BlockTreeProvider blocks={tree}>
                    <RenderBlock block={block} />
                </BlockTreeProvider>
            </section>

            <section>
                <h2>post-title with context (postId: 1)</h2>
                <BlockTreeProvider blocks={tree}>
                    <BlockContextProvider value={{ postId: 1, postType: 'post' }}>
                        <RenderBlock block={postTitleA} />
                    </BlockContextProvider>
                </BlockTreeProvider>
            </section>

            <section>
                <h2>post-title with context (postId: 2)</h2>
                <BlockTreeProvider blocks={tree}>
                    <BlockContextProvider value={{ postId: 2, postType: 'post' }}>
                        <RenderBlock block={postTitleB} />
                    </BlockContextProvider>
                </BlockTreeProvider>
            </section>

            <section>
                <h2>post-title without context (placeholder)</h2>
                <BlockTreeProvider blocks={tree}>
                    <RenderBlock block={postTitleOrphan} />
                </BlockTreeProvider>
            </section>

            <section>
                <h2>Preview (read-only)</h2>
                <BlockPreview blocks={tree} />
            </section>
        </main>
    );
}
