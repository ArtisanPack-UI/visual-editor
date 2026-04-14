import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { EditorShell } from './components';
import './components/editor.css';
import { registerBlock, type BlockEditProps } from './registry';
import { createEditorStore, type Block, type EditorStore } from './store';

const MOUNT_ID = 've-root';

type EditorConfig = {
    postId: string;
    postType: string;
    apiBase: string;
};

function readEditorConfig(container: HTMLElement): EditorConfig | null {
    const postId = container.dataset.postId?.trim();
    const postType = container.dataset.postType?.trim();
    const apiBase = container.dataset.apiBase?.trim();

    if (!postId || !postType || !apiBase) {
        return null;
    }

    return { postId, postType, apiBase };
}

function PlaceholderEdit({ attributes }: BlockEditProps) {
    const label =
        typeof attributes.label === 'string' ? attributes.label : 'Placeholder block';

    return <p className="ve-placeholder-block">{label}</p>;
}

function registerPlaceholderBlock(): void {
    registerBlock({ name: 've/placeholder', edit: PlaceholderEdit });
}

function createPlaceholderStore(): EditorStore {
    const placeholders: Block[] = [
        {
            clientId: 'placeholder-1',
            name: 've/placeholder',
            attributes: { label: 'First placeholder block' },
            innerBlocks: [],
        },
        {
            clientId: 'placeholder-2',
            name: 've/placeholder',
            attributes: { label: 'Second placeholder block' },
            innerBlocks: [],
        },
    ];

    return createEditorStore(placeholders);
}

function bootEditor(): void {
    const container = document.getElementById(MOUNT_ID);

    if (!container) {
        console.error(
            `visual-editor: missing #${MOUNT_ID} mount point. Include resources/views/editor/mount.blade.php or render a <div id="${MOUNT_ID}"> with data-post-id, data-post-type, and data-api-base.`
        );
        return;
    }

    const config = readEditorConfig(container);

    if (!config) {
        console.error(
            `visual-editor: #${MOUNT_ID} is missing required data attributes. Expected data-post-id, data-post-type, and data-api-base.`
        );
        return;
    }

    registerPlaceholderBlock();

    const store = createPlaceholderStore();

    createRoot(container).render(
        <StrictMode>
            <EditorShell store={store} />
        </StrictMode>
    );

    // Config is read for future wiring (post loader, API client) but unused for now.
    void config;
}

bootEditor();
