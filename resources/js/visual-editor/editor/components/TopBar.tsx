import { type ReactNode } from 'react';
import { useStore } from 'zustand';
import {
    faPlus,
    faUndo,
    faRedo,
    faCog,
} from '@fortawesome/free-solid-svg-icons';
import type { AutosaveState } from '../rest';
import { getBlock } from '../registry';
import { useEditorStore } from '../primitives';
import { Icon } from './Icon';
import { resolveBlockIcon } from './blockIconMap';

export interface TopBarProps {
    inserterOpen: boolean;
    onToggleInserter: () => void;
    inspectorOpen: boolean;
    onToggleInspector: () => void;
    saveStatus?: AutosaveState;
    pinnedToolbar?: ReactNode;
}

export function TopBar({
    inserterOpen,
    onToggleInserter,
    inspectorOpen,
    onToggleInspector,
    saveStatus,
    pinnedToolbar,
}: TopBarProps) {
    const store = useEditorStore();
    const isDirty = useStore(store, (state) => state.isDirty);
    const canUndo = useStore(store, (state) => state.history.past.length > 0);
    const canRedo = useStore(store, (state) => state.history.future.length > 0);

    const selectedBlock = useStore(store, (state) => {
        if (!state.selection.clientId) {
            return null;
        }
        return state.blocks.find((b) => b.clientId === state.selection.clientId) ?? null;
    });

    const selectedBlockDef = selectedBlock ? getBlock(selectedBlock.name) : null;
    const blockTitle = selectedBlockDef?.title ?? (selectedBlock ? selectedBlock.name : null);
    const blockIcon = selectedBlockDef ? resolveBlockIcon(selectedBlockDef.icon) : null;

    const statusLabel = resolveStatusLabel(saveStatus, isDirty);

    return (
        <div className="ve-top-bar" data-testid="ve-top-bar" role="toolbar" aria-label="Editor toolbar">
            <div className="ve-top-bar__left">
                <button
                    type="button"
                    className={[
                        've-top-bar__button',
                        inserterOpen ? 've-top-bar__button--is-active' : null,
                    ].filter(Boolean).join(' ')}
                    onClick={onToggleInserter}
                    aria-label={inserterOpen ? 'Close block inserter' : 'Open block inserter'}
                    aria-expanded={inserterOpen}
                    data-testid="ve-top-bar-inserter-toggle"
                >
                    <Icon icon={faPlus} />
                </button>

                {blockTitle ? (
                    <span className="ve-top-bar__block-info" data-testid="ve-top-bar-block-info">
                        {blockIcon ? <Icon icon={blockIcon} /> : null}
                        <span>{blockTitle}</span>
                    </span>
                ) : null}
            </div>

            <div className="ve-top-bar__center">
                {pinnedToolbar}
            </div>

            <div className="ve-top-bar__right">
                <button
                    type="button"
                    className="ve-top-bar__button"
                    onClick={() => store.getState().undo()}
                    disabled={!canUndo}
                    aria-label="Undo"
                    data-testid="ve-top-bar-undo"
                >
                    <Icon icon={faUndo} />
                </button>
                <button
                    type="button"
                    className="ve-top-bar__button"
                    onClick={() => store.getState().redo()}
                    disabled={!canRedo}
                    aria-label="Redo"
                    data-testid="ve-top-bar-redo"
                >
                    <Icon icon={faRedo} />
                </button>

                <span
                    className={[
                        've-top-bar__save-status',
                        isDirty ? 've-top-bar__save-status--dirty' : null,
                    ].filter(Boolean).join(' ')}
                    data-testid="ve-top-bar-save-status"
                >
                    {statusLabel}
                </span>

                <button
                    type="button"
                    className={[
                        've-top-bar__button',
                        inspectorOpen ? 've-top-bar__button--is-active' : null,
                    ].filter(Boolean).join(' ')}
                    onClick={onToggleInspector}
                    aria-label={inspectorOpen ? 'Close inspector' : 'Open inspector'}
                    aria-expanded={inspectorOpen}
                    data-testid="ve-top-bar-inspector-toggle"
                >
                    <Icon icon={faCog} />
                </button>
            </div>
        </div>
    );
}

function resolveStatusLabel(
    saveStatus: AutosaveState | undefined,
    isDirty: boolean
): string {
    if (saveStatus) {
        switch (saveStatus.status) {
            case 'saving':
                return 'Saving…';
            case 'saved':
                return 'Saved';
            case 'error':
                return 'Save failed';
            case 'idle':
            default:
                break;
        }
    }
    return isDirty ? 'Unsaved changes' : 'Saved';
}
