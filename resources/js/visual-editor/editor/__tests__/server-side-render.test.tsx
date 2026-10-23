import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { clearBlockPreviewCache } from '../block-preview-batcher';
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
        clearBlockPreviewCache();
    });

    /** Advance past the instance debounce and the shared batch window. */
    async function flushQueue(debounceMs: number): Promise<void> {
        await act(async () => {
            vi.advanceTimersByTime(debounceMs + 50);
        });
    }

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
            vi.advanceTimersByTime(51);
        });

        expect(mock.calls).toHaveLength(1);
        expect(mock.calls[0].url).toBe('/visual-editor/api/blocks/preview');

        const payload = JSON.parse(mock.calls[0].init.body as string);
        expect(payload).toEqual({
            items: [{ key: '0', name: 'acme/example', attributes: { text: 'abc' } }],
        });
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

        await flushQueue(50);

        await act(async () => {
            mock.resolve({ results: [{ key: '0', name: 'acme/example', html: '<p>hello</p>' }] });
            await vi.runAllTimersAsync();
        });

        expect(document.querySelector('[data-status="ready"]')?.innerHTML).toBe(
            '<p>hello</p>'
        );
    });

    it('ignores a stale response when attributes change again', async () => {
        const mock = installFetchMock();

        const { rerender } = render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'first' }}
                debounceMs={50}
            />
        );

        await flushQueue(50);

        expect(mock.calls).toHaveLength(1);

        rerender(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'second' }}
                debounceMs={50}
            />
        );

        await act(async () => {
            mock.resolve({ results: [{ key: '0', name: 'acme/example', html: '<p>first</p>' }] });
            await Promise.resolve();
        });

        expect(document.body.innerHTML).not.toContain('<p>first</p>');

        await flushQueue(50);

        expect(mock.calls).toHaveLength(2);
        expect(JSON.parse(mock.calls[1].init.body as string).items[0].attributes).toEqual({
            text: 'second',
        });
    });

    it('paints a cached render straight away without a request', async () => {
        const mock = installFetchMock();

        const first = render(
            <ServerSideRender block="acme/example" attributes={{ text: 'hi' }} debounceMs={10} />
        );

        await flushQueue(10);

        await act(async () => {
            mock.resolve({ results: [{ key: '0', name: 'acme/example', html: '<p>hi</p>' }] });
            await vi.runAllTimersAsync();
        });

        first.unmount();

        render(<ServerSideRender block="acme/example" attributes={{ text: 'hi' }} debounceMs={10} />);

        expect(document.querySelector('[data-status="ready"]')?.innerHTML).toBe('<p>hi</p>');

        await flushQueue(10);

        expect(mock.calls).toHaveLength(1);
    });

    it('surfaces a per-item error from the batch', async () => {
        const mock = installFetchMock();

        render(
            <ServerSideRender
                block="acme/missing"
                debounceMs={10}
                errorPlaceholder={(error) => <span role="alert">{error.message}</span>}
            />
        );

        await flushQueue(10);

        await act(async () => {
            mock.resolve({
                results: [
                    { key: '0', name: 'acme/missing', error: 'block_not_registered', status: 404 },
                ],
            });
            await vi.runAllTimersAsync();
        });

        expect(screen.getByRole('alert').textContent).toContain('404');
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

        await flushQueue(10);

        await act(async () => {
            mock.resolve({ message: 'Server Error' }, 500);
            await vi.runAllTimersAsync();
        });

        expect(screen.getByRole('alert').textContent).toContain('500');
    });

    it('sends identical instances as one item in one request', async () => {
        const mock = installFetchMock();

        render(
            <>
                {Array.from({ length: 13 }, (_, index) => (
                    <ServerSideRender
                        key={index}
                        block="acme/copy-command"
                        attributes={{ command: 'composer install' }}
                        debounceMs={10}
                    />
                ))}
            </>
        );

        await flushQueue(10);

        expect(mock.calls).toHaveLength(1);
        expect(JSON.parse(mock.calls[0].init.body as string).items).toHaveLength(1);

        await act(async () => {
            mock.resolve({ results: [{ key: '0', html: '<code>composer install</code>' }] });
            await vi.runAllTimersAsync();
        });

        expect(document.querySelectorAll('[data-status="ready"]')).toHaveLength(13);
    });

    it('batches distinct instances into one request', async () => {
        const mock = installFetchMock();

        render(
            <>
                {['a', 'b', 'c'].map((text) => (
                    <ServerSideRender
                        key={text}
                        block="acme/example"
                        attributes={{ text }}
                        debounceMs={10}
                    />
                ))}
            </>
        );

        await flushQueue(10);

        expect(mock.calls).toHaveLength(1);
        expect(
            JSON.parse(mock.calls[0].init.body as string).items.map(
                (item: { attributes: { text: string } }) => item.attributes.text
            )
        ).toEqual(['a', 'b', 'c']);
    });

    it('does not request an off-screen block until it nears the viewport', async () => {
        const mock = installFetchMock();
        const observers: FakeIntersectionObserver[] = [];

        class FakeIntersectionObserver {
            readonly elements: Element[] = [];

            constructor(readonly callback: IntersectionObserverCallback) {
                observers.push(this);
            }

            observe(element: Element): void {
                this.elements.push(element);
            }

            disconnect(): void {
                this.elements.length = 0;
            }

            trigger(isIntersecting: boolean): void {
                this.callback(
                    this.elements.map(
                        (target) => ({ isIntersecting, target }) as IntersectionObserverEntry
                    ),
                    this as unknown as IntersectionObserver
                );
            }
        }

        vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver);

        render(
            <ServerSideRender
                block="acme/example"
                attributes={{ text: 'below the fold' }}
                debounceMs={10}
                loadingPlaceholder={<span>placeholder</span>}
            />
        );

        await flushQueue(10);

        expect(mock.calls).toHaveLength(0);
        expect(screen.getByText('placeholder')).toBeDefined();
        expect(document.querySelector('[data-status="idle"]')).not.toBeNull();

        await act(async () => {
            observers[0]?.trigger(false);
        });

        await flushQueue(10);

        expect(mock.calls).toHaveLength(0);

        await act(async () => {
            observers[0]?.trigger(true);
        });

        await flushQueue(10);

        expect(mock.calls).toHaveLength(1);
    });
});
