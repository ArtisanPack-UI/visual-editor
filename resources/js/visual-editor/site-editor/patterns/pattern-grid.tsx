/**
 * Patterns canvas grid (list mode).
 *
 * Renders the active sync-tab's patterns as a card grid per design brief
 * §3.6. Each card shows a thumbnail, title, slug, sync status badge, and
 * three actions: Edit, Convert to unsynced copy (synced patterns only,
 * per F6 / P9), and Delete.
 *
 * Per P9 the "Convert to unsynced copy" action does not appear on
 * unsynced patterns and never carries the legacy "Detach" wording.
 */

import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    type KeyboardEvent as ReactKeyboardEvent,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';

import { type PatternRecord } from './api-client';
import { PatternThumbnail } from './pattern-thumbnail';
import { usePatternsList } from './use-patterns-list';

import './pattern-grid.css';

export interface PatternGridProps {
    apiConfig: SiteEditorApiConfig;
    synced: boolean;
    activeEntityId: string | null;
    refreshKey?: number;
    onEdit: (id: string) => void;
    onConvertToUnsynced: (pattern: PatternRecord) => void;
    onDelete: (pattern: PatternRecord) => void;
    onCreate: (synced: boolean) => void;
}

function patternTitle(pattern: PatternRecord): string {
    // Prefer the user-authored `raw` title over `rendered` so labels
    // don't surface HTML-escaped entities (`&amp;`, `&#039;`, …) the
    // REST renderer applies to the public form. Fall back to slug if
    // both are absent.
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

export function PatternGrid(props: PatternGridProps): JSX.Element {
    const {
        apiConfig,
        synced,
        activeEntityId,
        refreshKey,
        onEdit,
        onConvertToUnsynced,
        onDelete,
        onCreate,
    } = props;

    const { items, status, errorMessage, refresh } = usePatternsList({
        apiConfig,
        synced,
        refreshKey,
    });

    const gridRef = useRef<HTMLDivElement | null>(null);

    // Push fetched synced patterns into the core-data shim cache so
    // any `core/block` references opened from this surface (e.g. via
    // the post-editor's inserter in another tab, or via the patterns
    // canvas itself) can resolve their refs without bouncing back to
    // the network. The hook is a no-op for the unsynced tab.
    const coreDispatch = useDispatch('core') as
        | {
              receiveEntityRecords?: (
                  kind: string,
                  name: string,
                  records: readonly Record<string, unknown>[]
              ) => void;
          }
        | null
        | undefined;

    useEffect(() => {
        if (!synced || items.length === 0) {
            return;
        }

        const receive = coreDispatch?.receiveEntityRecords;

        if (typeof receive === 'function') {
            receive(
                'postType',
                'wp_block',
                items as unknown as readonly Record<string, unknown>[]
            );
        }
    }, [coreDispatch, items, synced]);

    const handleCardKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLElement>): void => {
            if (
                event.key !== 'ArrowDown' &&
                event.key !== 'ArrowUp' &&
                event.key !== 'ArrowLeft' &&
                event.key !== 'ArrowRight' &&
                event.key !== 'Home' &&
                event.key !== 'End'
            ) {
                return;
            }

            const grid = gridRef.current;

            if (grid === null) {
                return;
            }

            const focusables = Array.from(
                grid.querySelectorAll<HTMLButtonElement>(
                    'button[data-ap-pattern-card-edit]'
                )
            );

            if (focusables.length === 0) {
                return;
            }

            const active = document.activeElement;
            const activeButton =
                active instanceof HTMLButtonElement ? active : null;
            const currentIndex =
                activeButton === null
                    ? -1
                    : focusables.indexOf(activeButton);

            let nextIndex: number | null = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex =
                    currentIndex === -1
                        ? 0
                        : (currentIndex + 1) % focusables.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex =
                    currentIndex === -1
                        ? focusables.length - 1
                        : (currentIndex - 1 + focusables.length) %
                          focusables.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = focusables.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            focusables[nextIndex]?.focus();
        },
        []
    );

    const isLoading = status === 'loading' || status === 'idle';
    const isError = status === 'error';
    const isEmpty = status === 'ready' && items.length === 0;

    const headingId = useMemo(
        () =>
            synced
                ? __('Synced patterns', TEXT_DOMAIN)
                : __('Unsynced patterns', TEXT_DOMAIN),
        [synced]
    );

    return (
        <div
            className="ap-pattern-grid"
            data-testid="ap-pattern-grid"
            data-synced={synced}
        >
            <div className="ap-pattern-grid__header">
                <h2 className="ap-pattern-grid__heading">{headingId}</h2>
                <button
                    type="button"
                    className="ap-pattern-grid__create"
                    data-testid="ap-pattern-grid-create"
                    onClick={() => onCreate(synced)}
                >
                    {synced
                        ? __('New synced pattern', TEXT_DOMAIN)
                        : __('New unsynced pattern', TEXT_DOMAIN)}
                </button>
            </div>

            {isLoading ? (
                <p
                    className="ap-pattern-grid__status"
                    role="status"
                    aria-live="polite"
                    data-testid="ap-pattern-grid-loading"
                >
                    {__('Loading patterns…', TEXT_DOMAIN)}
                </p>
            ) : null}

            {isError ? (
                <div
                    className="ap-pattern-grid__error"
                    role="alert"
                    data-testid="ap-pattern-grid-error"
                >
                    <p>
                        {errorMessage ??
                            __('Failed to load patterns.', TEXT_DOMAIN)}
                    </p>
                    <button
                        type="button"
                        className="ap-pattern-grid__retry"
                        onClick={() => void refresh()}
                    >
                        {__('Retry', TEXT_DOMAIN)}
                    </button>
                </div>
            ) : null}

            {isEmpty ? (
                <div
                    className="ap-pattern-grid__empty"
                    data-testid="ap-pattern-grid-empty"
                >
                    <p className="ap-pattern-grid__empty-title">
                        {synced
                            ? __('No synced patterns yet.', TEXT_DOMAIN)
                            : __('No unsynced patterns yet.', TEXT_DOMAIN)}
                    </p>
                    <p className="ap-pattern-grid__empty-body">
                        {synced
                            ? __(
                                  'Create a synced pattern to share a block tree across templates and posts. Editing a synced pattern updates every place it is inserted.',
                                  TEXT_DOMAIN
                              )
                            : __(
                                  'Create an unsynced pattern to drop a starting block tree at insertion time. Each insertion is a copy — later edits to the pattern do not propagate.',
                                  TEXT_DOMAIN
                              )}
                    </p>
                </div>
            ) : null}

            {status === 'ready' && items.length > 0 ? (
                <div
                    ref={gridRef}
                    className="ap-pattern-grid__cards"
                    data-testid="ap-pattern-grid-cards"
                    role="list"
                    aria-label={headingId}
                >
                    {items.map((pattern) => {
                        const id = String(pattern.id);
                        const isActive = activeEntityId === id;

                        return (
                            <article
                                key={id}
                                className="ap-pattern-card"
                                data-testid={`ap-pattern-card-${id}`}
                                data-active={isActive}
                                role="listitem"
                            >
                                <PatternThumbnail
                                    blocks={pattern.content.blocks}
                                    title={patternTitle(pattern)}
                                />
                                <header className="ap-pattern-card__header">
                                    <h3 className="ap-pattern-card__title">
                                        {patternTitle(pattern)}
                                    </h3>
                                    <span
                                        className="ap-pattern-card__badge"
                                        data-synced={pattern.synced}
                                        data-testid={`ap-pattern-card-badge-${id}`}
                                    >
                                        {pattern.synced
                                            ? __('Synced', TEXT_DOMAIN)
                                            : __('Unsynced', TEXT_DOMAIN)}
                                    </span>
                                </header>
                                <p className="ap-pattern-card__slug">
                                    <code>{pattern.slug}</code>
                                </p>
                                {pattern.categories.length > 0 ? (
                                    <p className="ap-pattern-card__categories">
                                        {pattern.categories.join(', ')}
                                    </p>
                                ) : null}
                                <div className="ap-pattern-card__actions">
                                    <button
                                        type="button"
                                        className="ap-pattern-card__action ap-pattern-card__action--primary"
                                        data-ap-pattern-card-edit=""
                                        data-testid={`ap-pattern-card-edit-${id}`}
                                        aria-label={sprintf(
                                            /* translators: %s: pattern title. */
                                            __('Edit pattern: %s', TEXT_DOMAIN),
                                            patternTitle(pattern)
                                        )}
                                        onClick={() => onEdit(id)}
                                        onKeyDown={handleCardKeyDown}
                                    >
                                        {__('Edit', TEXT_DOMAIN)}
                                    </button>
                                    {pattern.synced ? (
                                        <button
                                            type="button"
                                            className="ap-pattern-card__action"
                                            data-testid={`ap-pattern-card-convert-${id}`}
                                            onClick={() =>
                                                onConvertToUnsynced(pattern)
                                            }
                                        >
                                            {__(
                                                'Convert to unsynced copy',
                                                TEXT_DOMAIN
                                            )}
                                        </button>
                                    ) : null}
                                    <button
                                        type="button"
                                        className="ap-pattern-card__action ap-pattern-card__action--danger"
                                        data-testid={`ap-pattern-card-delete-${id}`}
                                        onClick={() => onDelete(pattern)}
                                    >
                                        {__('Delete', TEXT_DOMAIN)}
                                    </button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
