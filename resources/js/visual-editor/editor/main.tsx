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

import '../a11y.css';

import type {
    AuthorOption,
    DocumentSupports,
    FeaturedImageValue,
} from './document-panels';

export {
    registerArtisanpackMediaBridge,
    registerMediaBridge,
} from '../media-bridge';
export type {
    BridgeMedia,
    BridgeMediaType,
    MediaBridgeComponent,
    MediaBridgeComponentProps,
    MediaUploader,
    RegisterArtisanpackMediaBridgeOptions,
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
    /**
     * Initial category ids — surfaced in the inspector for `posts` resources.
     * Hosts pass via `data-categories` (JSON array) on the mount element.
     */
    initialCategories?: ReadonlyArray<number>;
    /**
     * Initial tag ids — surfaced in the inspector for `posts` resources.
     * Hosts pass via `data-tags` (JSON array).
     */
    initialTags?: ReadonlyArray<number>;
    /**
     * Initial parent page id — surfaced in the inspector for `pages` resources.
     * Hosts pass via `data-parent` (numeric string; empty for top-level).
     */
    initialParent?: number | null;
    /**
     * Initial page menu_order — surfaced in the inspector for `pages` resources.
     * Hosts pass via `data-menu-order` (numeric string).
     */
    initialMenuOrder?: number;
    /**
     * Initial theme template slug — surfaced in the inspector for `pages` resources.
     * Hosts pass via `data-template`.
     */
    initialTemplate?: string;
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
    const rawAuthorId = element.dataset.authorId?.trim();
    const commentsOpenRaw = element.dataset.commentsOpen?.trim();
    const previewUrl = element.dataset.previewUrl?.trim();
    const rawParent = element.dataset.parent?.trim();
    const rawMenuOrder = element.dataset.menuOrder?.trim();
    const initialTemplate = element.dataset.template?.trim();

    const initialCategories = parseIdListDataset(
        element.dataset.categories,
        'data-categories'
    );
    const initialTags = parseIdListDataset(
        element.dataset.tags,
        'data-tags'
    );

    // `data-parent` has three meaningful states the rest of the editor
    // depends on: missing (use the default), the empty string (explicit
    // top-level — host stamped a `null` parent), and a numeric id.
    // A fourth case — malformed, e.g. `data-parent="abc"` — would be
    // silently coerced to `null` by `parseNullableInt` and conflated
    // with the legitimate top-level signal. Treat malformed input as
    // "not present" instead so the inspector falls back to its default
    // and a host bug doesn't masquerade as a top-level page.
    let initialParent: number | null | undefined;

    if (rawParent === undefined) {
        initialParent = undefined;
    } else if (rawParent === '') {
        initialParent = null;
    } else {
        const parsed = parseNullableInt(rawParent);
        initialParent = parsed ?? undefined;
    }

    const initialMenuOrder = parseNullableInt(rawMenuOrder);

    const featuredImage = parseJsonDataset<FeaturedImageValue | null>(
        element.dataset.featuredImage,
        'data-featured-image'
    );
    // `parseJsonDataset` returns whatever the JSON resolves to, so a host
    // mis-emitting an object (or a primitive) as `data-author-options`
    // would otherwise crash `normalizeAuthorId`'s `.find` / the
    // SelectControl props builder. Fall back to null when the parsed
    // value isn't an array.
    const parsedAuthorOptions = parseJsonDataset<unknown>(
        element.dataset.authorOptions,
        'data-author-options'
    );
    const authorOptions: ReadonlyArray<AuthorOption> | null = Array.isArray(
        parsedAuthorOptions
    )
        ? (parsedAuthorOptions as ReadonlyArray<AuthorOption>)
        : null;
    const supports = parseJsonDataset<DocumentSupports>(
        element.dataset.supports,
        'data-supports'
    );

    // Dataset attributes are always strings, but most Laravel hosts store
    // author IDs as integers. Normalize back to the original type so
    // `onMetadataChange` emits values that round-trip cleanly into the
    // host's model. Preference order: exact match against an author
    // option (preserves whatever type the host declared), then numeric
    // coercion when the string looks numeric, then leave-as-string.
    const initialAuthorId = normalizeAuthorId(rawAuthorId, authorOptions);

    return {
        apiBase,
        resource,
        id,
        ...(initialTitle ? { initialTitle } : {}),
        ...(initialSlug ? { initialSlug } : {}),
        ...(initialStatus ? { initialStatus } : {}),
        ...(initialExcerpt !== undefined ? { initialExcerpt } : {}),
        ...(initialAuthorId !== undefined ? { initialAuthorId } : {}),
        ...(commentsOpenRaw !== undefined
            ? { initialCommentsOpen: commentsOpenRaw === 'true' }
            : {}),
        ...(featuredImage !== null ? { initialFeaturedImage: featuredImage } : {}),
        ...(authorOptions !== null ? { authorOptions } : {}),
        ...(supports !== null ? { supports } : {}),
        ...(initialCategories !== null ? { initialCategories } : {}),
        ...(initialTags !== null ? { initialTags } : {}),
        ...(initialParent !== undefined ? { initialParent } : {}),
        ...(initialMenuOrder !== null
            ? { initialMenuOrder }
            : {}),
        ...(initialTemplate ? { initialTemplate } : {}),
        previewUrl: previewUrl ?? null,
    };
}

