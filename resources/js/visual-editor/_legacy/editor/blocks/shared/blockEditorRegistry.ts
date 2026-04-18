import type { Editor } from '@tiptap/react';

export type PendingCursor = 'start' | 'end' | number;

const editorsByClientId = new Map<string, Editor>();
const pendingCursors = new Map<string, PendingCursor>();
const listeners = new Set<() => void>();

function notify(): void {
    listeners.forEach((listener) => listener());
}

export function registerBlockEditor(clientId: string, editor: Editor): void {
    editorsByClientId.set(clientId, editor);
    notify();
}

export function unregisterBlockEditor(clientId: string): void {
    if (editorsByClientId.delete(clientId)) {
        notify();
    }
}

export function getBlockEditor(clientId: string): Editor | null {
    return editorsByClientId.get(clientId) ?? null;
}

export function clearBlockEditors(): void {
    editorsByClientId.clear();
    pendingCursors.clear();
    notify();
}

export function setPendingCursor(clientId: string, position: PendingCursor): void {
    pendingCursors.set(clientId, position);
}

export function takePendingCursor(clientId: string): PendingCursor | undefined {
    const position = pendingCursors.get(clientId);
    if (position !== undefined) {
        pendingCursors.delete(clientId);
    }
    return position;
}

export function subscribeBlockEditors(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}
