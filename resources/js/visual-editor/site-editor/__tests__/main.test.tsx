import { act } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

// Stub the dynamic shell import so the boot test doesn't try to load
// `@wordpress/block-editor` under jsdom.
vi.mock('../site-editor-app', () => ({
    SiteEditorApp: (): JSX.Element => (
        <div data-testid="ap-site-editor-stub" />
    ),
}));

import { bootSiteEditor, mountSiteEditor } from '../main';

describe('bootSiteEditor', () => {
    it('skips elements that are missing required data attributes', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {
            // suppress test-log noise — the warning is the assertion target.
        });

        const root = document.createElement('div');
        const incomplete = document.createElement('div');
        incomplete.setAttribute('data-ap-site-editor', '');
        // route-base + post-editor-url intentionally absent.
        root.appendChild(incomplete);

        await bootSiteEditor(root);

        expect(errorSpy).toHaveBeenCalledWith(
            expect.stringContaining('mount point is missing'),
            incomplete
        );

        errorSpy.mockRestore();
    });

    it('mounts the React shell when a valid element is present', async () => {
        const root = document.createElement('div');
        const target = document.createElement('div');
        target.setAttribute('data-ap-site-editor', '');
        target.dataset.routeBase = '/visual-editor/site';
        target.dataset.postEditorUrl = '/editor';
        target.dataset.apiBase = '/visual-editor/api';
        root.appendChild(target);
        document.body.appendChild(root);

        try {
            // `act` flushes the React 18 concurrent-render commit that
            // `createRoot().render()` only schedules for a future microtask;
            // without it the assertion races the renderer.
            await act(async () => {
                await bootSiteEditor(root);
            });

            expect(target.querySelector('[data-testid="ap-site-editor-stub"]')).not.toBeNull();
        } finally {
            document.body.removeChild(root);
        }
    });
});

describe('mountSiteEditor', () => {
    it("a stale handle's unmount() is a no-op against a newer root", async () => {
        const target = document.createElement('div');
        target.setAttribute('data-ap-site-editor', '');
        document.body.appendChild(target);

        try {
            // First mount → handleA. Tear it down, then re-mount the
            // same node → handleB. Calling handleA.unmount() a second
            // time must NOT touch handleB's root.
            let handleA: ReturnType<typeof mountSiteEditor>;
            let handleB: ReturnType<typeof mountSiteEditor>;

            await act(async () => {
                handleA = mountSiteEditor(target, {
                    routeBase: '/visual-editor/site',
                    postEditorUrl: '/editor',
                    apiBase: '/visual-editor/api',
                });
                await handleA.ready;
            });

            await act(async () => {
                handleA.unmount();
            });
            expect(
                target.querySelectorAll('[data-testid="ap-site-editor-stub"]').length
            ).toBe(0);

            await act(async () => {
                handleB = mountSiteEditor(target, {
                    routeBase: '/visual-editor/site',
                    postEditorUrl: '/editor',
                    apiBase: '/visual-editor/api',
                });
                await handleB.ready;
            });
            expect(
                target.querySelectorAll('[data-testid="ap-site-editor-stub"]').length
            ).toBe(1);

            // Stale unmount — must not affect handleB's root.
            await act(async () => {
                handleA.unmount();
            });
            expect(
                target.querySelectorAll('[data-testid="ap-site-editor-stub"]').length
            ).toBe(1);

            await act(async () => {
                handleB.unmount();
            });
        } finally {
            if (target.parentNode === document.body) {
                document.body.removeChild(target);
            }
        }
    });

    it('is idempotent — repeat mounts on the same node return the same root', async () => {
        const target = document.createElement('div');
        target.setAttribute('data-ap-site-editor', '');
        document.body.appendChild(target);

        try {
            await act(async () => {
                const first = mountSiteEditor(target, {
                    routeBase: '/visual-editor/site',
                    postEditorUrl: '/editor',
                    apiBase: '/visual-editor/api',
                });
                await first.ready;
            });

            const second = mountSiteEditor(target, {
                routeBase: '/visual-editor/site',
                postEditorUrl: '/editor',
                apiBase: '/visual-editor/api',
            });
            await second.ready;

            // The second call should have observed the existing root
            // rather than mounting a duplicate React tree.
            expect(
                target.querySelectorAll('[data-testid="ap-site-editor-stub"]').length
            ).toBe(1);

            await act(async () => {
                second.unmount();
            });
        } finally {
            if (target.parentNode === document.body) {
                document.body.removeChild(target);
            }
        }
    });
});
