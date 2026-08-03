import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAppliedTemplate } from '../use-applied-template';

const originalFetch = globalThis.fetch;

function mockFetch(
    responses: Array<{ status: number; body: unknown }>
): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn();

    for (const { status, body } of responses) {
        fetchMock.mockImplementationOnce(async () => ({
            ok: status >= 200 && status < 300,
            status,
            text: async () => JSON.stringify(body),
        }));
    }

    globalThis.fetch = fetchMock as unknown as typeof fetch;

    return fetchMock;
}

const CONFIG = {
    apiBase: '/visual-editor/api',
    resource: 'pages',
    id: '5',
};

beforeEach(() => {
    globalThis.fetch = originalFetch;
});

afterEach(() => {
    vi.restoreAllMocks();
    globalThis.fetch = originalFetch;
});

describe('useAppliedTemplate', () => {
    it('stays idle until enabled', () => {
        const fetchMock = mockFetch([]);

        const { result } = renderHook(() =>
            useAppliedTemplate({ ...CONFIG, enabled: false })
        );

        expect(result.current.status).toBe('idle');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('transitions loading → ok on a successful fetch', async () => {
        mockFetch([
            {
                status: 200,
                body: {
                    status: 'ok',
                    slug: 'single-post',
                    name: 'Single Post',
                    source: 'theme',
                    blocks: [],
                    template_parts: {},
                },
            },
        ]);

        const { result } = renderHook(() =>
            useAppliedTemplate({ ...CONFIG, enabled: true })
        );

        await waitFor(() => {
            expect(result.current.status).toBe('ok');
        });

        if (result.current.status === 'ok') {
            expect(result.current.template.slug).toBe('single-post');
        }
    });

    it('transitions loading → missing on the discriminated 200 payload', async () => {
        mockFetch([
            {
                status: 200,
                body: { status: 'missing', reason: 'unknown-slug', slug: 'x' },
            },
        ]);

        const { result } = renderHook(() =>
            useAppliedTemplate({ ...CONFIG, enabled: true })
        );

        await waitFor(() => {
            expect(result.current.status).toBe('missing');
        });

        if (result.current.status === 'missing') {
            expect(result.current.missing.reason).toBe('unknown-slug');
        }
    });

    it('caches ok result for the same (resource, id) across enable toggles', async () => {
        const fetchMock = mockFetch([
            {
                status: 200,
                body: {
                    status: 'ok',
                    slug: 's',
                    name: 'S',
                    source: 'theme',
                    blocks: [],
                    template_parts: {},
                },
            },
        ]);

        const { result, rerender } = renderHook(
            ({ enabled }: { enabled: boolean }) =>
                useAppliedTemplate({ ...CONFIG, enabled }),
            { initialProps: { enabled: true } }
        );

        await waitFor(() => {
            expect(result.current.status).toBe('ok');
        });

        // Disable, then re-enable — the second fetch must NOT run because
        // the cache hits.
        act(() => rerender({ enabled: false }));
        act(() => rerender({ enabled: true }));

        await waitFor(() => {
            expect(result.current.status).toBe('ok');
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('refetches when the selected template changes', async () => {
        const fetchMock = mockFetch([
            {
                status: 200,
                body: {
                    status: 'ok',
                    slug: 'single-post',
                    name: 'Single Post',
                    source: 'theme',
                    blocks: [],
                    template_parts: {},
                },
            },
            {
                status: 200,
                body: {
                    status: 'ok',
                    slug: 'full-width',
                    name: 'Full Width',
                    source: 'theme',
                    blocks: [],
                    template_parts: {},
                },
            },
        ]);

        const { result, rerender } = renderHook(
            ({ template }: { template: string }) =>
                useAppliedTemplate({ ...CONFIG, template, enabled: true }),
            { initialProps: { template: 'single-post' } }
        );

        await waitFor(() => {
            expect(result.current.status).toBe('ok');
        });

        act(() => rerender({ template: 'full-width' }));

        await waitFor(() => {
            expect(
                result.current.status === 'ok' &&
                    result.current.template.slug === 'full-width'
            ).toBe(true);
        });

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('sends the selected template as a query param so the response is not gated on the debounced save', async () => {
        const fetchMock = mockFetch([
            {
                status: 200,
                body: {
                    status: 'ok',
                    slug: 'full-width',
                    name: 'Full Width',
                    source: 'theme',
                    blocks: [],
                    template_parts: {},
                },
            },
        ]);

        const { result } = renderHook(() =>
            useAppliedTemplate({
                ...CONFIG,
                template: 'full-width',
                enabled: true,
            })
        );

        await waitFor(() => {
            expect(result.current.status).toBe('ok');
        });

        expect(String(fetchMock.mock.calls[0][0])).toContain(
            'template=full-width'
        );
    });

    it('sends a blank template param when the selection has been cleared', async () => {
        const fetchMock = mockFetch([
            { status: 200, body: { status: 'missing', reason: 'empty' } },
        ]);

        const { result } = renderHook(() =>
            useAppliedTemplate({ ...CONFIG, template: '', enabled: true })
        );

        await waitFor(() => {
            expect(result.current.status).toBe('missing');
        });

        // Blank is meaningful — it must reach the server rather than being
        // dropped, or the endpoint falls back to the stale persisted slug.
        expect(String(fetchMock.mock.calls[0][0])).toContain('?template=');
    });

    it('omits the template param entirely when no selection is supplied', async () => {
        const fetchMock = mockFetch([
            {
                status: 200,
                body: {
                    status: 'ok',
                    slug: 's',
                    name: 'S',
                    source: 'theme',
                    blocks: [],
                    template_parts: {},
                },
            },
        ]);

        const { result } = renderHook(() =>
            useAppliedTemplate({ ...CONFIG, enabled: true })
        );

        await waitFor(() => {
            expect(result.current.status).toBe('ok');
        });

        expect(String(fetchMock.mock.calls[0][0])).not.toContain('template=');
    });

    it('surfaces a malformed ok payload as an error rather than a bogus ok', async () => {
        mockFetch([{ status: 200, body: { status: 'ok' } }]);

        const { result } = renderHook(() =>
            useAppliedTemplate({ ...CONFIG, enabled: true })
        );

        await waitFor(() => {
            expect(result.current.status).toBe('error');
        });
    });
});
