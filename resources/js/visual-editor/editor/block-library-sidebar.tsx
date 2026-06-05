/**
 * Block library sidebar (left rail).
 *
 * Three-tab left sidebar that replaces the placeholder shipped with M7
 * (#317). Tabs are:
 *
 *  - Blocks — the real browsable inserter, delegated to
 *    `@wordpress/block-editor`'s `__experimentalLibrary`. The library's
 *    internal Blocks/Patterns/Media tab strip is hidden via scoped CSS so
 *    only the block grid, search, and category headers show through. We
 *    deliberately *don't* reimplement the inserter — the library already
 *    ships the "Most Used" section, category headers, keyboard nav, and
 *    drag-to-canvas behaviour that this milestone asks for.
 *  - Patterns — stub for Phase D (#309 plan §Phase D5). The container
 *    and panel markup live here now so the switch to the real library is
 *    a render swap rather than a structural change.
 *  - Layouts — hierarchical list of every block in the canvas, rendered
 *    via `__experimentalListView`. Authors can drag rows to reorder, and
 *    the canvas selection/order update to match.
 *
 * ARIA tabs pattern mirrors `inspector-sidebar.tsx` (the right rail):
 * tablist + three always-mounted tabpanels toggled via `hidden`, arrow-
 * key navigation with automatic activation, focus restored to the active
 * tab on first mount.
 *
 * Slash-command inserter: untouched. This sidebar lives alongside the
 * canvas, so authors keep the `/` shortcut for power use.
 */

import {
    __experimentalLibrary as InserterLibrary,
    __experimentalListView as ListView,
} from '@wordpress/block-editor';
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

import { InserterPatternsPanel } from './inserter-patterns-panel';

import './block-library-sidebar.css';

export type BlockLibraryTab = 'blocks' | 'patterns' | 'layouts';

const TAB_ORDER: ReadonlyArray<BlockLibraryTab> = [
    'blocks',
    'patterns',
    'layouts',
];

export interface BlockLibrarySidebarProps {
    /**
     * Render override for the Blocks tab. Tests pass in a stub so they
     * don't need the full `core/block-editor` store; production always
     * omits this so `__experimentalLibrary` renders.
     */
    renderBlocksTab?: () => ReactNode;
    /**
     * Render override for the Patterns tab. Production passes `apiBase`
     * via the dedicated prop instead and lets the default render path
     * mount the patterns panel; tests stub the panel out so they don't
     * have to spin up the patterns REST surface.
     */
    renderPatternsTab?: () => ReactNode;
    /**
     * Render override for the Layouts tab, for the same reason.
     */
    renderLayoutsTab?: () => ReactNode;
    /**
     * Base URL for the visual-editor API (e.g. `/visual-editor/api`).
     * Required for the Patterns tab — when omitted the panel renders an
     * inline note explaining the host hasn't passed an API base. Default
     * production wiring always supplies it.
     */
    apiBase?: string;
    /**
     * Tab to open on first render. Defaults to `'blocks'`.
     */
    initialTab?: BlockLibraryTab;
}

function nextTabInDirection(
    current: BlockLibraryTab,
    direction: 1 | -1
): BlockLibraryTab {
    const index = TAB_ORDER.indexOf(current);

    if (index === -1) {
        return 'blocks';
    }

    const nextIndex =
        (index + direction + TAB_ORDER.length) % TAB_ORDER.length;

    return TAB_ORDER[nextIndex] ?? 'blocks';
}

