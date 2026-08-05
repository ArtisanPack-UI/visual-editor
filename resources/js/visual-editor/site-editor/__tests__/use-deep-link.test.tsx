/**
 * `useSiteEditorDeepLink` (#625) turns a mount-time
 * `?entity=template&slug=…` into a router navigation. The list endpoint
 * is stubbed so the suite asserts the resolve → navigate → toast wiring
 * rather than re-testing `api-client.ts`.
 */

import { act, render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '@artisanpack-ui/react/feedback';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listEntities = vi.fn();

vi.mock('../api-client', () => ({
    listEntities: (...args: readonly unknown[]) => listEntities(...args),
}));

import { useSiteEditorDeepLink } from '../use-deep-link';

const apiConfig = { apiBase: '/visual-editor/api' };

function Harness(props: { search: string; navigate: ReturnType<typeof vi.fn> }): JSX.Element {
    useSiteEditorDeepLink({
        apiConfig,
        navigate: props.navigate,
        search: props.search,
    });

    return <div data-testid="ap-deep-link-harness" />;
}

function renderHarness(search: string) {
    const navigate = vi.fn();

    const result = render(
        <ToastProvider>
            <Harness search={search} navigate={navigate} />
        </ToastProvider>
    );

    return { ...result, navigate };
}

describe('useSiteEditorDeepLink', () => {
    beforeEach(() => {
        listEntities.mockReset();
        // The landing compares against the pathname captured at mount, and
        // one test pushes a new one — reset so ordering can't leak.
        window.history.replaceState({}, '', '/visual-editor/site');
    });

    it('navigates to the resolved template on mount', async () => {
        listEntities.mockResolvedValue([{ id: 12, slug: 'single' }]);

        const { navigate } = renderHarness('?entity=template&slug=single');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', '12', {
                replace: true,
            });
        });

        expect(listEntities).toHaveBeenCalledWith(apiConfig, 'template', {
            slug: 'single',
        });
    });

    it('ignores a slug the endpoint returns alongside non-matching rows', async () => {
        // A host that drops the `slug` filter hands back the whole list;
        // picking `matches[0]` would open the wrong template.
        listEntities.mockResolvedValue([
            { id: 3, slug: 'archive' },
            { id: 7, slug: 'single' },
        ]);

        const { navigate } = renderHarness('?entity=template&slug=single');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', '7', {
                replace: true,
            });
        });
    });

    it('lands on the templates index with a toast when the slug is unknown', async () => {
        listEntities.mockResolvedValue([]);

        const { navigate } = renderHarness('?entity=template&slug=nope');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', null, {
                replace: true,
            });
        });

        expect(
            await screen.findByText('The template “nope” was not found.')
        ).toBeInTheDocument();
    });

    it('lands on the templates index with a toast when the lookup fails', async () => {
        listEntities.mockRejectedValue(new Error('network down'));

        const { navigate } = renderHarness('?entity=template&slug=single');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', null, {
                replace: true,
            });
        });

        expect(
            await screen.findByText('The template “single” was not found.')
        ).toBeInTheDocument();
    });

    it('skips the lookup when entity_id is supplied', async () => {
        const { navigate } = renderHarness(
            '?entity=template&slug=single&entity_id=42'
        );

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', '42', {
                replace: true,
            });
        });

        expect(listEntities).not.toHaveBeenCalled();
    });

    it.each([
        ['no query string', ''],
        ['unrelated params', '?foo=bar'],
        ['unknown entity', '?entity=navigation&slug=primary'],
        ['entity without slug', '?entity=template'],
    ])('is a no-op for %s', async (_label, search) => {
        const { navigate } = renderHarness(search);

        expect(await screen.findByTestId('ap-deep-link-harness')).toBeInTheDocument();
        expect(navigate).not.toHaveBeenCalled();
        expect(listEntities).not.toHaveBeenCalled();
    });

    it('falls through to the slug lookup when entity_id is not id-shaped', async () => {
        // Skipping verification on a hand-typed id lands the author on an
        // entity-editor fetch failure instead of the not-found toast.
        listEntities.mockResolvedValue([{ id: 12, slug: 'single' }]);

        const { navigate } = renderHarness(
            '?entity=template&slug=single&entity_id=oops'
        );

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', '12', {
                replace: true,
            });
        });

        expect(listEntities).toHaveBeenCalled();
    });

    it('announces the not-found toast once per slug, not once per resolution', async () => {
        // StrictMode double-invokes the effect; the duplicate navigation is
        // absorbed by the routing dedupe but the toast was not, so the same
        // failure was announced twice. Two resolutions of one slug is the
        // shape that reproduces it.
        listEntities.mockResolvedValue([]);

        const navigate = vi.fn();

        render(
            <ToastProvider>
                <Harness search="?entity=template&slug=twice" navigate={navigate} />
                <Harness search="?entity=template&slug=twice" navigate={navigate} />
            </ToastProvider>
        );

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledTimes(2);
        });

        expect(
            await screen.findAllByText('The template “twice” was not found.')
        ).toHaveLength(1);
    });

    it('abandons the landing when the user navigates while the lookup is in flight', async () => {
        let resolveLookup: (rows: unknown) => void = () => undefined;

        listEntities.mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveLookup = resolve;
                })
        );

        const { navigate } = renderHarness('?entity=template&slug=single');

        await waitFor(() => {
            expect(listEntities).toHaveBeenCalled();
        });

        // The author moved on inside the SPA before the slug resolved.
        // Yanking them to the deep-link target now is a navigation they
        // never asked for.
        window.history.pushState({}, '', '/visual-editor/site/patterns');

        await act(async () => {
            resolveLookup([{ id: 12, slug: 'single' }]);
        });

        expect(navigate).not.toHaveBeenCalled();
    });

    it('resolves at most once per mount even as the component re-renders', async () => {
        listEntities.mockResolvedValue([{ id: 12, slug: 'single' }]);

        const navigate = vi.fn();
        const { rerender } = render(
            <ToastProvider>
                <Harness search="?entity=template&slug=single" navigate={navigate} />
            </ToastProvider>
        );

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledTimes(1);
        });

        rerender(
            <ToastProvider>
                <Harness search="?entity=template&slug=single" navigate={navigate} />
            </ToastProvider>
        );

        await waitFor(() => {
            expect(listEntities).toHaveBeenCalledTimes(1);
        });

        expect(navigate).toHaveBeenCalledTimes(1);
    });
});
