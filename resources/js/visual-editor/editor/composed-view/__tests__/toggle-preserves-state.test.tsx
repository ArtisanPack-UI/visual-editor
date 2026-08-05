/**
 * Flipping the composed-view toggle must not disturb the editor (#618).
 *
 * The abandoned first cut of #621 handed the content `BlockEditorProvider`
 * a *composed* tree — chrome wrapped around a content slot — and swapped
 * `value` every time the toggle moved. `BlockEditorProvider` is a single,
 * stateful tree: swapping its `value` discards the block-editor store's
 * selection and undo stack, and the echo (`value` → `onChange` → extract →
 * state → new composed tree → new `value`) froze the editor outright.
 *
 * The shipped design never touches `value`: chrome renders as sibling
 * previews inside the canvas, each with its own isolated store. Selection,
 * undo history and unsaved changes survive a toggle *structurally* — which
 * is precisely why nothing fails today if someone reintroduces the swap.
 * This file is that tripwire. It asserts the structural invariants that
 * the store's state depends on:
 *
 *   - the content provider is mounted exactly once across any number of
 *     toggles (a remount is a store teardown — selection and undo gone);
 *   - its `value` reference is identical before and after (a swap is a
 *     `resetBlocks`, which clears selection and pushes undo);
 *   - the toggle fires neither `onChange` nor `onInput`, so it neither
 *     records an undo entry nor marks the document dirty;
 *   - the editor's own undo/redo affordances read the same either side.
 *
 * `@wordpress/block-editor` and the heavy editor regions are stubbed, per
 * the convention in `editor-canvas.test.tsx` and
 * `site-editor/__tests__/site-editor-app.test.tsx` — the real modules pull
 * `@wordpress/blocks` (unimportable under vitest) and mount an iframe.
 * The `EditorCanvas` stub records the `chrome` prop, so the tests can
 * prove the toggle *did* take effect while the provider stood still.
 */

import { render, screen, waitFor } from '@testing-library/react';
import type { RenderResult } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';

// Side-effect import in `editor-app.tsx`. The real package is a
// node_modules dependency, so its own `@wordpress/blocks` import is not
// routed through the mock below and trips vitest's JSON-import-attribute
// error. Nothing here exercises inline formats.
vi.mock('@wordpress/format-library', () => ({}));

vi.mock('../../../blocks', () => ({
    registerArtisanPackBlocks: (): void => undefined,
}));
vi.mock('../../../background-controls', () => ({
    registerBackgroundControls: (): void => undefined,
}));
vi.mock('../../../box-shadows/register', () => ({
    registerBoxShadows: (): void => undefined,
}));
vi.mock('../../../gradient-borders/register', () => ({
    registerGradientBorders: (): void => undefined,
}));
vi.mock('../../../positioning/register', () => ({
    registerPositioning: (): void => undefined,
}));
vi.mock('../../../responsive/register-attribute', () => ({
    registerResponsiveAttribute: (): void => undefined,
}));
vi.mock('../../../responsive/with-responsive-attributes', () => ({
    registerResponsiveAttributesFilter: (): void => undefined,
}));
vi.mock('../../../states/register-attribute', () => ({
    registerStateAttribute: (): void => undefined,
}));
vi.mock('../../../states/with-state-attributes', () => ({
    registerStateAttributesFilter: (): void => undefined,
}));
vi.mock('../../../states/with-state-styles', () => ({
    registerStateStylesFilters: (): void => undefined,
}));
vi.mock('../../../states/StateInspectorSync', () => ({
    StateInspectorSync: (): null => null,
}));
vi.mock('../../../states/state-write-interceptor', () => ({
    StateWriteInterceptor: (): null => null,
}));
vi.mock('../../../animations/register-attribute', () => ({
    registerAnimationsAttribute: (): void => undefined,
}));
vi.mock('../../../animations/with-animations-panel', () => ({
    registerAnimationsPanel: (): void => undefined,
}));
vi.mock('../../../visibility/register-attribute', () => ({
    registerVisibilityAttribute: (): void => undefined,
}));
vi.mock('../../../visibility/with-visibility-panel', () => ({
    registerVisibilityPanel: (): void => undefined,
    setVisibilityBreakpoints: (): void => undefined,
    setVisibilityRoles: (): void => undefined,
}));
vi.mock('../../../dynamic-content', () => ({
    registerDynamicContent: (): void => undefined,
}));
vi.mock('../../block-library-sidebar', () => ({
    BlockLibrarySidebar: (): JSX.Element => (
        <div data-testid="ap-stub-block-library-sidebar" />
    ),
}));
vi.mock('../../inspector-sidebar', () => ({
    InspectorSidebar: (): JSX.Element => (
        <div data-testid="ap-stub-inspector-sidebar" />
    ),
}));
vi.mock('../../contrast-warning', () => ({
    registerContrastWarning: (): void => undefined,
}));
vi.mock('../../synced-pattern-indicator', () => ({
    registerSyncedPatternIndicator: (): void => undefined,
}));
vi.mock('../../convert-to-pattern-control', () => ({
    ConvertToPatternControl: (): null => null,
}));
vi.mock('../../page-pattern-modal/page-pattern-modal', () => ({
    PagePatternModal: (): null => null,
}));
vi.mock('../../keyboard-shortcuts-modal', () => ({
    KeyboardShortcutsModal: (): null => null,
}));

