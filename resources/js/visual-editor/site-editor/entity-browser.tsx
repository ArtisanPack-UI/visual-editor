/**
 * Shared browser component for template + template-part sections.
 *
 * Mounted inside the navigator-outlet slot per D0 §3.4 / §3.5, this is
 * the list surface users scan to pick an entity to open in the canvas.
 * Templates and parts share 80% of the interaction model — filter chips,
 * grouped list, row click opens-in-canvas, create-new button — so D2
 * parameterises the component by `kind` + a pluggable chip set and row
 * renderer rather than shipping two near-identical implementations.
 *
 * Deliberately list-only, no card grid: the issue's in-scope copy calls
 * for the navigator sub-panel list as the entry surface; the card-grid
 * canvas (D0 §3.4) is polish for post-V1 when thumbnails are available.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
    useCallback,
    useMemo,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type ReactNode,
} from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    type EntityKind,
    type EntityRecord,
    type SiteEditorApiConfig,
} from './api-client';
import { useEntityList } from './use-entity-list';

import './entity-browser.css';

export interface FilterChip {
    id: string;
    label: string;
    /** Passed to the list endpoint as the relevant filter query param. */
    filter?: { status?: string; area?: string; source?: string };
}

export interface EntityBrowserProps<K extends EntityKind> {
    apiConfig: SiteEditorApiConfig;
    kind: K;
    /** Active entity id from the URL, or `null` in list mode. */
    activeEntityId: string | null;
    /** Opens an entity in the canvas. */
    onOpen: (entityId: string) => void;
    /** Opens the create-new dialog. */
    onRequestCreate: () => void;
    /** Optional filter chips (rendered above the list). */
    chips?: readonly FilterChip[];
    /** Label for the list region (screen readers). */
    listLabel: string;
    /** Empty-state heading. */
    emptyTitle: string;
    /** Empty-state body copy. */
    emptyBody: string;
    /** "Add new" button label. */
    createLabel: string;
    /** Label describing a single row for "open" aria context. */
    openAriaLabel: (entity: EntityRecord<K>) => string;
    /** Renders the row contents (title, meta, badges). */
    renderRow: (entity: EntityRecord<K>) => ReactNode;
    /**
     * Force a re-fetch when the parent creates or deletes a record.
     * Unused for in-browser filter changes — those already re-fetch via
     * the chip handler.
     */
    refreshKey?: number;
}

const DEFAULT_PER_PAGE = 25;

function extractTitle(entity: { title?: { rendered?: string }; slug?: string }): string {
    const rendered = entity.title?.rendered?.trim();

    if (rendered !== undefined && rendered !== '') {
        return rendered;
    }

    return entity.slug ?? '';
}

