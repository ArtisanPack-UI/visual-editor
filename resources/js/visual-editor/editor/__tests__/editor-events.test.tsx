import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import {
    VE_EDITOR_AUTOSAVE,
    VE_EDITOR_CHANGE,
    VE_EDITOR_SAVE,
    type VeEditorAutosaveDetail,
    type VeEditorChangeDetail,
    type VeEditorSaveDetail,
} from '../editor-events';
import { usePersistence } from '../use-persistence';

const CONFIG = {
    apiBase: '/visual-editor/api',
    resource: 'posts',
    id: '42',
    debounceMs: 20,
};

function mockFetch(saveTimestamp: string): ReturnType<typeof vi.fn> {
    const mock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (method === 'GET') {
            return new Response(
                JSON.stringify({
                    id: 42,
                    resource: 'posts',
                    blocks: [],
                    updated_at: null,
                }),
                { status: 200, headers: { 'content-type': 'application/json' } }
            );
        }

        return new Response(
            JSON.stringify({
                id: 42,
                resource: 'posts',
                blocks: [],
                updated_at: saveTimestamp,
            }),
            { status: 200, headers: { 'content-type': 'application/json' } }
        );
    });

    vi.stubGlobal('fetch', mock);
    return mock;
}

interface CapturedEvent<Detail> {
    name: string;
    detail: Detail;
}

function captureEvents(): {
    change: CapturedEvent<VeEditorChangeDetail>[];
    autosave: CapturedEvent<VeEditorAutosaveDetail>[];
    save: CapturedEvent<VeEditorSaveDetail>[];
    cleanup: () => void;
} {
    const change: CapturedEvent<VeEditorChangeDetail>[] = [];
    const autosave: CapturedEvent<VeEditorAutosaveDetail>[] = [];
    const save: CapturedEvent<VeEditorSaveDetail>[] = [];

    const onChange = (event: Event): void => {
        change.push({
            name: event.type,
            detail: (event as CustomEvent<VeEditorChangeDetail>).detail,
        });
    };
    const onAutosave = (event: Event): void => {
        autosave.push({
            name: event.type,
            detail: (event as CustomEvent<VeEditorAutosaveDetail>).detail,
        });
    };
    const onSave = (event: Event): void => {
        save.push({
            name: event.type,
            detail: (event as CustomEvent<VeEditorSaveDetail>).detail,
        });
    };

    window.addEventListener(VE_EDITOR_CHANGE, onChange);
    window.addEventListener(VE_EDITOR_AUTOSAVE, onAutosave);
    window.addEventListener(VE_EDITOR_SAVE, onSave);

    return {
        change,
        autosave,
        save,
        cleanup: () => {
            window.removeEventListener(VE_EDITOR_CHANGE, onChange);
            window.removeEventListener(VE_EDITOR_AUTOSAVE, onAutosave);
            window.removeEventListener(VE_EDITOR_SAVE, onSave);
        },
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('editor CustomEvents', () => {
    it('exposes stable event name constants', () => {
        expect(VE_EDITOR_CHANGE).toBe('ve:editor:change');
        expect(VE_EDITOR_AUTOSAVE).toBe('ve:editor:autosave');
        expect(VE_EDITOR_SAVE).toBe('ve:editor:save');
    });

    it('fires ve:editor:change once per debounce window with the pending block tree', async () => {
        mockFetch('2026-04-20T10:00:00Z');
        const events = captureEvents();

        try {
            const { result } = renderHook(() => usePersistence(CONFIG));

            await waitFor(() => {
                expect(result.current.loadStatus).toBe('ready');
            });

            act(() => {
                result.current.onBlocksChange([
                    { clientId: 'a', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
                ]);
                result.current.onBlocksChange([
                    { clientId: 'b', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
                ]);
                result.current.onBlocksChange([
                    { clientId: 'c', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
                ]);
            });

            await waitFor(() => {
                expect(events.change).toHaveLength(1);
            });

            const [firstChange] = events.change;

            expect(firstChange?.detail).toEqual({
                resource: 'posts',
                id: '42',
                blocks: [
                    expect.objectContaining({ clientId: 'c', name: 'core/paragraph' }),
                ],
            });
        } finally {
            events.cleanup();
        }
    });

    it('fires ve:editor:autosave after a debounced save completes', async () => {
        mockFetch('2026-04-20T11:00:00Z');
        const events = captureEvents();

        try {
            const { result } = renderHook(() => usePersistence(CONFIG));

            await waitFor(() => {
                expect(result.current.loadStatus).toBe('ready');
            });

            act(() => {
                result.current.onBlocksChange([
                    { clientId: 'auto', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
                ]);
            });

            await waitFor(() => {
                expect(events.autosave).toHaveLength(1);
            });

            expect(events.save).toHaveLength(0);
            expect(events.autosave[0]?.detail).toEqual({
                resource: 'posts',
                id: '42',
                blocks: [
                    expect.objectContaining({ clientId: 'auto', name: 'core/paragraph' }),
                ],
                updatedAt: '2026-04-20T11:00:00Z',
            });
        } finally {
            events.cleanup();
        }
    });

    it('fires ve:editor:save — not ve:editor:autosave — when flush() is the trigger', async () => {
        mockFetch('2026-04-20T12:00:00Z');
        const events = captureEvents();

        try {
            const { result } = renderHook(() =>
                usePersistence({ ...CONFIG, debounceMs: 5000 })
            );

            await waitFor(() => {
                expect(result.current.loadStatus).toBe('ready');
            });

            act(() => {
                result.current.onBlocksChange([
                    { clientId: 'manual', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
                ]);
            });

            act(() => {
                result.current.flush();
            });

            await waitFor(() => {
                expect(events.save).toHaveLength(1);
            });

            expect(events.autosave).toHaveLength(0);
            expect(events.save[0]?.detail).toEqual({
                resource: 'posts',
                id: '42',
                blocks: [
                    expect.objectContaining({ clientId: 'manual', name: 'core/paragraph' }),
                ],
                updatedAt: '2026-04-20T12:00:00Z',
            });
        } finally {
            events.cleanup();
        }
    });

    it('does not fire save events when the request fails', async () => {
        const mock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();

            if (method === 'GET') {
                return new Response(
                    JSON.stringify({ id: 42, resource: 'posts', blocks: [], updated_at: null }),
                    { status: 200, headers: { 'content-type': 'application/json' } }
                );
            }

            return new Response(JSON.stringify({ message: 'nope' }), {
                status: 422,
                headers: { 'content-type': 'application/json' },
            });
        });
        vi.stubGlobal('fetch', mock);

        const events = captureEvents();

        try {
            const { result } = renderHook(() => usePersistence(CONFIG));

            await waitFor(() => {
                expect(result.current.loadStatus).toBe('ready');
            });

            act(() => {
                result.current.onBlocksChange([
                    { clientId: 'fail', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
                ]);
            });

            await waitFor(() => {
                expect(result.current.saveStatus).toBe('error');
            });

            expect(events.autosave).toHaveLength(0);
            expect(events.save).toHaveLength(0);
            // Change events still fire — they describe editing activity, not
            // persistence. Hosts should reconcile them against saveStatus.
            expect(events.change).toHaveLength(1);
        } finally {
            events.cleanup();
        }
    });
});
