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

import { registerHookAliases } from '../support/hook-aliases';

// Install #664 hook-name aliases at module load. See editor/main.tsx for
// the rationale — the site-editor shares the same hook surface as the
// post editor.
registerHookAliases();

import '../a11y.css';
import type { BreakpointRegistrySnapshot } from '../responsive/types';

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
    apiBase: string;
    /**
     * URL the top-bar exit link points at — where the user goes when
     * they leave the site editor. Optional: omit it and no exit link
     * renders. CMS hosts set this to their admin dashboard; the
     * package dev app sets it to the post editor.
     */
    exitUrl?: string;
    /**
     * Label for the exit link. Used verbatim (the consuming app owns
     * its translation). Falls back to a generic "← Back" when an
     * `exitUrl` is supplied without a label.
     */
    exitLabel?: string;
    theme?: string;
    /**
     * Serialised breakpoint registry (#617). Hosts stamp this via
     * `data-breakpoints` on the mount element; the shell hydrates it
     * via `registryFromSnapshot()` and forwards the resulting
     * registry to `TopBar` so the viewport switcher respects
     * host-configured `label` / `previewWidthPx` overrides.
     */
    breakpoints?: BreakpointRegistrySnapshot | null;
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
    const apiBase = element.dataset.apiBase?.trim();
    const exitLabel = element.dataset.exitLabel?.trim();
    const theme = element.dataset.theme?.trim();

    // `data-post-editor-url` is the pre-#446 attribute name. Read it as
    // a deprecated fallback for one release so a host that hasn't
    // migrated its mount markup yet still gets a working exit link.
    //
    // An explicitly-present `data-exit-url` always wins — even when
    // empty — so a consumer can set `data-exit-url=""` to deliberately
    // opt out of the exit link without the deprecated key silently
    // resurrecting it. The fallback only fires when `data-exit-url` is
    // absent entirely.
    const exitUrl =
        element.dataset.exitUrl !== undefined
            ? element.dataset.exitUrl.trim()
            : element.dataset.postEditorUrl?.trim();

    if (!routeBase || !apiBase) {
        return null;
    }

    if (
        element.dataset.exitUrl === undefined &&
        element.dataset.postEditorUrl !== undefined
    ) {
        console.warn(
            'site-editor: `data-post-editor-url` is deprecated — use `data-exit-url` (and optionally `data-exit-label`).'
        );
    }

    // #617 — the host stamps the merged breakpoint registry as
    // `data-breakpoints`. Passed through unchanged to the shell.
    const rawBreakpoints = element.dataset.breakpoints?.trim();
    let breakpoints: BreakpointRegistrySnapshot | null = null;
    if (rawBreakpoints !== undefined && rawBreakpoints !== '') {
        try {
            const parsed = JSON.parse(rawBreakpoints);
            if (Array.isArray(parsed)) {
                breakpoints = { breakpoints: parsed as BreakpointRegistrySnapshot['breakpoints'] };
            }
        } catch (error) {
            console.warn(
                'site-editor: could not parse data-breakpoints as JSON.',
                error
            );
        }
    }

    return {
        routeBase,
        apiBase,
        ...(exitUrl !== undefined && exitUrl !== '' ? { exitUrl } : {}),
        ...(exitLabel !== undefined && exitLabel !== '' ? { exitLabel } : {}),
        ...(theme !== undefined && theme !== '' ? { theme } : {}),
        ...(breakpoints !== null ? { breakpoints } : {}),
    };
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
    const existingRoot = host[ROOT_SYMBOL];

    if (existingRoot !== undefined && existing !== undefined) {
        return {
            ready: existing,
            unmount: makeBoundUnmount(host, existingRoot),
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
        unmount: makeBoundUnmount(host, root),
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

/**
 * Builds an unmount closure bound to the specific root the caller's
 * {@link MountedSiteEditor} handle was created for. A handle that was
 * already used to unmount (or that belongs to a mount that has since
 * been replaced — HMR, rapid re-boot) becomes a no-op instead of
 * tearing down whatever newer root happens to be on the host. Without
 * this, a stale handle's `unmount()` would call `unmountSiteEditor`
 * unconditionally and stomp the live mount.
 */
function makeBoundUnmount(host: MountableElement, ownRoot: Root): () => void {
    return () => {
        if (host[ROOT_SYMBOL] === ownRoot) {
            unmountSiteEditor(host);
        }
    };
}

async function mount(element: MountableElement): Promise<void> {
    const config = readMountConfig(element);

    if (config === null) {
        console.error(
            'site-editor: mount point is missing data-route-base or data-api-base.',
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
