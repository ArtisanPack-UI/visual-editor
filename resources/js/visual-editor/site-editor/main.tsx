/**
 * Site-editor bootstrap entry.
 *
 * Scans the DOM for the `[data-ap-site-editor]` mount element rendered
 * by `resources/views/site-editor/index.blade.php` and attaches the
 * React shell to it. Mirrors the post-editor boot pattern in
 * `editor/main.tsx` so host apps that already understand one entry
 * understand the other.
 *
 * The Gutenberg packages are imported lazily through a dynamic
 * `import('./site-editor-app')` so the `@wordpress/*` bundle lands in
 * the shared `gutenberg` chunk (see `vite.config.ts`).
 */

import { createElement } from 'react';
import { createRoot, type Root } from 'react-dom/client';

const MOUNT_SELECTOR = '[data-ap-site-editor]';
const ROOT_SYMBOL: unique symbol = Symbol('ap-site-editor-root');
const READY_SYMBOL: unique symbol = Symbol('ap-site-editor-ready');

type MountableElement = HTMLElement & {
    [ROOT_SYMBOL]?: Root;
    /**
     * Resolves once the dynamic shell module has loaded and the initial
     * render has been scheduled. Stored on the host so a second call to
     * {@link mountSiteEditor} on the same element waits for the first
     * mount's render commit instead of resolving immediately and
     * letting callers race the render.
     */
    [READY_SYMBOL]?: Promise<void>;
};

export interface SiteEditorMountConfig {
    routeBase: string;
    postEditorUrl: string;
}

export interface MountedSiteEditor {
    /**
     * Tear down the React root and release the mount slot. Idempotent.
     */
    unmount(): void;
    /**
     * Resolves once the dynamic shell module has loaded and the initial
     * render has been scheduled. Rejects if the bundle fails to load.
     */
    ready: Promise<void>;
}

function readMountConfig(element: HTMLElement): SiteEditorMountConfig | null {
    const routeBase = element.dataset.routeBase?.trim();
    const postEditorUrl = element.dataset.postEditorUrl?.trim();

    if (!routeBase || !postEditorUrl) {
        return null;
    }

    return { routeBase, postEditorUrl };
}

export function mountSiteEditor(
    element: HTMLElement,
    config: SiteEditorMountConfig
): MountedSiteEditor {
    const host = element as MountableElement;

    // Re-mount on an already-mounted host: hand back the in-flight
    // readiness promise so the second caller sees the same render
    // commit the first caller is waiting for. Falling back to
    // `Promise.resolve()` here (the previous behaviour) lets the
    // second caller race the dynamic import and observe a
    // half-mounted DOM.
    const existing = host[READY_SYMBOL];

    if (host[ROOT_SYMBOL] && existing !== undefined) {
        return {
            ready: existing,
            unmount: () => unmountSiteEditor(host),
        };
    }

    const root = createRoot(host);
    host[ROOT_SYMBOL] = root;

    const ready = import('./site-editor-app').then(
        ({ SiteEditorApp }) => {
            // Bail if the host unmounted before the dynamic import
            // resolved (HMR / rapid re-mount).
            if (host[ROOT_SYMBOL] !== root) {
                return;
            }

            // Same StrictMode caveat as the post editor — see
            // `editor/main.tsx` for the rationale. Several
            // `@wordpress/components` and block-editor color paths
            // are not StrictMode-safe in the pinned package versions.
            root.render(createElement(SiteEditorApp, config));
        },
        (error: unknown) => {
            console.error('site-editor: failed to load shell.', error);

            // Same staleness guard as the success path above: if the
            // host has been unmounted and re-mounted (HMR, rapid
            // re-boot) while this import was in flight, the current
            // root on the host belongs to that newer mount — leave it
            // alone instead of tearing it down on a stale failure.
            if (host[ROOT_SYMBOL] === root) {
                unmountSiteEditor(host);
            }

            throw error;
        }
    );

    host[READY_SYMBOL] = ready;

    return {
        ready,
        unmount: () => unmountSiteEditor(host),
    };
}

function unmountSiteEditor(element: MountableElement): void {
    const root = element[ROOT_SYMBOL];

    if (root === undefined) {
        return;
    }

    delete element[ROOT_SYMBOL];
    delete element[READY_SYMBOL];
    root.unmount();
}

async function mount(element: MountableElement): Promise<void> {
    const config = readMountConfig(element);

    if (config === null) {
        console.error(
            'site-editor: mount point is missing data-route-base or data-post-editor-url.',
            element
        );
        return;
    }

    try {
        await mountSiteEditor(element, config).ready;
    } catch {
        // `mountSiteEditor` already logged the failure and cleared the root.
    }
}

export function bootSiteEditor(
    scope: ParentNode = document
): Promise<void[]> {
    const elements = scope.querySelectorAll<HTMLElement>(MOUNT_SELECTOR);

    return Promise.all(Array.from(elements).map((element) => mount(element)));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            void bootSiteEditor();
        });
    } else {
        void bootSiteEditor();
    }
}
