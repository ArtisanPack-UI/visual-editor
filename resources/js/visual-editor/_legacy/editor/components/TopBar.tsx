import { type ReactNode } from 'react';
import { useStore } from 'zustand';
import { Button } from '@artisanpack-ui/react/form';
import {
    faPlus,
    faUndo,
    faRedo,
    faCog,
} from '@fortawesome/free-solid-svg-icons';
import type { AutosaveState } from '../rest';
import { getBlock } from '../registry';
import { useEditorStore } from '../primitives';
import type { Block } from '../store';
import { Icon } from './Icon';
import { resolveBlockIcon } from './blockIconMap';

function findBlockInTree(blocks: Block[], clientId: string): Block | undefined {
    for (const block of blocks) {
        if (block.clientId === clientId) return block;
        const found = findBlockInTree(block.innerBlocks, clientId);
        if (found) return found;
    }
    return undefined;
}

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
        return findBlockInTree(state.blocks, state.selection.clientId) ?? null;
    });

    const selectedBlockDef = selectedBlock ? getBlock(selectedBlock.name) : null;
    const blockTitle = selectedBlockDef?.title ?? (selectedBlock ? selectedBlock.name : null);
    const blockIcon = selectedBlockDef ? resolveBlockIcon(selectedBlockDef.icon) : null;

    const statusLabel = resolveStatusLabel(saveStatus, isDirty);

    return (
        <div className="ve-top-bar" data-testid="ve-top-bar" role="toolbar" aria-label="Editor toolbar">
            <div className="ve-top-bar__left">
                <Button
                    size="sm"
                    color={inserterOpen ? 'primary' : 'ghost'}
                    className="btn-square"
                    onClick={onToggleInserter}
                    aria-label={inserterOpen ? 'Close block inserter' : 'Open block inserter'}
                    aria-expanded={inserterOpen}
                    data-testid="ve-top-bar-inserter-toggle"
                    icon={<Icon icon={faPlus} />}
                />

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
                <Button
                    size="sm"
                    color="ghost"
                    className="btn-square"
                    onClick={() => store.getState().undo()}
                    disabled={!canUndo}
                    aria-label="Undo"
                    data-testid="ve-top-bar-undo"
                    icon={<Icon icon={faUndo} />}
                />
                <Button
                    size="sm"
                    color="ghost"
                    className="btn-square"
                    onClick={() => store.getState().redo()}
                    disabled={!canRedo}
                    aria-label="Redo"
                    data-testid="ve-top-bar-redo"
                    icon={<Icon icon={faRedo} />}
                />

                <span
                    className={[
                        've-top-bar__save-status',
                        isDirty ? 've-top-bar__save-status--dirty' : null,
                    ].filter(Boolean).join(' ')}
                    data-testid="ve-top-bar-save-status"
                >
                    {statusLabel}
                </span>

                <Button
                    size="sm"
                    color={inspectorOpen ? 'primary' : 'ghost'}
                    className="btn-square"
                    onClick={onToggleInspector}
                    aria-label={inspectorOpen ? 'Close inspector' : 'Open inspector'}
                    aria-expanded={inspectorOpen}
                    data-testid="ve-top-bar-inspector-toggle"
                    icon={<Icon icon={faCog} />}
                />
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
