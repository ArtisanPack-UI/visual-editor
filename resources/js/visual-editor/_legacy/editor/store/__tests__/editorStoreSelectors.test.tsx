import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    createEditorStore,
    useBlock,
    useChildren,
    useIsDirty,
    useSelection,
    type EditorStore,
} from '../editorStore';
import type { Block } from '../types';

function makeBlock(clientId: string, name = 've/paragraph', overrides: Partial<Block> = {}): Block {
    return {
        clientId,
        name,
        attributes: {},
        innerBlocks: [],
        ...overrides,
    };
}

function makeStore(): EditorStore {
    return createEditorStore([
        makeBlock('ql-1', 've/query-loop', {
            attributes: { postType: 'post' },
            innerBlocks: [
                makeBlock('title-1', 've/post-title'),
                makeBlock('para-1', 've/paragraph', { attributes: { content: 'hello' } }),
            ],
        }),
        makeBlock('para-top', 've/paragraph'),
    ]);
}

describe('useBlock', () => {
    it('returns the block for a known top-level id', () => {
        const store = makeStore();
        const { result } = renderHook(() => useBlock(store, 'para-top'));

        expect(result.current?.clientId).toBe('para-top');
    });

    it('returns a nested block by id', () => {
        const store = makeStore();
        const { result } = renderHook(() => useBlock(store, 'para-1'));

        expect(result.current?.attributes.content).toBe('hello');
    });

    it('returns undefined for a missing id', () => {
        const store = makeStore();
        const { result } = renderHook(() => useBlock(store, 'nope'));

        expect(result.current).toBeUndefined();
    });

    it('returns undefined when clientId is null', () => {
        const store = makeStore();
        const { result } = renderHook(() => useBlock(store, null));

        expect(result.current).toBeUndefined();
    });

    it('reflects updated attributes after updateBlockAttributes', () => {
        const store = makeStore();
        const { result } = renderHook(() => useBlock(store, 'para-1'));

        expect(result.current?.attributes.content).toBe('hello');

        act(() => {
            store.getState().updateBlockAttributes('para-1', { content: 'updated' });
        });

        expect(result.current?.attributes.content).toBe('updated');
    });
});

describe('useChildren', () => {
    it('returns top-level blocks when parentClientId is null', () => {
        const store = makeStore();
        const { result } = renderHook(() => useChildren(store, null));

        expect(result.current.map((b) => b.clientId)).toEqual(['ql-1', 'para-top']);
    });

    it('returns children of a nested parent', () => {
        const store = makeStore();
        const { result } = renderHook(() => useChildren(store, 'ql-1'));

        expect(result.current.map((b) => b.clientId)).toEqual(['title-1', 'para-1']);
    });

    it('returns an empty array for an unknown parent', () => {
        const store = makeStore();
        const { result } = renderHook(() => useChildren(store, 'missing'));

        expect(result.current).toEqual([]);
    });

    it('reflects insertions into the parent', () => {
        const store = makeStore();
        const { result } = renderHook(() => useChildren(store, 'ql-1'));

        act(() => {
            store.getState().insertBlock(makeBlock('new'), { parentClientId: 'ql-1' });
        });

        expect(result.current.map((b) => b.clientId)).toEqual(['title-1', 'para-1', 'new']);
    });
});

describe('useSelection', () => {
    it('returns the current selection', () => {
        const store = makeStore();
        const { result } = renderHook(() => useSelection(store));

        expect(result.current).toEqual({ clientId: null });

        act(() => {
            store.getState().select('para-top', 'end');
        });

        expect(result.current).toEqual({ clientId: 'para-top', edge: 'end' });
    });
});

describe('useIsDirty', () => {
    it('reflects the dirty flag reactively', () => {
        const store = makeStore();
        const { result } = renderHook(() => useIsDirty(store));

        expect(result.current).toBe(false);

        act(() => {
            store.getState().insertBlock(makeBlock('new'));
        });

        expect(result.current).toBe(true);

        act(() => {
            store.getState().markClean();
        });

        expect(result.current).toBe(false);
    });
});
