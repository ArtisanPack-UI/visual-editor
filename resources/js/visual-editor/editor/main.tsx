/**
 * Visual editor bootstrap entry.
 *
 * Scans the DOM for every `[data-ap-visual-editor]` mount point rendered by
 * the `<x-visual-editor>` Blade component and attaches a React root to each.
 * Dynamically imports the editor app so the `@wordpress/*` bundle lands in a
 * dedicated `gutenberg` chunk (see vite.config.ts) that only downloads when
 * at least one editor is present on the page.
 */

import { createElement } from 'react';
import { createRoot, type Root } from 'react-dom/client';

import type {
    AuthorOption,
    DocumentSupports,
    FeaturedImageValue,
} from './document-panels';

export { registerMediaBridge } from '../media-bridge';
export type {
    BridgeMedia,
    BridgeMediaType,
    MediaBridgeComponent,
    MediaBridgeComponentProps,
    MediaUploader,
    RegisterMediaBridgeOptions,
} from '../media-bridge';

export {
    VE_EDITOR_AUTOSAVE,
    VE_EDITOR_CHANGE,
    VE_EDITOR_SAVE,
} from './editor-events';
export type {
    EditorEventTarget,
    VeEditorAutosaveDetail,
    VeEditorAutosaveEvent,
    VeEditorChangeDetail,
    VeEditorChangeEvent,
    VeEditorSaveDetail,
    VeEditorSaveEvent,
} from './editor-events';

export {
    DocumentPanelSlot,
    DOCUMENT_PANELS_FILTER,
    PluginDocumentSettingPanel,
    getFilteredDocumentPanels,
} from './plugin-document-setting-panel';
export type {
    DocumentPanelSpec,
    PluginDocumentSettingPanelProps,
} from './plugin-document-setting-panel';
export type {
    AuthorOption,
    DocumentSupports,
    FeaturedImageValue,
    PostStatus,
} from './document-panels';

const MOUNT_SELECTOR = '[data-ap-visual-editor]';
const ROOT_SYMBOL: unique symbol = Symbol('ap-visual-editor-root');

type MountableElement = HTMLElement & {
    [ROOT_SYMBOL]?: Root;
};

export interface MountConfig {
    apiBase: string;
    resource: string;
    id: string;
    initialTitle?: string;
    initialSlug?: string;
    initialStatus?: string;
    initialExcerpt?: string;
    initialFeaturedImage?: FeaturedImageValue | null;
    initialAuthorId?: number | string | null;
    initialCommentsOpen?: boolean;
    authorOptions?: ReadonlyArray<AuthorOption>;
    supports?: DocumentSupports;
    previewUrl?: string | null;
}

export interface MountedEditor {
    /**
     * Unmounts the React root and releases the mount slot. Safe to call
     * multiple times — subsequent calls are no-ops.
     */
    unmount(): void;
    /**
     * Resolves once the dynamic editor bundle has been loaded and the
     * initial React render has been scheduled. Rejects if the bundle
     * fails to load.
     */
    ready: Promise<void>;
}

function parseJsonDataset<T>(raw: string | undefined, context: string): T | null {
    if (raw === undefined) {
        return null;
    }

    const trimmed = raw.trim();

    if (trimmed === '') {
        return null;
    }

    try {
        return JSON.parse(trimmed) as T;
    } catch (error) {
        console.warn(
            `visual-editor: could not parse ${context} dataset attribute as JSON.`,
            error
        );
        return null;
    }
}

function readMountConfig(element: HTMLElement): MountConfig | null {
    const apiBase = element.dataset.apiBase?.trim();
    const resource = element.dataset.resource?.trim();
    const id = element.dataset.id?.trim();

    if (!apiBase || !resource || !id) {
        return null;
    }

    const initialTitle = element.dataset.title?.trim();
    const initialSlug = element.dataset.slug?.trim();
    const initialStatus = element.dataset.status?.trim();
    const initialExcerpt = element.dataset.excerpt;
    const initialAuthorId = element.dataset.authorId?.trim();
    const commentsOpenRaw = element.dataset.commentsOpen?.trim();
    const previewUrl = element.dataset.previewUrl?.trim();

    const featuredImage = parseJsonDataset<FeaturedImageValue | null>(
        element.dataset.featuredImage,
        'data-featured-image'
    );
    const authorOptions = parseJsonDataset<ReadonlyArray<AuthorOption>>(
        element.dataset.authorOptions,
        'data-author-options'
    );
    const supports = parseJsonDataset<DocumentSupports>(
        element.dataset.supports,
        'data-supports'
    );

    return {
        apiBase,
        resource,
        id,
        ...(initialTitle ? { initialTitle } : {}),
        ...(initialSlug ? { initialSlug } : {}),
        ...(initialStatus ? { initialStatus } : {}),
        ...(initialExcerpt !== undefined ? { initialExcerpt } : {}),
        ...(initialAuthorId ? { initialAuthorId } : {}),
        ...(commentsOpenRaw !== undefined
            ? { initialCommentsOpen: commentsOpenRaw === 'true' }
            : {}),
        ...(featuredImage !== null ? { initialFeaturedImage: featuredImage } : {}),
        ...(authorOptions !== null ? { authorOptions } : {}),
        ...(supports !== null ? { supports } : {}),
        previewUrl: previewUrl ?? null,
    };
}

/**
 * Mounts the React editor into an arbitrary host element with an explicit
 * config object. Intended for host-framework wrappers (Vue, Svelte, etc.)
 * that can't rely on the `[data-ap-visual-editor]` attribute bootstrap.
 *
 * Returns a {@link MountedEditor} with an `unmount()` method the host
 * should call from its component-unmount hook to tear down the React root.
 */
export function mountEditor(
    element: HTMLElement,
    config: MountConfig,
): MountedEditor {
    const host = element as MountableElement;

    if (host[ROOT_SYMBOL]) {
        return {
            ready: Promise.resolve(),
            unmount: () => unmountEditor(host),
        };
    }

    // Reserve the element synchronously so a second concurrent mount call
    // (e.g. a rapid bootVisualEditor() re-trigger or a dev-server HMR
    // double-mount) can't slip past the dedupe check while the dynamic
    // import is still in flight.
    const root = createRoot(host);
    host[ROOT_SYMBOL] = root;

    const ready = import('./editor-app').then(
        ({ EditorApp }) => {
            // If the host unmounted before the dynamic import resolved,
            // `ROOT_SYMBOL` will have been cleared — skip the render.
            if (host[ROOT_SYMBOL] !== root) {
                return;
            }

            // Intentionally NOT wrapped in React.StrictMode. Several
            // `@wordpress/components` class components (and the block-
            // editor color pipeline) are not StrictMode-safe in v32/v15
            // — the dev-mode double-invocation triggers "Maximum update
            // depth exceeded" crashes during interactions like color
            // picker drag. Revisit once Gutenberg's own StrictMode audit
            // lands upstream.
            root.render(createElement(EditorApp, config));
        },
        (error: unknown) => {
            console.error('visual-editor: failed to load editor app.', error);
            unmountEditor(host);
            throw error;
        },
    );

    return {
        ready,
        unmount: () => unmountEditor(host),
    };
}

function unmountEditor(element: MountableElement): void {
    const root = element[ROOT_SYMBOL];

    if (root === undefined) {
        return;
    }

    delete element[ROOT_SYMBOL];
    root.unmount();
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

    try {
        await mountEditor(element, config).ready;
    } catch {
        // `mountEditor` already logged the failure and cleared the root.
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
