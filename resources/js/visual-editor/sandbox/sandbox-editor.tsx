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

import { bootI18n, TEXT_DOMAIN } from '../vendor/i18n';
import {
    ensureMediaBridgeFilter,
    mediaUploadSetting,
} from '../media-bridge';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

bootI18n();
ensureMediaBridgeFilter();
registerCoreBlocks();

const initialBlocks: BlockInstance[] = [
    createBlock('core/paragraph', {
        content: __('Hello from the Gutenberg sandbox.', TEXT_DOMAIN),
    }),
    // Sanity-check for the M2 core-data shim: the image block's placeholder
    // renders against the (empty) `core` store without crashing. See #312.
    createBlock('core/image', {}),
];

// `MediaUploadCheck` (in @wordpress/block-editor) hides the "Media Library"
// button when `settings.mediaUpload` is falsy. The bridge's settings
// callback keeps the button in place; the editor.MediaUpload filter
// intercepts the click to open the host-registered picker. See #314.
const sandboxSettings = {
    mediaUpload: mediaUploadSetting,
};

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
                settings={sandboxSettings}
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
