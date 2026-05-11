/**
 * Inspector sidebar shell.
 *
 * Right-hand sidebar that tabs between per-block settings (rendered by
 * `@wordpress/block-editor`'s `<BlockInspector />`) and document-level
 * settings (status, featured image, excerpt, discussion, plus host
 * extensions). Replaces the literal placeholder that shipped with M7
 * (#317) and wires up the sidebar toggle that was already plumbed
 * through the top bar.
 *
 * ARIA tabs pattern:
 *   - The tablist has `role="tablist"` and each button has `role="tab"`.
 *   - Arrow-left/right move focus between tabs and activate on focus
 *     (automatic activation — matches WP's inspector).
 *   - Each `tabpanel` is labelled by its tab's id.
 *
 * Auto-switching: when a block is selected while the sidebar is open,
 * the Block tab auto-activates. When the selection clears we leave the
 * user on whichever tab they're on — the Block tab stays visible at all
 * times and shows a "select a block" empty state when nothing is
 * selected. Mirrors the WordPress inspector and means the tablist never
 * surprises the user by shrinking.
 *
 * Focus management: when the sidebar becomes visible, focus moves to
 * the active tab. The parent controls visibility; this component just
 * runs the effect on mount.
 */

import { BlockInspector } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type ReactNode,
} from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import './inspector-sidebar.css';

export type InspectorTab = 'block' | 'document';

export interface InspectorSidebarProps {
    documentContent: ReactNode;
    /**
     * Render the Document tab. When `false`, the tablist is suppressed and
     * the inspector is a single-pane Block view — used when the host app
     * surfaces post meta in its own form outside the editor.
     */
    showDocumentTab?: boolean;
    /**
     * Override for the `@wordpress/data` selection lookup. Tests pass a
     * synchronous value so they don't need to stand up a block-editor
     * store; production code always omits this so the sidebar reflects
     * the live block selection.
     */
    hasSelectedBlockOverride?: boolean;
}

