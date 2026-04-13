import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

const MOUNT_ID = 'visual-editor-root';

function EditorPlaceholder() {
    return (
        <div className="ve-editor-placeholder">
            <h1>ArtisanPack Visual Editor</h1>
            <p>Phase 1.1 placeholder. The real editor root lands in later issues.</p>
        </div>
    );
}

const container = document.getElementById(MOUNT_ID);

if (!container) {
    throw new Error(`visual-editor: missing #${MOUNT_ID} mount point`);
}

createRoot(container).render(
    <StrictMode>
        <EditorPlaceholder />
    </StrictMode>
);