const canvasProps = vi.fn();

vi.mock('../../editor-canvas', () => ({
    EditorCanvas: (props: Record<string, unknown>): JSX.Element => {
        canvasProps(props);

        return (
            <div
                data-testid="ap-stub-editor-canvas"
                data-composed={props.chrome !== null && props.chrome !== undefined}
            />
        );
    },
}));

/**
 * The provider stub is the heart of this file: it counts mounts and
 * records the `value` reference on every render.
 */
const providerMounts = vi.fn();
const providerRenders = vi.fn();

vi.mock('@wordpress/block-editor', async () => {
    const { useEffect } = await vi.importActual<typeof import('react')>(
        'react'
    );

    return {
        BlockEditorProvider: (props: {
            value: unknown;
            children?: ReactNode;
        }): JSX.Element => {
            providerRenders(props.value);

            useEffect(() => {
                providerMounts();
            }, []);

            return <div data-testid="ap-stub-provider">{props.children}</div>;
        },
    };
});

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes: Record<string, unknown>,
        innerBlocks: unknown[]
    ) => ({
        clientId: `cid-${name}-${Math.random().toString(36).slice(2)}`,
        name,
        attributes,
        innerBlocks,
        isValid: true,
    }),
}));

const fetchAppliedTemplate = vi.fn();

vi.mock('../api', async () => {
    const actual = await vi.importActual<typeof import('../api')>('../api');

    return {
        ...actual,
        fetchAppliedTemplate: (config: unknown) =>
            (fetchAppliedTemplate as unknown as (c: unknown) => unknown)(config),
    };
});

import { EditorApp } from '../../editor-app';

const APPLIED_TEMPLATE = {
    status: 'ok' as const,
    slug: 'single',
    name: 'Single Post',
    blocks: [
        {
            name: 'artisanpack/template-part',
            attributes: { slug: 'header' },
            innerBlocks: [],
        },
        {
            name: 'artisanpack/post-content',
            attributes: {},
            innerBlocks: [],
        },
        {
            name: 'artisanpack/template-part',
            attributes: { slug: 'footer' },
            innerBlocks: [],
        },
    ],
    template_parts: {},
};

/**
 * Mounts the editor and waits for the content GET to settle — the shell
 * renders a "Loading content…" branch until then, with no provider and no
 * canvas to assert against.
 */
async function renderEditor(
    overrides: { resource?: string; id?: string } = {}
): Promise<RenderResult> {
    const result = render(
        <EditorApp
            apiBase="/visual-editor/api"
            resource={overrides.resource ?? 'posts'}
            id={overrides.id ?? '7'}
            initialTitle="Hello"
            initialTemplate="single"
        />
    );

    await screen.findByTestId('ap-stub-provider');

    return result;
}

function viewModeToggle(): HTMLElement {
    return screen.getByTestId('ap-visual-editor-top-bar-view-mode-toggle');
}

function lastCanvasChrome(): unknown {
    const calls = canvasProps.mock.calls;

    return (calls[calls.length - 1]?.[0] as { chrome?: unknown })?.chrome;
}

