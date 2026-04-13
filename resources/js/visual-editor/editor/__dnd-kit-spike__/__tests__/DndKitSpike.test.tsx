import { act, render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { getTiptapEditor } from '@spike/richtext/useTiptap';
import { DndKitSpike } from '../DndKitSpike';

function getTiptapDom(testId: string): HTMLElement {
    const block = screen.getByTestId(testId);
    const dom = block.querySelector<HTMLElement>('.ve-richtext.ProseMirror');
    expect(dom).not.toBeNull();
    return dom!;
}

describe('DndKitSpike harness', () => {
    it('mounts a Tiptap editor inside every sortable block', () => {
        render(<DndKitSpike />);

        const list = screen.getByTestId('spike-list');
        const editors = list.querySelectorAll('.ve-richtext.ProseMirror');
        expect(editors.length).toBe(4);

        expect(screen.getByText(/Alpha/)).toBeInTheDocument();
        expect(screen.getByText(/Bravo/)).toBeInTheDocument();
        expect(screen.getByText(/Charlie/)).toBeInTheDocument();
        expect(screen.getByText(/Delta/)).toBeInTheDocument();
    });

    it('exposes a drag handle separate from the Tiptap editor surface', () => {
        render(<DndKitSpike />);

        const block = screen.getByTestId('spike-block-block-a');
        const handle = within(block).getByTestId('spike-handle-block-a');
        const editorDom = getTiptapDom('spike-block-block-a');

        // Handle and editor are siblings, not ancestors of each other —
        // that is what keeps dnd-kit pointer activation off of the
        // contenteditable surface.
        expect(handle.contains(editorDom)).toBe(false);
        expect(editorDom.contains(handle)).toBe(false);
    });

    it('keeps Tiptap editor state independent per block through edits', async () => {
        render(<DndKitSpike />);

        const editorA = getTiptapEditor(getTiptapDom('spike-block-block-a'));
        const editorB = getTiptapEditor(getTiptapDom('spike-block-block-b'));
        expect(editorA).not.toBeNull();
        expect(editorB).not.toBeNull();

        await act(async () => {
            editorA!.commands.insertContentAt(0, 'EDITED ');
        });

        expect(editorA!.getHTML()).toContain('EDITED');
        expect(editorB!.getHTML()).not.toContain('EDITED');
    });

    it('registers pointer and keyboard sensors without throwing', () => {
        // Smoke check: rendering implicitly wires up both sensors via
        // useSensors. If activation constraints were missing or the
        // keyboard coordinate getter was misconfigured, dnd-kit would
        // throw during mount.
        expect(() => render(<DndKitSpike />)).not.toThrow();
        expect(screen.getByTestId('dnd-kit-spike')).toBeInTheDocument();
    });
});