export function BlockLibrarySidebar(
    props: BlockLibrarySidebarProps
): JSX.Element {
    const {
        renderBlocksTab,
        renderPatternsTab,
        renderLayoutsTab,
        apiBase,
        initialTab = 'blocks',
    } = props;

    const [activeTab, setActiveTab] = useState<BlockLibraryTab>(initialTab);

    const blocksTabId = useId();
    const patternsTabId = useId();
    const layoutsTabId = useId();
    const blocksPanelId = useId();
    const patternsPanelId = useId();
    const layoutsPanelId = useId();

    const blocksTabRef = useRef<HTMLButtonElement | null>(null);
    const patternsTabRef = useRef<HTMLButtonElement | null>(null);
    const layoutsTabRef = useRef<HTMLButtonElement | null>(null);

    const tabRefForTab = useCallback(
        (
            tab: BlockLibraryTab
        ): React.MutableRefObject<HTMLButtonElement | null> => {
            if (tab === 'blocks') {
                return blocksTabRef;
            }

            if (tab === 'patterns') {
                return patternsTabRef;
            }

            return layoutsTabRef;
        },
        []
    );

    // Focus the active tab on mount so keyboard users who just toggled
    // the sidebar open land somewhere useful. Subsequent re-renders skip
    // the focus so typing into the library's search field doesn't steal
    // focus back to the tablist.
    const initialFocusRan = useRef(false);

    useEffect(() => {
        if (initialFocusRan.current) {
            return;
        }

        initialFocusRan.current = true;
        tabRefForTab(activeTab).current?.focus({ preventScroll: true });
    }, [activeTab, tabRefForTab]);

    const handleSelectTab = useCallback((tab: BlockLibraryTab): void => {
        setActiveTab(tab);
    }, []);

    const handleTabKey = useCallback(
        (event: ReactKeyboardEvent<HTMLButtonElement>): void => {
            if (
                event.key !== 'ArrowLeft' &&
                event.key !== 'ArrowRight' &&
                event.key !== 'Home' &&
                event.key !== 'End'
            ) {
                return;
            }

            event.preventDefault();

            const focused: BlockLibraryTab =
                event.currentTarget === blocksTabRef.current
                    ? 'blocks'
                    : event.currentTarget === patternsTabRef.current
                      ? 'patterns'
                      : event.currentTarget === layoutsTabRef.current
                        ? 'layouts'
                        : activeTab;

            let next: BlockLibraryTab;

            if (event.key === 'Home') {
                next = TAB_ORDER[0] ?? 'blocks';
            } else if (event.key === 'End') {
                next = TAB_ORDER[TAB_ORDER.length - 1] ?? 'layouts';
            } else {
                next = nextTabInDirection(
                    focused,
                    event.key === 'ArrowRight' ? 1 : -1
                );
            }

            setActiveTab(next);
            tabRefForTab(next).current?.focus({ preventScroll: true });
        },
        [activeTab, tabRefForTab]
    );

    const blocksContent: ReactNode =
        renderBlocksTab !== undefined ? (
            renderBlocksTab()
        ) : (
            <InserterLibrary
                __experimentalInitialTab="blocks"
                showMostUsedBlocks
            />
        );

    const layoutsContent: ReactNode =
        renderLayoutsTab !== undefined ? (
            renderLayoutsTab()
        ) : (
            // `showBlockMovers` enables drag-drop reordering of the
            // block tree, `isExpanded` defaults every branch to open so
            // authors see the full outline without having to click to
            // expand. Selection flows through the block-editor store,
            // so clicking a row highlights the matching block in the
            // canvas (and vice-versa).
            <ListView
                showBlockMovers
                isExpanded
                description={__(
                    'Block list with drag-and-drop reordering.',
                    TEXT_DOMAIN
                )}
            />
        );

    return (
        <aside
            className="ap-visual-editor-block-library"
            aria-label={__('Block library', TEXT_DOMAIN)}
            data-testid="ap-visual-editor-block-library"
            data-active-tab={activeTab}
        >
            <div
                className="ap-visual-editor-block-library__tablist"
                role="tablist"
                aria-label={__('Block library tabs', TEXT_DOMAIN)}
            >
                <button
                    ref={blocksTabRef}
                    type="button"
                    role="tab"
                    id={blocksTabId}
                    className="ap-visual-editor-block-library__tab"
                    aria-selected={activeTab === 'blocks'}
                    aria-controls={blocksPanelId}
                    tabIndex={activeTab === 'blocks' ? 0 : -1}
                    data-testid="ap-visual-editor-block-library-tab-blocks"
                    onClick={() => handleSelectTab('blocks')}
                    onKeyDown={handleTabKey}
                >
                    {__('Blocks', TEXT_DOMAIN)}
                </button>
                <button
                    ref={patternsTabRef}
                    type="button"
                    role="tab"
                    id={patternsTabId}
                    className="ap-visual-editor-block-library__tab"
                    aria-selected={activeTab === 'patterns'}
                    aria-controls={patternsPanelId}
                    tabIndex={activeTab === 'patterns' ? 0 : -1}
                    data-testid="ap-visual-editor-block-library-tab-patterns"
                    onClick={() => handleSelectTab('patterns')}
                    onKeyDown={handleTabKey}
                >
                    {__('Patterns', TEXT_DOMAIN)}
                </button>
                <button
                    ref={layoutsTabRef}
                    type="button"
                    role="tab"
                    id={layoutsTabId}
                    className="ap-visual-editor-block-library__tab"
                    aria-selected={activeTab === 'layouts'}
                    aria-controls={layoutsPanelId}
                    tabIndex={activeTab === 'layouts' ? 0 : -1}
                    data-testid="ap-visual-editor-block-library-tab-layouts"
                    onClick={() => handleSelectTab('layouts')}
                    onKeyDown={handleTabKey}
                >
                    {__('Layouts', TEXT_DOMAIN)}
                </button>
            </div>
            {/*
             * Both the Blocks tab and its siblings stay mounted so
             * `__experimentalLibrary`'s internal state (search query,
             * selected category, preview hover) and the `ListView` tree
             * expansion survive tab switches. The inactive panel is
             * toggled via `hidden` so screen readers and sighted users
             * only see one at a time.
             */}
            <div
                role="tabpanel"
                id={blocksPanelId}
                aria-labelledby={blocksTabId}
                className="ap-visual-editor-block-library__panel ap-visual-editor-block-library__panel--blocks"
                data-testid="ap-visual-editor-block-library-blocks-panel"
                hidden={activeTab !== 'blocks'}
            >
                {blocksContent}
            </div>
            <div
                role="tabpanel"
                id={patternsPanelId}
                aria-labelledby={patternsTabId}
                className="ap-visual-editor-block-library__panel ap-visual-editor-block-library__panel--patterns"
                data-testid="ap-visual-editor-block-library-patterns-panel"
                hidden={activeTab !== 'patterns'}
            >
                {renderPatternsTab !== undefined ? (
                    renderPatternsTab()
                ) : apiBase !== undefined && apiBase !== '' ? (
                    <InserterPatternsPanel apiBase={apiBase} />
                ) : (
                    <div
                        className="ap-visual-editor-block-library__patterns-stub"
                        data-testid="ap-visual-editor-block-library-patterns-stub"
                    >
                        <h3 className="ap-visual-editor-block-library__stub-title">
                            {__('Patterns', TEXT_DOMAIN)}
                        </h3>
                        <p className="ap-visual-editor-block-library__empty">
                            {__(
                                'Pass an API base to load patterns into this tab.',
                                TEXT_DOMAIN
                            )}
                        </p>
                    </div>
                )}
            </div>
            <div
                role="tabpanel"
                id={layoutsPanelId}
                aria-labelledby={layoutsTabId}
                className="ap-visual-editor-block-library__panel ap-visual-editor-block-library__panel--layouts"
                data-testid="ap-visual-editor-block-library-layouts-panel"
                hidden={activeTab !== 'layouts'}
            >
                {layoutsContent}
            </div>
        </aside>
    );
}
