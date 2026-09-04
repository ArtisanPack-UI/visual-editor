/**
 * Tests for `useBusinessInfo` (#761).
 *
 * The hook is the editor's shared fetch/cache seam for the business-info
 * envelope. It targets `/visual-editor/api/business-info`, dedupes
 * concurrent callers through an in-flight promise cache, and threads
 * per-block attribute overrides through as query parameters.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';

import {
    useBusinessInfo,
    __resetBusinessInfoCache,
} from '../use-business-info';

function jsonResponse(body: unknown): Response {
    return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    __resetBusinessInfoCache();
});

describe('useBusinessInfo', () => {
    it('fetches the envelope from the default endpoint and exposes it on the result', async () => {
        const fetcher = vi.fn().mockResolvedValue(
            jsonResponse({ phone: '+1 555-0000', email: 'hi@example.test' })
        );

        const { result } = renderHook(() => useBusinessInfo({ fetcher }));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.envelope).toEqual({
            phone: '+1 555-0000',
            email: 'hi@example.test',
        });
        expect(result.current.error).toBeNull();
        expect(fetcher).toHaveBeenCalledTimes(1);
        expect(fetcher.mock.calls[0][0]).toBe('/visual-editor/api/business-info');
    });

    it('threads the address / hours overrides through as query parameters', async () => {
        const fetcher = vi.fn().mockResolvedValue(jsonResponse({}));

        renderHook(() =>
            useBusinessInfo({
                fetcher,
                mapProvider: 'google',
                showMap: false,
                zoom: 17,
                specialHoursWindowDays: 14,
            })
        );

        await waitFor(() => expect(fetcher).toHaveBeenCalled());

        const url = fetcher.mock.calls[0][0] as string;
        expect(url).toContain('mapProvider=google');
        expect(url).toContain('showMap=0');
        expect(url).toContain('zoom=17');
        expect(url).toContain('specialHoursWindowDays=14');
    });

    it('does not infinitely re-render when no fetcher is supplied (default fetcher memoized)', async () => {
        // Spy on globalThis.fetch. Without memoizing the default
        // `fetch.bind( globalThis )` fallback, every render allocates
        // a fresh function reference → the effect deps change → the
        // effect re-runs → setLoading re-renders → "Too many re-renders".
        const spy = vi.fn().mockResolvedValue(jsonResponse({ phone: '+1 default' }));
        const originalFetch = globalThis.fetch;
        // @ts-expect-error — override for the test
        globalThis.fetch = spy;

        try {
            const { rerender, result } = renderHook(() => useBusinessInfo());

            // Force a re-render — a stable fetcher means the effect
            // must NOT fire again for this second render.
            rerender();

            await waitFor(() => {
                expect(result.current.loading).toBe(false);
            });

            // The in-flight cache should also mean the effect body
            // only kicks off one fetch across both renders.
            expect(spy).toHaveBeenCalledTimes(1);
        } finally {
            // @ts-expect-error — restore
            globalThis.fetch = originalFetch;
        }
    });

    it('does not evict a newer cached promise when an older one rejects', async () => {
        // Two calls to the same URL. The first rejects; between its
        // fetch starting and its rejection settling, a second call
        // replaces the cache entry with a fresh in-flight promise
        // (simulated here by clearing the cache and firing a second
        // hook whose fetcher resolves). The first's rejection cleanup
        // must NOT evict the second's cached entry.
        let rejectFirst: ((reason: Error) => void) | null = null;
        const firstFetcher = vi.fn().mockImplementation(
            () =>
                new Promise<Response>((_resolve, reject) => {
                    rejectFirst = reject;
                })
        );

        const first = renderHook(() => useBusinessInfo({ fetcher: firstFetcher }));

        // Give the effect a tick.
        await waitFor(() => expect(firstFetcher).toHaveBeenCalledTimes(1));

        // Simulate a caller between the first fetch and its rejection
        // that installs a newer entry for the same URL.
        __resetBusinessInfoCache();

        const secondFetcher = vi.fn().mockResolvedValue(
            jsonResponse({ phone: '+1 second' })
        );
        const second = renderHook(() =>
            useBusinessInfo({ fetcher: secondFetcher })
        );

        await waitFor(() => expect(secondFetcher).toHaveBeenCalledTimes(1));

        // NOW settle the first as a failure — this used to unconditionally
        // wipe the cache entry the second call installed.
        rejectFirst!(new Error('boom'));

        await waitFor(() => {
            expect(second.result.current.loading).toBe(false);
        });

        // The second call's promise must have resolved with its own
        // envelope — proving its cache entry survived the older
        // promise's rejection cleanup.
        expect(second.result.current.envelope).toEqual({ phone: '+1 second' });
        expect(second.result.current.error).toBeNull();

        // First hook records the error but never leaks the second's data.
        await waitFor(() => {
            expect(first.result.current.error).not.toBeNull();
        });
    });

    it('shares the in-flight promise between concurrent callers with the same URL', async () => {
        const fetcher = vi.fn().mockResolvedValue(jsonResponse({ phone: '+1' }));

        const first  = renderHook(() => useBusinessInfo({ fetcher }));
        const second = renderHook(() => useBusinessInfo({ fetcher }));

        await waitFor(() => {
            expect(first.result.current.loading).toBe(false);
            expect(second.result.current.loading).toBe(false);
        });

        expect(fetcher).toHaveBeenCalledTimes(1);
    });
});
