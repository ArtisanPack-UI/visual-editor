import { useStore } from 'zustand';
import { getBlock } from '../registry';
import { useEditorStore } from '../primitives';

export function StatusBar() {
    const store = useEditorStore();
    const selectedBlock = useStore(store, (state) => {
        if (!state.selection.clientId) {
            return null;
        }
        return state.blocks.find((b) => b.clientId === state.selection.clientId) ?? null;
    });

    const blockTitle = selectedBlock
        ? getBlock(selectedBlock.name)?.title ?? selectedBlock.name
        : null;

    return (
        <div className="ve-status-bar" data-testid="ve-status-bar" role="status" aria-live="polite">
            <span className="ve-status-bar__item" data-testid="ve-status-bar-block">
                {blockTitle
                    ? `${blockTitle} — ${selectedBlock!.clientId}`
                    : 'No block selected'}
            </span>
        </div>
    );
}
