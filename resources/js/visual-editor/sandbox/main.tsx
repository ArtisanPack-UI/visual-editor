import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

/**
 * M1 sandbox entry (#311). Dynamically imports the Gutenberg-using module so
 * `@wordpress/*` lands in a dedicated `gutenberg` chunk (see vite.config.ts)
 * that only downloads when the editor mounts.
 */

const MOUNT_ID = 've-sandbox-root';

async function bootSandbox(): Promise<void> {
    const container = document.getElementById(MOUNT_ID);

    if (!container) {
        console.error(
            `visual-editor sandbox: missing #${MOUNT_ID} mount point. Render the temporary /ve-sandbox Blade route or include a <div id="${MOUNT_ID}">.`
        );
        return;
    }

    const { SandboxEditor } = await import('./sandbox-editor');

    createRoot(container).render(createElement(SandboxEditor));
}

void bootSandbox();
