/**
 * `useSiteEditorDeepLink` (#625) turns a mount-time
 * `?entity=template&slug=…` into a router navigation. The list endpoint
 * is stubbed so the suite asserts the resolve → navigate → toast wiring
 * rather than re-testing `api-client.ts`.
 */

import { render, screen, waitFor } from '@testing-library/react';
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
    });

    it('navigates to the resolved template on mount', async () => {
        listEntities.mockResolvedValue([{ id: 12, slug: 'single' }]);

        const { navigate } = renderHarness('?entity=template&slug=single');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', '12');
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
            expect(navigate).toHaveBeenCalledWith('templates', '7');
        });
    });

    it('lands on the templates index with a toast when the slug is unknown', async () => {
        listEntities.mockResolvedValue([]);

        const { navigate } = renderHarness('?entity=template&slug=nope');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', null);
        });

        expect(
            await screen.findByText('The template “nope” was not found.')
        ).toBeInTheDocument();
    });

    it('lands on the templates index with a toast when the lookup fails', async () => {
        listEntities.mockRejectedValue(new Error('network down'));

        const { navigate } = renderHarness('?entity=template&slug=single');

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('templates', null);
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
            expect(navigate).toHaveBeenCalledWith('templates', '42');
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