export function InspectorSidebar(props: InspectorSidebarProps): JSX.Element {
    const {
        documentContent,
        hasSelectedBlockOverride,
        showDocumentTab = true,
    } = props;

    const liveHasSelectedBlock = useSelect(
        (select) => {
            if (hasSelectedBlockOverride !== undefined) {
                return hasSelectedBlockOverride;
            }

            const store = select('core/block-editor') as
                | { hasSelectedBlock?: () => boolean }
                | undefined;

            return store?.hasSelectedBlock?.() ?? false;
        },
        [hasSelectedBlockOverride]
    );

    const hasSelectedBlock =
        hasSelectedBlockOverride !== undefined
            ? hasSelectedBlockOverride
            : liveHasSelectedBlock;

    // Both tabs are always mounted; `activeTab` just tracks which panel
    // is visible. On load we land on Document unless a block is already
    // selected (which is rare but possible when the host reopens a
    // previously-focused canvas).
    const [activeTab, setActiveTab] = useState<InspectorTab>(
        !showDocumentTab || hasSelectedBlock ? 'block' : 'document'
    );

    const blockTabId = useId();
    const documentTabId = useId();
    const blockPanelId = useId();
    const documentPanelId = useId();

    const blockTabRef = useRef<HTMLButtonElement | null>(null);
    const documentTabRef = useRef<HTMLButtonElement | null>(null);
    const initialFocusRan = useRef(false);

    // When a user selects a block while the sidebar is open, flip to
    // the Block tab so they see the settings for what they just clicked.
    // We deliberately don't auto-return to Document when the selection
    // clears — leaving the user on the Block tab with the empty-state
    // message preserves their place, and the tablist stays stable.
    const previousHasSelection = useRef<boolean>(hasSelectedBlock);

    useEffect(() => {
        if (previousHasSelection.current === hasSelectedBlock) {
            return;
        }

        previousHasSelection.current = hasSelectedBlock;

        if (hasSelectedBlock) {
            setActiveTab('block');
        }
    }, [hasSelectedBlock]);

    // Belt-and-suspenders: if a host flips `showDocumentTab` off while
    // the Document panel is active, snap back to Block so we never leave
    // the inspector stuck in a hidden tab with no tablist to recover
    // from. The `activeTab` initializer already covers the mount case;
    // this guards against runtime prop changes.
    useEffect(() => {
        if (!showDocumentTab && activeTab === 'document') {
            setActiveTab('block');
        }
    }, [showDocumentTab, activeTab]);

    // Move focus to the active tab on first render so keyboard users who
    // just toggled the sidebar open land somewhere useful. Skip on
    // subsequent re-renders so typing inside a document control doesn't
    // steal focus back to the tablist. Also skip when there's no tablist
    // to focus (single-pane mode via `showDocumentTab={false}`).
    useEffect(() => {
        if (initialFocusRan.current || !showDocumentTab) {
            return;
        }

        initialFocusRan.current = true;

        const target =
            activeTab === 'block' ? blockTabRef.current : documentTabRef.current;

        target?.focus({ preventScroll: true });
    }, [activeTab, showDocumentTab]);

    const handleSelectTab = useCallback((tab: InspectorTab): void => {
        setActiveTab(tab);
    }, []);

    const handleTabKey = useCallback(
        (event: ReactKeyboardEvent<HTMLButtonElement>): void => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }

            event.preventDefault();

            // Flip based on which tab is currently focused so the user can
            // arrow-navigate even if the selection/activation state is mid-
            // transition. Falling back to `activeTab` when focus is somewhere
            // else covers the keyboard-shortcut case where an outer handler
            // dispatches the event without focusing a tab first.
            const focusedTab: InspectorTab =
                event.currentTarget === blockTabRef.current
                    ? 'block'
                    : event.currentTarget === documentTabRef.current
                      ? 'document'
                      : activeTab;
            const next: InspectorTab =
                focusedTab === 'block' ? 'document' : 'block';

            setActiveTab(next);

            const target =
                next === 'block' ? blockTabRef.current : documentTabRef.current;

            target?.focus({ preventScroll: true });
        },
        [activeTab]
    );

    return (
        <aside
            className="ap-visual-editor-inspector-sidebar"
            aria-label={__('Inspector', TEXT_DOMAIN)}
            data-testid="ap-visual-editor-inspector-sidebar"
            data-active-tab={activeTab}
        >
            {showDocumentTab && (
                <div
                    className="ap-visual-editor-inspector-sidebar__tablist"
                    role="tablist"
                    aria-label={__('Inspector tabs', TEXT_DOMAIN)}
                >
                    <button
                        ref={blockTabRef}
                        type="button"
                        role="tab"
                        id={blockTabId}
                        className="ap-visual-editor-inspector-sidebar__tab"
                        aria-selected={activeTab === 'block'}
                        aria-controls={blockPanelId}
                        tabIndex={activeTab === 'block' ? 0 : -1}
                        data-testid="ap-visual-editor-inspector-tab-block"
                        onClick={() => handleSelectTab('block')}
                        onKeyDown={handleTabKey}
                    >
                        {__('Block', TEXT_DOMAIN)}
                    </button>
                    <button
                        ref={documentTabRef}
                        type="button"
                        role="tab"
                        id={documentTabId}
                        className="ap-visual-editor-inspector-sidebar__tab"
                        aria-selected={activeTab === 'document'}
                        aria-controls={documentPanelId}
                        tabIndex={activeTab === 'document' ? 0 : -1}
                        data-testid="ap-visual-editor-inspector-tab-document"
                        onClick={() => handleSelectTab('document')}
                        onKeyDown={handleTabKey}
                    >
                        {__('Document', TEXT_DOMAIN)}
                    </button>
                </div>
            )}
            {/*
             * Both tabpanels stay mounted so inner state survives tab
             * switches — `PanelBody` open/closed state, `TextareaControl`
             * cursor position, filter-registered plugin state, etc.
             * would reset if we unmounted the inactive panel. The
             * inactive panel is toggled via the `hidden` attribute
             * (which also sets `aria-hidden` automatically) so screen
             * readers and sighted users still see only the active one.
             */}
            <div
                {...(showDocumentTab
                    ? {
                          role: 'tabpanel',
                          id: blockPanelId,
                          'aria-labelledby': blockTabId,
                          hidden: activeTab !== 'block',
                      }
                    : { role: 'region', 'aria-label': __('Block', TEXT_DOMAIN) })}
                className="ap-visual-editor-inspector-sidebar__panel"
                data-testid="ap-visual-editor-inspector-block-panel"
            >
                {hasSelectedBlock ? (
                    <BlockInspector />
                ) : (
                    <p
                        className="ap-visual-editor-inspector-sidebar__empty"
                        data-testid="ap-visual-editor-inspector-block-empty"
                    >
                        {__(
                            'Click on a block to view its settings.',
                            TEXT_DOMAIN
                        )}
                    </p>
                )}
            </div>
            {showDocumentTab && (
                <div
                    role="tabpanel"
                    id={documentPanelId}
                    aria-labelledby={documentTabId}
                    className="ap-visual-editor-inspector-sidebar__panel"
                    data-testid="ap-visual-editor-inspector-document-panel"
                    hidden={activeTab !== 'document'}
                >
                    {documentContent}
                </div>
            )}
        </aside>
    );
}
