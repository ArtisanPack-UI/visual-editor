import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

const MOUNT_ID = 've-root';

type EditorConfig = {
    postId: string;
    postType: string;
    apiBase: string;
};

type EditorShellProps = EditorConfig;

function EditorShell({ postId, postType, apiBase }: EditorShellProps) {
    return (
        <div className="ve-editor-placeholder">
            <h1>ArtisanPack Visual Editor</h1>
            <p>Placeholder shell — the real editor lands in issue #270.</p>
            <dl>
                <dt>postId</dt>
                <dd>{postId}</dd>
                <dt>postType</dt>
                <dd>{postType}</dd>
                <dt>apiBase</dt>
                <dd>{apiBase}</dd>
            </dl>
        </div>
    );
}

function readEditorConfig(container: HTMLElement): EditorConfig | null {
    const { postId, postType, apiBase } = container.dataset;

    if (!postId || !postType || !apiBase) {
        return null;
    }

    return { postId, postType, apiBase };
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

    createRoot(container).render(
        <StrictMode>
            <EditorShell {...config} />
        </StrictMode>
    );
}

bootEditor();
