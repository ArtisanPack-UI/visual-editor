import { useState } from 'react';
import { blockTree, type Block } from './mocks/blockTree';
import { BlockTreeProvider } from './primitives/BlockTreeContext';
import { RenderBlock } from './primitives/useInnerBlocksProps';
import { registerParagraphBlock } from './blocks/paragraph';
import { registerPostTitleBlock } from './blocks/post-title';
import { registerQueryLoopBlock } from './blocks/query-loop';

registerParagraphBlock();
registerPostTitleBlock();
registerQueryLoopBlock();

function cloneBlocks(blocks: Block[]): Block[] {
    return blocks.map((block) => ({
        ...block,
        attributes: { ...block.attributes },
        innerBlocks: cloneBlocks(block.innerBlocks),
    }));
}

export default function App() {
    const [tree] = useState<Block[]>(() => cloneBlocks(blockTree));
    const root = tree[0];

    return (
        <main className="editor-spike">
            <h1>Visual Editor Spike</h1>
            <p>
                Phase 0.8: query-loop block with active/inactive post switching. Edit the active
                post below; the same change should appear in every inactive preview because they
                all share the same template.
            </p>

            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={root} />
            </BlockTreeProvider>
        </main>
    );
}
