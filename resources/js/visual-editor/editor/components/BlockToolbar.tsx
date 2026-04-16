import { useCallback, useEffect, useRef, useState } from 'react';
import { useStore } from 'zustand';
import { faThumbtack } from '@fortawesome/free-solid-svg-icons';
import { useEditorStore } from '../primitives';
import { RichTextToolbar } from './RichTextToolbar';
import { Icon } from './Icon';

export interface BlockToolbarProps {
    pinned: boolean;
    onTogglePin: () => void;
}

/**
 * BlockToolbar wraps the RichTextToolbar with floating positioning logic.
 * In floating mode, it appears directly above the selected block.
 * In pinned mode, it renders inline (the parent places it in the TopBar).
 */
export function BlockToolbar({ pinned, onTogglePin }: BlockToolbarProps) {
    const store = useEditorStore();
    const selectedClientId = useStore(store, (state) => state.selection.clientId);
    const toolbarRef = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState<{ top: number; left: number } | null>(null);

    const updatePosition = useCallback(() => {
        if (pinned || !selectedClientId) {
            setPosition(null);
            return;
        }

        const blockEl = document.querySelector(
            `[data-block-client-id="${selectedClientId}"]`
        );

        if (!blockEl) {
            setPosition(null);
            return;
        }

        // The data-block-client-id element is inside the .ve-block wrapper which
        // has the actual padding. Find the ancestor .ve-block to get the correct
        // content offset.
        const veBlock = blockEl.closest('.ve-block') ?? blockEl;
        const veBlockRect = veBlock.getBoundingClientRect();
        const veBlockStyles = window.getComputedStyle(veBlock);
        const paddingLeft = parseFloat(veBlockStyles.paddingLeft) || 0;

        const canvasArea = blockEl.closest('.ve-editor-shell__canvas-area');
        const canvasAreaRect = canvasArea?.getBoundingClientRect();
        const scrollTop = (canvasArea as HTMLElement | null)?.scrollTop ?? 0;

        if (!canvasAreaRect) {
            setPosition(null);
            return;
        }

        setPosition({
            top: veBlockRect.top - canvasAreaRect.top + scrollTop - 44,
            left: veBlockRect.left - canvasAreaRect.left + paddingLeft,
        });
    }, [pinned, selectedClientId]);

    useEffect(() => {
        updatePosition();
    }, [updatePosition]);

    useEffect(() => {
        if (pinned || !selectedClientId) {
            return;
        }

        const observer = new MutationObserver(updatePosition);
        const canvasArea = document.querySelector('.ve-editor-shell__canvas-area');

        if (canvasArea) {
            observer.observe(canvasArea, { childList: true, subtree: true, attributes: true });
            canvasArea.addEventListener('scroll', updatePosition);
        }

        window.addEventListener('resize', updatePosition);

        return () => {
            observer.disconnect();
            if (canvasArea) {
                canvasArea.removeEventListener('scroll', updatePosition);
            }
            window.removeEventListener('resize', updatePosition);
        };
    }, [pinned, selectedClientId, updatePosition]);

    if (!selectedClientId) {
        return null;
    }

    const toolbarContent = (
        <>
            <RichTextToolbar />
            <button
                type="button"
                className={[
                    've-block-toolbar__pin',
                    pinned ? 've-block-toolbar__pin--active' : null,
                ].filter(Boolean).join(' ')}
                onClick={onTogglePin}
                aria-label={pinned ? 'Float toolbar' : 'Pin toolbar to top'}
                aria-pressed={pinned}
                data-testid="ve-block-toolbar-pin"
            >
                <Icon icon={faThumbtack} />
            </button>
        </>
    );

    if (pinned) {
        return (
            <div
                ref={toolbarRef}
                className="ve-block-toolbar ve-block-toolbar--pinned"
                data-testid="ve-block-toolbar"
            >
                {toolbarContent}
            </div>
        );
    }

    if (!position) {
        return null;
    }

    return (
        <div
            ref={toolbarRef}
            className="ve-block-toolbar ve-block-toolbar--floating"
            data-testid="ve-block-toolbar"
            style={{
                position: 'absolute',
                top: `${Math.max(0, position.top)}px`,
                left: `${position.left}px`,
                zIndex: 10,
            }}
        >
            {toolbarContent}
        </div>
    );
}
