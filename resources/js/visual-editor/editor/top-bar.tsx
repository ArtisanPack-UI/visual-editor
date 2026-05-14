/**
 * Editor top bar.
 *
 * Owns the admin chrome above the block canvas — save indicator, undo/
 * redo, the inserter/inspector toggles, preview link, and the "more
 * options" menu. This intentionally replaces `@wordpress/edit-post`'s
 * header so the editor feels like a Laravel admin page rather than a
 * WordPress one (M7 of the Gutenberg adoption; umbrella issue #309).
 *
 * A1 (#343) moved the title input into the canvas and moved the slug
 * and status selectors into the inspector sidebar's Document tab, so
 * this bar no longer renders them. Keyboard shortcuts (⌘Z, ⌘⇧Z, ⌘S)
 * stay here because they're global regardless of where the fields
 * live.
 *
 * Theming hook: every color, spacing, and radius value is driven by CSS
 * custom properties under the `--ap-visual-editor-top-bar-*` namespace so
 * M8 (#318) can override them without touching this file.
 */

import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type MouseEvent as ReactMouseEvent,
    type ReactNode,
} from 'react';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import './top-bar.css';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export interface TopBarProps {
    saveStatus: SaveStatus;
    lastSavedAt?: string | null;
    saveErrorMessage?: string | null;
    canUndo: boolean;
    canRedo: boolean;
    onUndo: () => void;
    onRedo: () => void;
    isInserterOpen: boolean;
    isInspectorOpen: boolean;
    onToggleInserter: () => void;
    onToggleInspector: () => void;
    previewUrl?: string | null;
    onSave?: () => void;
    onCopyStyles?: () => void;
    onPasteStyles?: () => void;
    onShowKeyboardShortcuts?: () => void;
    /**
     * Optional override so host apps can inject brand icons or additional
     * actions between the preview link and the more-options menu.
     */
    extraActions?: ReactNode;
    /**
     * Optional content rendered at the very start of the left group,
     * before the inserter / undo / redo buttons. The site editor uses
     * this for its exit link so it sits top-left — matching the
     * WordPress site-editor layout — rather than buried in the right
     * group.
     */
    leadingActions?: ReactNode;
    /**
     * Override the aria-label / accessible name of the left toggle button.
     * The post editor uses this slot for the block inserter; the site
     * editor (D1 · #368) reuses the same button for the navigator panel
     * and supplies the matching label here. Defaults preserve the
     * post-editor wording when omitted.
     */
    inserterToggleAriaLabel?: { open: string; close: string };
    /**
     * Override the aria-label / accessible name of the right (inspector)
     * toggle button. The label varies between the post editor (the
     * inspector panel) and the site editor (the section inspector).
     */
    inspectorToggleAriaLabel?: { open: string; close: string };
}

function saveStatusLabel(
    status: SaveStatus,
    errorMessage: string | null | undefined,
    lastSavedAt: string | null | undefined
): string {
    switch (status) {
        case 'saving':
            return __('Saving…', TEXT_DOMAIN);
        case 'saved':
            if (lastSavedAt) {
                const formatted = formatTimestamp(lastSavedAt);

                if (formatted !== null) {
                    return __('Saved', TEXT_DOMAIN) + ' · ' + formatted;
                }
            }

            return __('Saved', TEXT_DOMAIN);
        case 'error':
            return errorMessage ?? __('Save failed', TEXT_DOMAIN);
        case 'idle':
        default:
            return __('Unsaved changes', TEXT_DOMAIN);
    }
}

function formatTimestamp(iso: string): string | null {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    } catch {
        return date.toISOString();
    }
}

function isModifier(event: KeyboardEvent): boolean {
    return event.metaKey || event.ctrlKey;
}

/**
 * Walks an element list and focuses the next/previous item, wrapping at the
 * ends. Used for the arrow-key navigation inside the more-options menu.
 */
function focusAdjacent(
    items: HTMLElement[],
    currentIndex: number,
    direction: 1 | -1
): void {
    if (items.length === 0) {
        return;
    }

    const nextIndex = (currentIndex + direction + items.length) % items.length;

    items[nextIndex]?.focus();
}