beforeEach(() => {
    canvasProps.mockClear();
    providerMounts.mockClear();
    providerRenders.mockClear();
    fetchAppliedTemplate.mockReset();
    fetchAppliedTemplate.mockResolvedValue(APPLIED_TEMPLATE);
    vi.stubGlobal(
        'fetch',
        vi.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                // `api-client.ts` reads bodies via `.text()`.
                text: () => Promise.resolve(JSON.stringify({ blocks: [] })),
                json: () => Promise.resolve({ blocks: [] }),
            } as unknown as Response)
        )
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('composed-view toggle preserves editor state (#618)', () => {
    it('composes the canvas when toggled on, and uncomposes when toggled off', async () => {
        const user = userEvent.setup();
        await renderEditor();

        expect(lastCanvasChrome()).toBeNull();

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).not.toBeNull();
        });

        expect(lastCanvasChrome()).toMatchObject({
            templateName: 'Single Post',
            templateSlug: 'single',
        });

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).toBeNull();
        });
    });

    it('never remounts the content provider across toggles', async () => {
        const user = userEvent.setup();
        await renderEditor();

        expect(providerMounts).toHaveBeenCalledTimes(1);

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).not.toBeNull();
        });
        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).toBeNull();
        });

        // A remount tears down the block-editor store: selection gone,
        // undo stack gone. Once, and only once.
        expect(providerMounts).toHaveBeenCalledTimes(1);
    });

    it('never swaps the provider value, so selection and undo survive', async () => {
        const user = userEvent.setup();
        await renderEditor();

        const initialValue = providerRenders.mock.calls[0]?.[0];

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).not.toBeNull();
        });
        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).toBeNull();
        });

        // Same reference on every render — not merely deep-equal. A
        // composed tree would be a fresh array each time, and each swap
        // is a `resetBlocks` in the store.
        for (const [value] of providerRenders.mock.calls) {
            expect(value).toBe(initialValue);
        }
    });

    it('records no block change, so the toggle leaves the document clean', async () => {
        const user = userEvent.setup();
        await renderEditor();

        const undo = screen.getByTestId('ap-visual-editor-top-bar-undo');
        const redo = screen.getByTestId('ap-visual-editor-top-bar-redo');

        expect(undo).toBeDisabled();
        expect(redo).toBeDisabled();

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).not.toBeNull();
        });

        // Composing the view is a presentation change: no undo entry, no
        // dirty flag, nothing to save.
        expect(undo).toBeDisabled();
        expect(redo).toBeDisabled();
        expect(
            screen.getByTestId('ap-visual-editor-top-bar-save-status')
        ).toHaveAttribute('data-save-status', 'idle');
    });
});

describe('applied-template request: template override', () => {
    it('omits the override for resources with no template control', async () => {
        const user = userEvent.setup();
        await renderEditor({ resource: 'posts' });

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(fetchAppliedTemplate).toHaveBeenCalled();
        });

        // Only pages surface the template picker, so `template` state stays
        // `''` elsewhere — and `''` is the "selection cleared" sentinel the
        // endpoint answers with `missing/empty`. Sending it made the
        // persisted template unreachable for every non-page resource.
        expect(fetchAppliedTemplate.mock.calls[0][0]).toMatchObject({
            resource: 'posts',
            template: undefined,
        });
    });

    it('still sends the live selection for pages', async () => {
        const user = userEvent.setup();
        await renderEditor({ resource: 'pages' });

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(fetchAppliedTemplate).toHaveBeenCalled();
        });

        expect(fetchAppliedTemplate.mock.calls[0][0]).toMatchObject({
            resource: 'pages',
            template: 'single',
        });
    });
});

describe('composed-view bindings context (#622)', () => {
    it('points the bindings resolver at the content being edited', async () => {
        await renderEditor();

        const host = globalThis as unknown as {
            __artisanpackBindingsResource?: string | null;
            __artisanpackBindingsRecordId?: number | string | null;
        };

        // `core/post-title` / `core/post-author` in the template chrome
        // resolve through this context, so the composed view shows *this*
        // post's title and author rather than the template's sample data.
        expect(host.__artisanpackBindingsResource).toBe('posts');
        expect(host.__artisanpackBindingsRecordId).toBe('7');
    });

    it('clears the context on unmount so the next editor starts clean', async () => {
        // Let the content GET settle before unmounting: tearing down
        // mid-flight would leave a pending state update behind and make
        // the assertion below race the load.
        const { unmount } = await renderEditor();

        unmount();

        const host = globalThis as unknown as {
            __artisanpackBindingsResource?: string | null;
            __artisanpackBindingsRecordId?: number | string | null;
        };

        expect(host.__artisanpackBindingsResource).toBeNull();
        expect(host.__artisanpackBindingsRecordId).toBeNull();
    });

    it('gives the chrome the same entity block context as the content', async () => {
        const user = userEvent.setup();
        await renderEditor();

        await user.click(viewModeToggle());
        await waitFor(() => {
            expect(lastCanvasChrome()).not.toBeNull();
        });

        const props = canvasProps.mock.calls[
            canvasProps.mock.calls.length - 1
        ]?.[0] as { blockContext?: unknown };

        // Chrome previews mount inside the canvas's `BlockContextProvider`
        // (see `editor-canvas.tsx`), so `core/post-*` blocks in the
        // template see the active entity, not a placeholder.
        expect(props.blockContext).toEqual({ postType: 'post', postId: 7 });
    });
});
