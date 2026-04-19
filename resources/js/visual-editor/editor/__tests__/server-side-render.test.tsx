import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ServerSideRender } from '../server-side-render';

interface FetchCall {
    url: string;
    init: RequestInit;
}

function installFetchMock(): {
    resolve: (body: unknown, status?: number) => void;
    reject: (error: unknown) => void;
    calls: FetchCall[];
    aborts: number;
} {
    const calls: FetchCall[] = [];
    let pendingResolve: ((value: Response) => void) | null = null;
    let pendingReject: ((error: unknown) => void) | null = null;
    let aborts = 0;

    vi.stubGlobal(
        'fetch',
        vi.fn((url: string, init: RequestInit = {}) => {
            calls.push({ url, init });

            return new Promise<Response>((resolve, reject) => {
                pendingResolve = resolve;
                pendingReject = reject;

                const signal = init.signal;

                if (signal) {
                    signal.addEventListener('abort', () => {
                        aborts += 1;
                        reject(new DOMException('aborted', 'AbortError'));
                    });
                }
            });
        })
    );

    return {
        calls,
        get aborts(): number {
            return aborts;
        },
        resolve(body: unknown, status = 200): void {
            pendingResolve?.(
                new Response(JSON.stringify(body), {
                    status,
                    headers: { 'content-type': 'application/json' },
                })
            );
        },
        reject(error: unknown): void {
            pendingReject?.(error);
        },
    };
}

describe('ServerSideRender', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('shows the loading placeholder before the first response arrives', () => {
        installFetchMock();

        render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'hi' }}
                loadingPlaceholder={<span>loading…</span>}
                debounceMs={100}
            />
        );

        expect(screen.getByText('loading…')).toBeDefined();
    });

    it('debounces rapid attribute changes into a single fetch', async () => {
        const mock = installFetchMock();

        const { rerender } = render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'a' }}
                debounceMs={200}
            />
        );

        rerender(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'ab' }}
                debounceMs={200}
            />
        );

        rerender(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'abc' }}
                debounceMs={200}
            />
        );

        await act(async () => {
            vi.advanceTimersByTime(199);
        });

        expect(mock.calls).toHaveLength(0);

        await act(async () => {
            vi.advanceTimersByTime(1);
        });

        expect(mock.calls).toHaveLength(1);
        expect(mock.calls[0].url).toBe('/visual-editor/api/blocks/preview');

        const payload = JSON.parse(mock.calls[0].init.body as string);
        expect(payload).toEqual({ name: 'acme/example', attributes: { text: 'abc' } });
    });

    it('renders the returned HTML once the fetch resolves', async () => {
        const mock = installFetchMock();

        render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'hi' }}
                debounceMs={50}
            />
        );

        await act(async () => {
            vi.advanceTimersByTime(50);
        });

        await act(async () => {
            mock.resolve({ name: 'acme/example', html: '<p>hello</p>' });
            await vi.runAllTimersAsync();
        });

        expect(document.querySelector('[data-status="ready"]')?.innerHTML).toBe(
            '<p>hello</p>'
        );
    });

    it('aborts the in-flight request when attributes change again', async () => {
        const mock = installFetchMock();

        const { rerender } = render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'first' }}
                debounceMs={50}
            />
        );

        await act(async () => {
            vi.advanceTimersByTime(50);
        });

        expect(mock.calls).toHaveLength(1);

        rerender(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'second' }}
                debounceMs={50}
            />
        );

        await act(async () => {
            vi.advanceTimersByTime(50);
        });

        expect(mock.aborts).toBeGreaterThanOrEqual(1);
        expect(mock.calls).toHaveLength(2);
    });

    it('surfaces an error placeholder when the fetch fails', async () => {
        const mock = installFetchMock();

        render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'hi' }}
                debounceMs={10}
                errorPlaceholder={(error) => <span role="alert">{error.message}</span>}
            />
        );

        await act(async () => {
            vi.advanceTimersByTime(10);
        });

        await act(async () => {
            mock.resolve({ error: 'block_not_registered' }, 404);
            await vi.runAllTimersAsync();
        });

        expect(screen.getByRole('alert').textContent).toContain('404');
    });
});
