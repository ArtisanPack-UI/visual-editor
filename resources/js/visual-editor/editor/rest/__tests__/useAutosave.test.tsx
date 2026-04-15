import { act, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

async function flushMicrotasks(): Promise<void> {
    // `vi.advanceTimersByTimeAsync` resolves timers but pending fetch
    // promises (and the state updates that follow them) still need a tick
    // to settle. Looping over a handful of microtasks is enough to let
    // React flush the resulting state updates.
    for (let i = 0; i < 20; i += 1) {
        await Promise.resolve();
    }
}
import { useAutosave, type AutosaveState } from '../useAutosave';
import { createEditorStore, type Block, type EditorStore } from '../../store';

function makeBlock(clientId: string, content: string): Block {
    return {
        clientId,
        name: 'core/paragraph',
        attributes: { content },
        innerBlocks: [],
    };
}

interface HarnessProps {
    store: EditorStore;
    fetchImpl: typeof fetch;
    onState?: (state: ReturnType<typeof useAutosave>) => void;
    debounceMs?: number;
    retryBaseMs?: number;
    maxRetries?: number;
}

function AutosaveHarness({
    store,
    fetchImpl,
    onState,
    debounceMs = 1000,
    retryBaseMs = 100,
    maxRetries = 3,
}: HarnessProps) {
    const state = useAutosave({
        store,
        postId: '1',
        clientOptions: { apiBase: '/visual-editor/api', fetchImpl },
        debounceMs,
        retryBaseMs,
        maxRetries,
    });

    if (onState) {
        onState(state);
    }

    return <div data-testid="status">{state.status}</div>;
}

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

function successResponse(): Response {
    return new Response(
        JSON.stringify({
            id: 1,
            title: 'Test',
            blocks: [],
            updated_at: '2026-04-14T12:00:00+00:00',
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } }
    );
}

describe('useAutosave', () => {
    it('debounces saves and clears the dirty flag on success', async () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        const fetchImpl = vi.fn().mockResolvedValue(successResponse());

        render(<AutosaveHarness store={store} fetchImpl={fetchImpl} />);

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        expect(store.getState().isDirty).toBe(true);
        expect(fetchImpl).not.toHaveBeenCalled();

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1000);
        });

        expect(fetchImpl).toHaveBeenCalledTimes(1);
        const [url, init] = fetchImpl.mock.calls[0];
        expect(url).toBe('/visual-editor/api/posts/1');
        expect(init.method).toBe('PUT');

        const body = JSON.parse(init.body as string);
        expect(body.blocks).toHaveLength(2);

        await act(async () => {
            await flushMicrotasks();
        });

        expect(store.getState().isDirty).toBe(false);
    });

    it('does not save when nothing is dirty', async () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        const fetchImpl = vi.fn().mockResolvedValue(successResponse());

        render(<AutosaveHarness store={store} fetchImpl={fetchImpl} />);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(5000);
        });

        expect(fetchImpl).not.toHaveBeenCalled();
    });

    it('reports error status and keeps dirty flag when save fails', async () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        const fetchImpl = vi
            .fn()
            .mockResolvedValue(
                new Response(JSON.stringify({ message: 'boom' }), { status: 500 })
            );

        const stateRef: { current: AutosaveState | null } = { current: null };

        render(
            <AutosaveHarness
                store={store}
                fetchImpl={fetchImpl}
                onState={(state) => {
                    stateRef.current = state;
                }}
            />
        );

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1000);
        });

        await act(async () => {
            await flushMicrotasks();
        });

        expect(stateRef.current?.status).toBe('error');
        expect(store.getState().isDirty).toBe(true);
        expect(stateRef.current?.retryCount).toBe(1);
    });

    it('saves every time the store becomes dirty, not just the first edit', async () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        const fetchImpl = vi.fn().mockImplementation(() => Promise.resolve(successResponse()));

        render(
            <AutosaveHarness store={store} fetchImpl={fetchImpl} debounceMs={500} />
        );

        // First edit + save cycle.
        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        await act(async () => {
            await vi.advanceTimersByTimeAsync(500);
            await flushMicrotasks();
        });

        expect(fetchImpl).toHaveBeenCalledTimes(1);
        expect(store.getState().isDirty).toBe(false);

        // Second edit should trigger a brand new debounce + save cycle.
        act(() => {
            store.getState().insertBlock(makeBlock('c', 'gamma'));
        });

        expect(store.getState().isDirty).toBe(true);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(500);
            await flushMicrotasks();
        });

        expect(fetchImpl).toHaveBeenCalledTimes(2);
        expect(store.getState().isDirty).toBe(false);

        // And a third edit, for good measure.
        act(() => {
            store.getState().insertBlock(makeBlock('d', 'delta'));
        });

        await act(async () => {
            await vi.advanceTimersByTimeAsync(500);
            await flushMicrotasks();
        });

        expect(fetchImpl).toHaveBeenCalledTimes(3);
        expect(store.getState().isDirty).toBe(false);
    });

    it('retries with exponential backoff up to maxRetries', async () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);

        const fetchImpl = vi
            .fn()
            .mockResolvedValueOnce(new Response('', { status: 500 }))
            .mockResolvedValueOnce(new Response('', { status: 500 }))
            .mockResolvedValue(successResponse());

        render(
            <AutosaveHarness
                store={store}
                fetchImpl={fetchImpl}
                debounceMs={200}
                retryBaseMs={50}
                maxRetries={5}
            />
        );

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        // First attempt after initial debounce.
        await act(async () => {
            await vi.advanceTimersByTimeAsync(200);
            await flushMicrotasks();
        });
        expect(fetchImpl).toHaveBeenCalledTimes(1);

        // Retry 1 scheduled at retryBaseMs * 2^0 = 50ms.
        await act(async () => {
            await vi.advanceTimersByTimeAsync(50);
            await flushMicrotasks();
        });
        expect(fetchImpl).toHaveBeenCalledTimes(2);

        // Retry 2 scheduled at retryBaseMs * 2^1 = 100ms.
        await act(async () => {
            await vi.advanceTimersByTimeAsync(100);
            await flushMicrotasks();
        });
        expect(fetchImpl).toHaveBeenCalledTimes(3);

        expect(store.getState().isDirty).toBe(false);
    });
});
