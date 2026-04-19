/**
 * Visual editor bootstrap entry.
 *
 * Scans the DOM for every `[data-ap-visual-editor]` mount point rendered by
 * the `<x-visual-editor>` Blade component and attaches a React root to each.
 * Dynamically imports the editor app so the `@wordpress/*` bundle lands in a
 * dedicated `gutenberg` chunk (see vite.config.ts) that only downloads when
 * at least one editor is present on the page.
 */

import { StrictMode, createElement } from 'react';
import { createRoot, type Root } from 'react-dom/client';

const MOUNT_SELECTOR = '[data-ap-visual-editor]';
const ROOT_SYMBOL: unique symbol = Symbol('ap-visual-editor-root');

type MountableElement = HTMLElement & {
    [ROOT_SYMBOL]?: Root;
};

interface MountConfig {
    apiBase: string;
    resource: string;
    id: string;
}

function readMountConfig(element: HTMLElement): MountConfig | null {
    const apiBase = element.dataset.apiBase?.trim();
    const resource = element.dataset.resource?.trim();
    const id = element.dataset.id?.trim();

    if (!apiBase || !resource || !id) {
        return null;
    }

    return { apiBase, resource, id };
}

async function mount(element: MountableElement): Promise<void> {
    const config = readMountConfig(element);

    if (config === null) {
        console.error(
            'visual-editor: mount point is missing data-api-base, data-resource, or data-id.',
            element
        );
        return;
    }

    if (element[ROOT_SYMBOL]) {
        return;
    }

    // Reserve the element synchronously so a second concurrent mount() call
    // (e.g. a rapid bootVisualEditor() re-trigger) can't slip past the
    // dedupe check while the dynamic import is still in flight.
    const root = createRoot(element);
    element[ROOT_SYMBOL] = root;

    try {
        const { EditorApp } = await import('./editor-app');

        root.render(
            createElement(StrictMode, null, createElement(EditorApp, config))
        );
    } catch (error: unknown) {
        console.error('visual-editor: failed to load editor app.', error);
        root.unmount();
        delete element[ROOT_SYMBOL];
    }
}

export function bootVisualEditor(
    scope: ParentNode = document
): Promise<void[]> {
    const elements = scope.querySelectorAll<HTMLElement>(MOUNT_SELECTOR);

    return Promise.all(Array.from(elements).map((element) => mount(element)));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            void bootVisualEditor();
        });
    } else {
        void bootVisualEditor();
    }
}
