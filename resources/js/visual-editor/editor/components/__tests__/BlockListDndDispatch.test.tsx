import { act, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';

/**
 * Driving dnd-kit sensors through jsdom is unreliable because jsdom lacks
 * layout: all rects collapse to zero, so the keyboard and pointer sensors
 * can never compute a drop target. Instead, we mock DndContext to capture
 * the drag lifecycle callbacks that BlockList passes in, then invoke them
 * directly with pointer- and keyboard-shaped events. This exercises the
 * real BlockList dispatch contract — the exact code path the sensors feed
 * into — while staying deterministic.
 */

type CapturedHandlers = {
    onDragStart?: (event: DragStartEvent) => void;
    onDragEnd?: (event: DragEndEvent) => void;
    onDragCancel?: () => void;
};

const handlers: CapturedHandlers = {};

vi.mock('@dnd-kit/core', async () => {
    const actual =
        await vi.importActual<typeof import('@dnd-kit/core')>('@dnd-kit/core');
    return {
        ...actual,
        DndContext: ({
            children,
            onDragStart,
            onDragEnd,
            onDragCancel,
        }: {
            children: React.ReactNode;
            onDragStart?: (event: DragStartEvent) => void;
            onDragEnd?: (event: DragEndEvent) => void;
            onDragCancel?: () => void;
        }) => {
            handlers.onDragStart = onDragStart;
            handlers.onDragEnd = onDragEnd;
            handlers.onDragCancel = onDragCancel;
            return <>{children}</>;
        },
        DragOverlay: ({ children }: { children?: React.ReactNode }) => (
            <>{children}</>
        ),
    };
});

import { BlockList } from '../BlockList';
import { EditorStoreProvider } from '../../primitives';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';

function Paragraph({ attributes, clientId }: BlockEditProps) {
    return <p data-client-id={clientId}>{String(attributes.content ?? '')}</p>;
}

function makeBlock(clientId: string, content: string): Block {
    return {
        clientId,
        name: 've/paragraph',
        attributes: { content },
        innerBlocks: [],
    };
}

function renderList(store: EditorStore) {
    return render(
        <EditorStoreProvider store={store}>
            <BlockList />
        </EditorStoreProvider>
    );
}

function pointerDragEvent(activeId: string, overId: string | null): DragEndEvent {
    return {
        active: {
            id: activeId,
            data: { current: undefined },
            rect: { current: { initial: null, translated: null } },
        },
        over:
            overId === null
                ? null
                : {
                      id: overId,
                      rect: {
                          width: 0,
                          height: 0,
                          top: 0,
                          bottom: 0,
                          left: 0,
                          right: 0,
                      },
                      disabled: false,
                      data: { current: undefined },
                  },
        delta: { x: 0, y: 0 },
        collisions: null,
        activatorEvent: new Event('pointerdown'),
    } as unknown as DragEndEvent;
}

function keyboardDragEvent(activeId: string, overId: string | null): DragEndEvent {
    const event = pointerDragEvent(activeId, overId);
    (event as unknown as { activatorEvent: Event }).activatorEvent =
        new KeyboardEvent('keydown', { code: 'Space' });
    return event;
}

function getOrder(store: EditorStore): string[] {
    return store.getState().blocks.map((block) => block.clientId);
}

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 've/paragraph', edit: Paragraph });
    handlers.onDragStart = undefined;
    handlers.onDragEnd = undefined;
    handlers.onDragCancel = undefined;
});

afterEach(() => {
    clearRegistry();
    vi.restoreAllMocks();
});

describe('BlockList drag dispatch', () => {
    it('dispatches moveBlock with the correct target index on pointer drop', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
            makeBlock('c', 'gamma'),
        ]);
        const moveBlockSpy = vi.spyOn(store.getState(), 'moveBlock');

        renderList(store);

        expect(handlers.onDragEnd).toBeDefined();
        act(() => {
            handlers.onDragEnd!(pointerDragEvent('a', 'c'));
        });

        expect(moveBlockSpy).toHaveBeenCalledTimes(1);
        expect(moveBlockSpy).toHaveBeenCalledWith('a', {
            parentClientId: null,
            index: 2,
        });
        expect(getOrder(store)).toEqual(['b', 'c', 'a']);
    });

    it('dispatches moveBlock on keyboard drop with the correct indices', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
            makeBlock('c', 'gamma'),
        ]);
        const moveBlockSpy = vi.spyOn(store.getState(), 'moveBlock');

        renderList(store);

        act(() => {
            handlers.onDragEnd!(keyboardDragEvent('c', 'a'));
        });

        expect(moveBlockSpy).toHaveBeenCalledWith('c', {
            parentClientId: null,
            index: 0,
        });
        expect(getOrder(store)).toEqual(['c', 'a', 'b']);
    });

    it('no-ops when the drop target is the same as the active block', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);
        const moveBlockSpy = vi.spyOn(store.getState(), 'moveBlock');

        renderList(store);

        act(() => {
            handlers.onDragEnd!(pointerDragEvent('a', 'a'));
        });

        expect(moveBlockSpy).not.toHaveBeenCalled();
        expect(getOrder(store)).toEqual(['a', 'b']);
    });

    it('no-ops when the drop is released outside any droppable', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);
        const moveBlockSpy = vi.spyOn(store.getState(), 'moveBlock');

        renderList(store);

        act(() => {
            handlers.onDragEnd!(pointerDragEvent('a', null));
        });

        expect(moveBlockSpy).not.toHaveBeenCalled();
        expect(getOrder(store)).toEqual(['a', 'b']);
    });

    it('tracks active block on drag start for the drag overlay', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);

        renderList(store);

        expect(handlers.onDragStart).toBeDefined();
        expect(() =>
            act(() => {
                handlers.onDragStart!({
                    active: {
                        id: 'a',
                        data: { current: undefined },
                        rect: { current: { initial: null, translated: null } },
                    },
                } as unknown as DragStartEvent);
            })
        ).not.toThrow();
    });

    it('clears active state on drag cancel', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);

        renderList(store);

        expect(handlers.onDragCancel).toBeDefined();
        expect(() =>
            act(() => {
                handlers.onDragCancel!();
            })
        ).not.toThrow();
    });
});
