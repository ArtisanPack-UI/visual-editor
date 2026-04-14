import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { EditorShell } from './components';
import './components/editor.css';
import { registerBlock, type BlockEditProps } from './registry';
import { registerCoreBlocks, PARAGRAPH_BLOCK_NAME, HEADING_BLOCK_NAME } from './blocks';
import { loadInserterBlocks } from './inserter';
import { createEditorStore, createClientId, type Block, type EditorStore } from './store';

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

function createInitialStore(): EditorStore {
    const initialBlocks: Block[] = [
        {
            clientId: createClientId(),
            name: HEADING_BLOCK_NAME,
            attributes: { level: 2, content: '<h2>Heading</h2>' },
            innerBlocks: [],
        },
        {
            clientId: createClientId(),
            name: PARAGRAPH_BLOCK_NAME,
            attributes: { content: '<p>Start writing…</p>' },
            innerBlocks: [],
        },
    ];

    return createEditorStore(initialBlocks);
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
    registerCoreBlocks();

    void loadInserterBlocks({ apiBase: config.apiBase });

    const store = createInitialStore();

    createRoot(container).render(
        <StrictMode>
            <EditorShell store={store} />
        </StrictMode>
    );

}

bootEditor();
