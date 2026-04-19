/**
 * Visual editor React app.
 *
 * Mounts a `BlockEditorProvider` for a single resource+id pair and wires the
 * persistence loop so keystrokes hit Laravel via debounced PUTs. This is the
 * M3 entry point (#313) — a minimal shell sufficient to prove the end-to-end
 * persistence path. The full editor chrome (three-panel layout, inserter,
 * block toolbar) already exists in the sandbox and will consolidate with
 * this app in a later milestone.
 */

import {
    BlockEditorProvider,
    BlockList,
    BlockTools,
    WritingFlow,
} from '@wordpress/block-editor';
import { registerCoreBlocks } from '@wordpress/block-library';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { bootI18n, TEXT_DOMAIN } from '../vendor/i18n';
import {
    ensureMediaBridgeFilter,
    mediaUploadSetting,
} from '../media-bridge';

import { usePersistence } from './use-persistence';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

let blocksRegistered = false;

function registerOnce(): void {
    if (blocksRegistered) {
        return;
    }

    bootI18n();
    ensureMediaBridgeFilter();
    registerCoreBlocks();
    blocksRegistered = true;
}

const editorSettings = {
    mediaUpload: mediaUploadSetting,
};

export interface EditorAppProps {
    apiBase: string;
    resource: string;
    id: string;
}

export function EditorApp(props: EditorAppProps): JSX.Element {
    registerOnce();

    const {
        blocks,
        loadStatus,
        saveStatus,
        loadError,
        saveError,
        onBlocksChange,
    } = usePersistence(props);

    if (loadStatus === 'loading') {
        return (
            <p className="ap-visual-editor__status ap-visual-editor__status--loading">
                {__('Loading content…', TEXT_DOMAIN)}
            </p>
        );
    }

    if (loadStatus === 'error') {
        return (
            <p className="ap-visual-editor__status ap-visual-editor__status--error">
                {loadError?.message ??
                    __('Unable to load content.', TEXT_DOMAIN)}
            </p>
        );
    }

    return (
        <SlotFillProvider>
            <div className="ap-visual-editor__shell">
                <BlockEditorProvider
                    value={blocks}
                    settings={editorSettings}
                    onInput={(next: BlockInstance[]) => onBlocksChange(next)}
                    onChange={(next: BlockInstance[]) => onBlocksChange(next)}
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
                <p
                    className="ap-visual-editor__save-status"
                    data-save-status={saveStatus}
                    role="status"
                    aria-live="polite"
                >
                    {saveStatusLabel(saveStatus, saveError?.message)}
                </p>
            </div>
        </SlotFillProvider>
    );
}

function saveStatusLabel(
    status: 'idle' | 'saving' | 'saved' | 'error',
    errorMessage?: string
): string {
    switch (status) {
        case 'saving':
            return __('Saving…', TEXT_DOMAIN);
        case 'saved':
            return __('All changes saved.', TEXT_DOMAIN);
        case 'error':
            return errorMessage ?? __('Save failed.', TEXT_DOMAIN);
        case 'idle':
        default:
            return '';
    }
}