export function EntityBrowser<K extends EntityKind>(
    props: EntityBrowserProps<K>
): JSX.Element {
    const {
        apiConfig,
        kind,
        activeEntityId,
        onOpen,
        onRequestCreate,
        chips,
        listLabel,
        emptyTitle,
        emptyBody,
        createLabel,
        openAriaLabel,
        renderRow,
        refreshKey,
    } = props;

    const [activeChipId, setActiveChipId] = useState<string | null>(
        chips !== undefined && chips.length > 0 ? (chips[0]?.id ?? null) : null
    );

    const activeChip = useMemo(
        () =>
            chips !== undefined
                ? (chips.find((chip) => chip.id === activeChipId) ?? null)
                : null,
        [activeChipId, chips]
    );

    const { items, status, errorMessage, refresh } = useEntityList({
        apiConfig,
        kind,
        perPage: DEFAULT_PER_PAGE,
        status: activeChip?.filter?.status,
        area: activeChip?.filter?.area,
        refreshKey,
    });

    // Keyboard navigation across the row buttons — Up/Down walks the
    // list, Home/End jumps to the ends. Roving tabindex keeps keyboard
    // users from tabbing through every row when they just want to scan.
    const handleRowKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLElement>): void => {
            const list = event.currentTarget.closest(
                '[data-ap-site-editor-entity-list]'
            );

            if (list === null) {
                return;
            }

            const buttons = Array.from(
                list.querySelectorAll<HTMLButtonElement>(
                    'button[data-ap-site-editor-entity-row]'
                )
            );

            if (buttons.length === 0) {
                return;
            }

            const activeElement = document.activeElement;
            const activeButton =
                activeElement instanceof HTMLButtonElement ? activeElement : null;
            const currentIndex = activeButton === null ? -1 : buttons.indexOf(activeButton);

            let nextIndex: number | null = null;

            if (event.key === 'ArrowDown') {
                nextIndex =
                    currentIndex === -1 ? 0 : (currentIndex + 1) % buttons.length;
            } else if (event.key === 'ArrowUp') {
                nextIndex =
                    currentIndex === -1
                        ? buttons.length - 1
                        : (currentIndex - 1 + buttons.length) % buttons.length;
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

    const isLoading = status === 'loading' || status === 'idle';
    const isError = status === 'error';
    const isEmpty = status === 'ready' && items.length === 0;

    return (
        <div
            className="ap-site-editor__entity-browser"
            data-testid={`ap-site-editor-entity-browser-${kind}`}
        >
            <div className="ap-site-editor__entity-browser-head">
                <h3 className="ap-site-editor__entity-browser-title">{listLabel}</h3>
                <button
                    type="button"
                    className="ap-site-editor__entity-browser-create"
                    data-testid={`ap-site-editor-entity-browser-create-${kind}`}
                    onClick={onRequestCreate}
                >
                    {createLabel}
                </button>
            </div>

            {chips !== undefined && chips.length > 0 ? (
                <div
                    className="ap-site-editor__entity-browser-chips"
                    role="group"
                    aria-label={__('Filters', TEXT_DOMAIN)}
                >
                    {chips.map((chip) => {
                        const isActive = chip.id === activeChipId;

                        return (
                            <button
                                key={chip.id}
                                type="button"
                                className="ap-site-editor__entity-browser-chip"
                                data-testid={`ap-site-editor-entity-browser-chip-${kind}-${chip.id}`}
                                aria-pressed={isActive}
                                onClick={() => setActiveChipId(chip.id)}
                            >
                                {chip.label}
                            </button>
                        );
                    })}
                </div>
            ) : null}

            {isLoading ? (
                <p
                    className="ap-site-editor__entity-browser-status"
                    role="status"
                    aria-live="polite"
                    data-testid={`ap-site-editor-entity-browser-loading-${kind}`}
                >
                    {__('Loading…', TEXT_DOMAIN)}
                </p>
            ) : null}

            {isError ? (
                <div
                    className="ap-site-editor__entity-browser-error"
                    role="alert"
                    data-testid={`ap-site-editor-entity-browser-error-${kind}`}
                >
                    <p>
                        {errorMessage ??
                            __('Failed to load the list.', TEXT_DOMAIN)}
                    </p>
                    <button
                        type="button"
                        className="ap-site-editor__entity-browser-retry"
                        onClick={() => void refresh()}
                    >
                        {__('Retry', TEXT_DOMAIN)}
                    </button>
                </div>
            ) : null}

            {isEmpty ? (
                <div
                    className="ap-site-editor__entity-browser-empty"
                    data-testid={`ap-site-editor-entity-browser-empty-${kind}`}
                >
                    <p className="ap-site-editor__entity-browser-empty-title">
                        {emptyTitle}
                    </p>
                    <p className="ap-site-editor__entity-browser-empty-body">
                        {emptyBody}
                    </p>
                </div>
            ) : null}

            {status === 'ready' && items.length > 0 ? (
                <ul
                    className="ap-site-editor__entity-browser-list"
                    data-ap-site-editor-entity-list=""
                    data-testid={`ap-site-editor-entity-browser-list-${kind}`}
                    aria-label={listLabel}
                >
                    {items.map((entity, index) => {
                        const entityId = String(entity.id);
                        const isActive = activeEntityId === entityId;
                        const hasActive = items.some(
                            (candidate) => String(candidate.id) === activeEntityId
                        );
                        // Roving tabindex: one row must always be tab-reachable.
                        // Prefer the active entity; fall back to the first row
                        // when nothing is active so the list is reachable from
                        // the keyboard out of the box.
                        const isTabStop = hasActive ? isActive : index === 0;
                        const title = extractTitle(entity);

                        return (
                            <li
                                key={entityId}
                                className="ap-site-editor__entity-browser-item"
                            >
                                <button
                                    type="button"
                                    className="ap-site-editor__entity-browser-row"
                                    data-ap-site-editor-entity-row=""
                                    data-active={isActive}
                                    data-testid={`ap-site-editor-entity-browser-row-${kind}-${entityId}`}
                                    aria-current={isActive ? 'true' : undefined}
                                    aria-label={sprintf(
                                        /* translators: %s: row description (e.g. "Open template: Single post"). */
                                        __('Open %s', TEXT_DOMAIN),
                                        openAriaLabel(entity)
                                    )}
                                    tabIndex={isTabStop ? 0 : -1}
                                    onClick={() => onOpen(entityId)}
                                    onKeyDown={handleRowKeyDown}
                                >
                                    <span className="ap-site-editor__entity-browser-row-title">
                                        {title || entity.slug}
                                    </span>
                                    <span className="ap-site-editor__entity-browser-row-meta">
                                        {renderRow(entity)}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
        </div>
    );
}