/**
 * Parses a `data-*` JSON array of integers into a deduplicated id
 * list. Returns `null` when the attribute is missing or unparseable
 * so `readMountConfig` can omit the field instead of clobbering the
 * `EditorAppProps` default.
 *
 * Exported for tests. Not part of the public package surface.
 *
 * @internal
 */
export function parseIdListDataset(
    raw: string | undefined,
    context: string
): ReadonlyArray<number> | null {
    const parsed = parseJsonDataset<unknown>(raw, context);

    if (!Array.isArray(parsed)) {
        return null;
    }

    const ids: number[] = [];

    for (const candidate of parsed) {
        if (
            typeof candidate === 'number' &&
            Number.isInteger(candidate) &&
            candidate > 0
        ) {
            ids.push(candidate);
            continue;
        }

        if (
            typeof candidate === 'string' &&
            candidate.trim() !== '' &&
            /^[1-9]\d*$/.test(candidate.trim())
        ) {
            ids.push(Number.parseInt(candidate.trim(), 10));
        }
    }

    return Array.from(new Set(ids));
}

/**
 * Parses a `data-*` numeric attribute into a finite integer or
 * `null` for blank / unparseable input.
 *
 * The whole string must match `^-?\d+$` (optional leading minus, one
 * or more digits, nothing else); `parseInt` would otherwise silently
 * accept tokens like `"3.9"` (truncated to `3`) or `"12abc"`
 * (truncated to `12`) and surface them as initial values.
 *
 * Exported for tests. Not part of the public package surface.
 *
 * @internal
 */
export function parseNullableInt(raw: string | undefined): number | null {
    if (raw === undefined) {
        return null;
    }

    const trimmed = raw.trim();

    if (trimmed === '' || !/^-?\d+$/.test(trimmed)) {
        return null;
    }

    const parsed = Number.parseInt(trimmed, 10);

    return Number.isFinite(parsed) ? parsed : null;
}

/**
 * Exported for tests. Not part of the public package surface.
 * @internal
 */
export function normalizeAuthorId(
    raw: string | undefined,
    authorOptions: ReadonlyArray<AuthorOption> | null
): number | string | undefined {
    if (raw === undefined || raw === '') {
        return undefined;
    }

    if (authorOptions !== null && authorOptions.length > 0) {
        const match = authorOptions.find(
            (option) =>
                String(option.value) === raw || option.value === Number(raw)
        );

        if (match !== undefined) {
            return match.value;
        }
    }

    const asNumber = Number(raw);

    if (!Number.isNaN(asNumber) && /^-?\d+(\.\d+)?$/.test(raw)) {
        return asNumber;
    }

    return raw;
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

// Expose the boot function on `window` so SPA hosts (Inertia, Livewire, etc.)
// can call it after client-side navigation introduces new
// `[data-ap-visual-editor]` markers. `mountEditor` already de-dupes via a
// per-element symbol, so calling this on already-mounted markers is a no-op.
//
// `window.ApVisualEditor` mirrors the named ESM exports for hosts that
// load this bundle as `<script type="module" src="…">` rather than
// importing it. The bridge state lives in `media-bridge/state.ts` inside
// this same module scope, so calls through the global flow into the same
// registry as direct imports.
import {
    registerArtisanpackMediaBridge as registerArtisanpackMediaBridgeImpl,
    registerMediaBridge as registerMediaBridgeImpl,
} from '../media-bridge';

declare global {
    interface Window {
        ApVisualEditorBoot?: (scope?: ParentNode) => Promise<void[]>;
        ApVisualEditor?: {
            boot: typeof bootVisualEditor;
            registerArtisanpackMediaBridge: typeof registerArtisanpackMediaBridgeImpl;
            registerMediaBridge: typeof registerMediaBridgeImpl;
        };
    }
}

if (typeof window !== 'undefined') {
    window.ApVisualEditorBoot = bootVisualEditor;
    window.ApVisualEditor = {
        boot: bootVisualEditor,
        registerArtisanpackMediaBridge: registerArtisanpackMediaBridgeImpl,
        registerMediaBridge: registerMediaBridgeImpl,
    };
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
