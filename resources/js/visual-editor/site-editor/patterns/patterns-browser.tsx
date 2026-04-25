/**
 * Patterns navigator-outlet browser.
 *
 * Lives in the site-editor navigator slot when the active section is
 * Patterns. Two tabs (Synced / Unsynced) split the list per design brief
 * §3.6; rows mirror templates / parts so users get a consistent scanning
 * experience across sections. The card-grid view that the canvas
 * exposes complements this list — the navigator shows the small-row
 * scan list, the canvas shows thumbnails and per-card actions.
 *
 * No "Detach" affordance anywhere. The conversion flow lives on the
 * canvas (per §3.6) so the navigator stays focused on browsing.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    type KeyboardEvent as ReactKeyboardEvent,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';

import { type PatternRecord } from './api-client';
import { usePatternsList } from './use-patterns-list';

import './patterns-browser.css';

export type SyncTabId = 'synced' | 'unsynced';

const TAB_ORDER: ReadonlyArray<SyncTabId> = ['synced', 'unsynced'];

export interface PatternsBrowserProps {
    apiConfig: SiteEditorApiConfig;
    activeEntityId: string | null;
    activeTab: SyncTabId;
    onSelectTab: (tab: SyncTabId) => void;
    onOpen: (entityId: string) => void;
    onRequestCreate: (synced: boolean) => void;
    refreshKey?: number;
}

function patternTitle(pattern: PatternRecord): string {
    // Prefer the user-authored `raw` title over `rendered` so labels
    // don't surface HTML-escaped entities (`&amp;`, …). Fall back to
    // the slug when neither field is present.
    const raw = pattern.title?.raw?.trim();

    if (raw !== undefined && raw !== '') {
        return raw;
    }

    const rendered = pattern.title?.rendered?.trim();

    if (rendered !== undefined && rendered !== '') {
        return rendered;
    }

    return pattern.slug;
}

export function PatternsBrowser(props: PatternsBrowserProps): JSX.Element {
    const {
        apiConfig,
        activeEntityId,
        activeTab,
        onSelectTab,
        onOpen,
        onRequestCreate,
        refreshKey,
    } = props;

    const tabListId = useId();
    const syncedTabId = useId();
    const unsyncedTabId = useId();
    const syncedPanelId = useId();
    const unsyncedPanelId = useId();
    const syncedTabRef = useRef<HTMLButtonElement | null>(null);
    const unsyncedTabRef = useRef<HTMLButtonElement | null>(null);

    const { items, status, errorMessage, refresh } = usePatternsList({
        apiConfig,
        synced: activeTab === 'synced',
        refreshKey,
    });

    const tabRefForTab = useCallback(
        (
            tab: SyncTabId
        ): React.MutableRefObject<HTMLButtonElement | null> => {
            return tab === 'synced' ? syncedTabRef : unsyncedTabRef;
        },
        []
    );

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

            let next: SyncTabId;

            if (event.key === 'Home') {
                next = TAB_ORDER[0] ?? 'synced';
            } else if (event.key === 'End') {
                next = TAB_ORDER[TAB_ORDER.length - 1] ?? 'unsynced';
            } else {
                const index = TAB_ORDER.indexOf(activeTab);
                const direction = event.key === 'ArrowRight' ? 1 : -1;
                const nextIndex =
                    (index + direction + TAB_ORDER.length) % TAB_ORDER.length;

                next = TAB_ORDER[nextIndex] ?? 'synced';
            }

            onSelectTab(next);
            tabRefForTab(next).current?.focus({ preventScroll: true });
        },
        [activeTab, onSelectTab, tabRefForTab]
    );

    const handleRowKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLElement>): void => {
            const list = event.currentTarget.closest(
                '[data-ap-patterns-list]'
            );

            if (list === null) {
                return;
            }

            const buttons = Array.from(
                list.querySelectorAll<HTMLButtonElement>(
                    'button[data-ap-patterns-row]'
                )
            );

            if (buttons.length === 0) {
                return;
            }

            const activeElement = document.activeElement;
            const activeButton =
                activeElement instanceof HTMLButtonElement
                    ? activeElement
                    : null;
            const currentIndex =
                activeButton === null ? -1 : buttons.indexOf(activeButton);

            let nextIndex: number | null = null;

            if (event.key === 'ArrowDown') {
                nextIndex =
                    currentIndex === -1
                        ? 0
                        : (currentIndex + 1) % buttons.length;
            } else if (event.key === 'ArrowUp') {
                nextIndex =
                    currentIndex === -1
                        ? buttons.length - 1
                        : (currentIndex - 1 + buttons.length) %
                          buttons.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = buttons.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            buttons[nextIndex]?.focus();
        },
        []
    );

    // Reset to page 1 implicitly happens via tab change because the
    // `usePatternsList` hook re-creates its filter signature; this effect
    // keeps `setPage` honest if the parent later wires pagination chrome.
    useEffect(() => {
        // Intentionally empty — the hook key change re-fires the fetch.
    }, [activeTab]);

    const isLoading = status === 'loading' || status === 'idle';
    const isError = status === 'error';
    const isEmpty = status === 'ready' && items.length === 0;

    const tabLabel = (tab: SyncTabId): string => {
        return tab === 'synced'
            ? __('Synced', TEXT_DOMAIN)
            : __('Unsynced', TEXT_DOMAIN);
    };

    const tabPanelId = (tab: SyncTabId): string =>
        tab === 'synced' ? syncedPanelId : unsyncedPanelId;

    const tabButtonId = (tab: SyncTabId): string =>
        tab === 'synced' ? syncedTabId : unsyncedTabId;

    return (
        <div
            className="ap-patterns-browser"
            data-testid="ap-patterns-browser"
            data-active-tab={activeTab}
        >
            <div className="ap-patterns-browser__head">
                <h3 className="ap-patterns-browser__title">
                    {__('Patterns', TEXT_DOMAIN)}
                </h3>
                <button
                    type="button"
                    className="ap-patterns-browser__create"
                    data-testid="ap-patterns-browser-create"
                    onClick={() => onRequestCreate(activeTab === 'synced')}
                >
                    {__('Add new', TEXT_DOMAIN)}
                </button>
            </div>

            <div
                id={tabListId}
                className="ap-patterns-browser__tabs"
                role="tablist"
                aria-label={__('Pattern sync filter', TEXT_DOMAIN)}
            >
                <button
                    ref={syncedTabRef}
                    id={tabButtonId('synced')}
                    type="button"
                    role="tab"
                    className="ap-patterns-browser__tab"
                    aria-selected={activeTab === 'synced'}
                    aria-controls={tabPanelId('synced')}
                    tabIndex={activeTab === 'synced' ? 0 : -1}
                    data-testid="ap-patterns-browser-tab-synced"
                    onClick={() => onSelectTab('synced')}
                    onKeyDown={handleTabKey}
                >
                    {tabLabel('synced')}
                </button>
                <button
                    ref={unsyncedTabRef}
                    id={tabButtonId('unsynced')}
                    type="button"
                    role="tab"
                    className="ap-patterns-browser__tab"
                    aria-selected={activeTab === 'unsynced'}
                    aria-controls={tabPanelId('unsynced')}
                    tabIndex={activeTab === 'unsynced' ? 0 : -1}
                    data-testid="ap-patterns-browser-tab-unsynced"
                    onClick={() => onSelectTab('unsynced')}
                    onKeyDown={handleTabKey}
                >
                    {tabLabel('unsynced')}
                </button>
            </div>

            <div
                id={tabPanelId(activeTab)}
                role="tabpanel"
                aria-labelledby={tabButtonId(activeTab)}
                className="ap-patterns-browser__panel"
                data-testid="ap-patterns-browser-panel"
            >
                {isLoading ? (
                    <p
                        className="ap-patterns-browser__status"
                        role="status"
                        aria-live="polite"
                        data-testid="ap-patterns-browser-loading"
                    >
                        {__('Loading…', TEXT_DOMAIN)}
                    </p>
                ) : null}

                {isError ? (
                    <div
                        className="ap-patterns-browser__error"
                        role="alert"
                        data-testid="ap-patterns-browser-error"
                    >
                        <p>
                            {errorMessage ??
                                __('Failed to load patterns.', TEXT_DOMAIN)}
                        </p>
                        <button
                            type="button"
                            className="ap-patterns-browser__retry"
                            onClick={() => void refresh()}
                        >
                            {__('Retry', TEXT_DOMAIN)}
                        </button>
                    </div>
                ) : null}

                {isEmpty ? (
                    <div
                        className="ap-patterns-browser__empty"
                        data-testid="ap-patterns-browser-empty"
                    >
                        <p className="ap-patterns-browser__empty-title">
                            {activeTab === 'synced'
                                ? __('No synced patterns yet', TEXT_DOMAIN)
                                : __('No unsynced patterns yet', TEXT_DOMAIN)}
                        </p>
                        <p className="ap-patterns-browser__empty-body">
                            {activeTab === 'synced'
                                ? __(
                                      'Synced patterns are stored by reference — editing one updates every place it is inserted.',
                                      TEXT_DOMAIN
                                  )
                                : __(
                                      'Unsynced patterns are inserted as a copy of the block tree. Future edits to the pattern only affect new insertions.',
                                      TEXT_DOMAIN
                                  )}
                        </p>
                    </div>
                ) : null}

                {status === 'ready' && items.length > 0 ? (
                    <ul
                        className="ap-patterns-browser__list"
                        data-ap-patterns-list=""
                        data-testid="ap-patterns-browser-list"
                        aria-label={
                            activeTab === 'synced'
                                ? __('Synced patterns', TEXT_DOMAIN)
                                : __('Unsynced patterns', TEXT_DOMAIN)
                        }
                    >
                        {items.map((pattern, index) => {
                            const id = String(pattern.id);
                            const isActive = activeEntityId === id;
                            const hasActive = items.some(
                                (candidate) =>
                                    String(candidate.id) === activeEntityId
                            );
                            const isTabStop = hasActive
                                ? isActive
                                : index === 0;

                            return (
                                <li
                                    key={id}
                                    className="ap-patterns-browser__item"
                                >
                                    <button
                                        type="button"
                                        className="ap-patterns-browser__row"
                                        data-ap-patterns-row=""
                                        data-active={isActive}
                                        data-testid={`ap-patterns-browser-row-${id}`}
                                        aria-current={
                                            isActive ? 'true' : undefined
                                        }
                                        aria-label={sprintf(
                                            /* translators: %s: pattern title or slug. */
                                            __('Open pattern: %s', TEXT_DOMAIN),
                                            patternTitle(pattern)
                                        )}
                                        tabIndex={isTabStop ? 0 : -1}
                                        onClick={() => onOpen(id)}
                                        onKeyDown={handleRowKeyDown}
                                    >
                                        <span className="ap-patterns-browser__row-title">
                                            {patternTitle(pattern)}
                                        </span>
                                        <span className="ap-patterns-browser__row-meta">
                                            <code className="ap-patterns-browser__row-slug">
                                                {pattern.slug}
                                            </code>
                                            {pattern.categories.length > 0 ? (
                                                <span className="ap-patterns-browser__row-categories">
                                                    {pattern.categories.join(
                                                        ', '
                                                    )}
                                                </span>
                                            ) : null}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                ) : null}
            </div>
        </div>
    );
}