export function TopBar(props: TopBarProps): JSX.Element {
    const {
        saveStatus,
        lastSavedAt,
        saveErrorMessage,
        canUndo,
        canRedo,
        onUndo,
        onRedo,
        isInserterOpen,
        isInspectorOpen,
        onToggleInserter,
        onToggleInspector,
        previewUrl,
        onSave,
        onCopyStyles,
        onPasteStyles,
        onShowKeyboardShortcuts,
        extraActions,
        leadingActions,
        inserterToggleAriaLabel,
        inspectorToggleAriaLabel,
    } = props;

    const inserterOpenLabel =
        inserterToggleAriaLabel?.open ?? __('Open block inserter', TEXT_DOMAIN);
    const inserterCloseLabel =
        inserterToggleAriaLabel?.close ?? __('Close block inserter', TEXT_DOMAIN);
    const inspectorOpenLabel =
        inspectorToggleAriaLabel?.open ?? __('Open inspector', TEXT_DOMAIN);
    const inspectorCloseLabel =
        inspectorToggleAriaLabel?.close ?? __('Close inspector', TEXT_DOMAIN);

    const menuId = useId();
    const menuTriggerRef = useRef<HTMLButtonElement | null>(null);
    const menuRef = useRef<HTMLDivElement | null>(null);
    const [menuOpen, setMenuOpen] = useState<boolean>(false);

    useEffect(() => {
        function handleShortcut(event: KeyboardEvent): void {
            if (!isModifier(event)) {
                return;
            }

            const key = event.key.toLowerCase();

            if (key === 'z') {
                event.preventDefault();

                if (event.shiftKey) {
                    if (canRedo) {
                        onRedo();
                    }
                } else if (canUndo) {
                    onUndo();
                }

                return;
            }

            if (key === 's' && onSave !== undefined) {
                event.preventDefault();
                onSave();
            }
        }

        window.addEventListener('keydown', handleShortcut);

        return () => {
            window.removeEventListener('keydown', handleShortcut);
        };
    }, [canRedo, canUndo, onRedo, onSave, onUndo]);

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        function handlePointerDown(event: MouseEvent): void {
            const target = event.target;

            if (!(target instanceof Node)) {
                return;
            }

            if (menuRef.current?.contains(target)) {
                return;
            }

            if (menuTriggerRef.current?.contains(target)) {
                return;
            }

            setMenuOpen(false);
        }

        function handleKey(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                event.preventDefault();
                setMenuOpen(false);
                menuTriggerRef.current?.focus();
            }
        }

        window.addEventListener('mousedown', handlePointerDown);
        window.addEventListener('keydown', handleKey);

        return () => {
            window.removeEventListener('mousedown', handlePointerDown);
            window.removeEventListener('keydown', handleKey);
        };
    }, [menuOpen]);

    const handleMenuKey = useCallback(
        (event: ReactKeyboardEvent<HTMLDivElement>) => {
            if (!menuRef.current) {
                return;
            }

            const items = Array.from(
                menuRef.current.querySelectorAll<HTMLButtonElement>(
                    '[role="menuitem"]:not([disabled])'
                )
            );

            if (items.length === 0) {
                return;
            }

            const currentIndex = items.indexOf(
                document.activeElement as HTMLButtonElement
            );

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                focusAdjacent(items, currentIndex, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                focusAdjacent(items, currentIndex, -1);
            } else if (event.key === 'Home') {
                event.preventDefault();
                items[0]?.focus();
            } else if (event.key === 'End') {
                event.preventDefault();
                items[items.length - 1]?.focus();
            }
        },
        []
    );

    const toggleMenu = useCallback(
        (event: ReactMouseEvent<HTMLButtonElement>): void => {
            event.preventDefault();
            setMenuOpen((open) => !open);
        },
        []
    );

    const runMenuAction = useCallback(
        (handler: (() => void) | undefined): (() => void) =>
            () => {
                setMenuOpen(false);
                menuTriggerRef.current?.focus();
                handler?.();
            },
        []
    );

    const saveStatusText = useMemo(
        () => saveStatusLabel(saveStatus, saveErrorMessage, lastSavedAt),
        [saveStatus, saveErrorMessage, lastSavedAt]
    );

    const menuItems: ReadonlyArray<{
        key: string;
        label: string;
        handler: (() => void) | undefined;
    }> = [
        {
            key: 'shortcuts',
            label: __('Keyboard shortcuts', TEXT_DOMAIN),
            handler: onShowKeyboardShortcuts,
        },
        {
            key: 'copy-styles',
            label: __('Copy styles', TEXT_DOMAIN),
            handler: onCopyStyles,
        },
        {
            key: 'paste-styles',
            label: __('Paste styles', TEXT_DOMAIN),
            handler: onPasteStyles,
        },
    ];

    return (
        <header
            className="ap-visual-editor-top-bar"
            role="toolbar"
            aria-label={__('Editor top bar', TEXT_DOMAIN)}
            data-testid="ap-visual-editor-top-bar"
        >
            <div className="ap-visual-editor-top-bar__group ap-visual-editor-top-bar__group--start">
                {leadingActions}
                <button
                    type="button"
                    className="ap-visual-editor-top-bar__icon-button"
                    aria-label={
                        isInserterOpen ? inserterCloseLabel : inserterOpenLabel
                    }
                    aria-expanded={isInserterOpen}
                    aria-pressed={isInserterOpen}
                    data-open={isInserterOpen}
                    data-testid="ap-visual-editor-top-bar-inserter"
                    onClick={onToggleInserter}
                >
                    <svg
                        aria-hidden="true"
                        focusable="false"
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                    >
                        <path
                            fill="currentColor"
                            d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1z"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    className="ap-visual-editor-top-bar__icon-button"
                    aria-label={__('Undo', TEXT_DOMAIN)}
                    data-testid="ap-visual-editor-top-bar-undo"
                    disabled={!canUndo}
                    onClick={onUndo}
                >
                    <svg
                        aria-hidden="true"
                        focusable="false"
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                    >
                        <path
                            fill="currentColor"
                            d="M12 5a8 8 0 0 1 7.446 10.889 1 1 0 1 1-1.86-.739A6 6 0 1 0 12 19h3a1 1 0 1 1 0 2h-3A8 8 0 0 1 12 5zm-5 5 3 3H5V7h2v3z"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    className="ap-visual-editor-top-bar__icon-button"
                    aria-label={__('Redo', TEXT_DOMAIN)}
                    data-testid="ap-visual-editor-top-bar-redo"
                    disabled={!canRedo}
                    onClick={onRedo}
                >
                    <svg
                        aria-hidden="true"
                        focusable="false"
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                    >
                        <path
                            fill="currentColor"
                            d="M12 5a8 8 0 0 0-7.446 10.889 1 1 0 1 0 1.86-.739A6 6 0 1 1 12 19H9a1 1 0 1 0 0 2h3A8 8 0 0 0 12 5zm5 5-3 3h5V7h-2v3z"
                        />
                    </svg>
                </button>
            </div>
            <div className="ap-visual-editor-top-bar__group ap-visual-editor-top-bar__group--end">
                <span
                    className="ap-visual-editor-top-bar__save-status"
                    role="status"
                    aria-live="polite"
                    data-save-status={saveStatus}
                    data-testid="ap-visual-editor-top-bar-save-status"
                >
                    {saveStatusText}
                </span>
                {previewUrl ? (
                    <a
                        className="ap-visual-editor-top-bar__preview"
                        href={previewUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        data-testid="ap-visual-editor-top-bar-preview"
                    >
                        {__('Preview', TEXT_DOMAIN)}
                    </a>
                ) : null}
                {extraActions}
                <button
                    type="button"
                    className="ap-visual-editor-top-bar__icon-button"
                    aria-label={
                        isInspectorOpen ? inspectorCloseLabel : inspectorOpenLabel
                    }
                    aria-expanded={isInspectorOpen}
                    aria-pressed={isInspectorOpen}
                    data-open={isInspectorOpen}
                    data-testid="ap-visual-editor-top-bar-inspector"
                    onClick={onToggleInspector}
                >
                    <svg
                        aria-hidden="true"
                        focusable="false"
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                    >
                        <path
                            fill="currentColor"
                            d="M3 6h18v2H3V6zm0 5h12v2H3v-2zm0 5h18v2H3v-2z"
                        />
                    </svg>
                </button>
                <div className="ap-visual-editor-top-bar__menu">
                    <button
                        ref={menuTriggerRef}
                        type="button"
                        className="ap-visual-editor-top-bar__icon-button"
                        aria-haspopup="menu"
                        aria-expanded={menuOpen}
                        aria-controls={menuId}
                        aria-label={__('More options', TEXT_DOMAIN)}
                        data-testid="ap-visual-editor-top-bar-menu-trigger"
                        onClick={toggleMenu}
                    >
                        <svg
                            aria-hidden="true"
                            focusable="false"
                            viewBox="0 0 24 24"
                            width="20"
                            height="20"
                        >
                            <circle cx="5" cy="12" r="2" fill="currentColor" />
                            <circle cx="12" cy="12" r="2" fill="currentColor" />
                            <circle cx="19" cy="12" r="2" fill="currentColor" />
                        </svg>
                    </button>
                    {menuOpen ? (
                        <div
                            ref={menuRef}
                            id={menuId}
                            role="menu"
                            aria-label={__('More options', TEXT_DOMAIN)}
                            className="ap-visual-editor-top-bar__menu-panel"
                            data-testid="ap-visual-editor-top-bar-menu"
                            onKeyDown={handleMenuKey}
                        >
                            {menuItems.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    role="menuitem"
                                    className="ap-visual-editor-top-bar__menu-item"
                                    disabled={item.handler === undefined}
                                    data-testid={`ap-visual-editor-top-bar-menu-${item.key}`}
                                    onClick={runMenuAction(item.handler)}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>
            </div>
        </header>
    );
}
