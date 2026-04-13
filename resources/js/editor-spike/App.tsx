import { useMemo, useState } from 'react';
import type { Block } from './mocks/blockTree';
import { BlockTreeProvider } from './primitives/BlockTreeContext';
import { BlockPreview } from './primitives/useBlockPreview';
import { RenderBlock } from './primitives/useInnerBlocksProps';
import { PARAGRAPH_BLOCK_NAME, registerParagraphBlock } from './blocks/paragraph';

registerParagraphBlock();

export default function App() {
    const [block] = useState<Block>(() => ({
        clientId: 'spike-paragraph-1',
        name: PARAGRAPH_BLOCK_NAME,
        attributes: {
            content: '<p>Edit me. Try <strong>Cmd+B</strong> for bold or <em>Cmd+I</em> for italic.</p>',
        },
        innerBlocks: [],
    }));

    const tree = useMemo(() => [block], [block]);

    return (
        <main className="editor-spike">
            <h1>Visual Editor Spike</h1>
            <p>Phase 0.6: Tiptap + paragraph block edit module.</p>

            <section>
                <h2>Edit</h2>
                <BlockTreeProvider blocks={tree}>
                    <RenderBlock block={block} />
                </BlockTreeProvider>
            </section>

            <section>
                <h2>Preview (read-only)</h2>
                <BlockPreview blocks={tree} />
            </section>
        </main>
    );
}
