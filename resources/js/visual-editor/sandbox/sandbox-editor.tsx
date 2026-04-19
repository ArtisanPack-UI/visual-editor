import { useState } from 'react';
import { createBlock, type BlockInstance } from '@wordpress/blocks';
import {
    BlockEditorProvider,
    BlockList,
    BlockTools,
    WritingFlow,
} from '@wordpress/block-editor';
import { registerCoreBlocks } from '@wordpress/block-library';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

registerCoreBlocks();

const initialBlocks: BlockInstance[] = [
    createBlock('core/paragraph', {
        content: __(
            'Hello from the Gutenberg sandbox.',
            'artisanpack-visual-editor'
        ),
    }),
];

/**
 * Minimal `BlockEditorProvider` mount used to prove `@wordpress/*` imports
 * cleanly and the Gutenberg canvas renders. M2+ replaces this with the real
 * editor shell; see docs/gutenberg-adoption.md and issue #311.
 */
export function SandboxEditor(): JSX.Element {
    const [blocks, setBlocks] = useState<BlockInstance[]>(initialBlocks);

    return (
        <SlotFillProvider>
            <BlockEditorProvider
                value={blocks}
                onInput={(next: BlockInstance[]) => setBlocks(next)}
                onChange={(next: BlockInstance[]) => setBlocks(next)}
            >
                <div className="editor-styles-wrapper">
                    <BlockTools>
                        <WritingFlow>
                            <BlockList />
                        </WritingFlow>
                    </BlockTools>
                </div>
                <Popover.Slot />
            </BlockEditorProvider>
        </SlotFillProvider>
    );
}
