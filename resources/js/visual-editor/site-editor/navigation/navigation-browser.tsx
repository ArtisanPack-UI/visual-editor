/**
 * Navigation browser — the left rail's menu list.
 *
 * Lists `wp_navigation` records via the C4 index endpoint. Mirrors the
 * `EntityBrowser` ergonomics (list rows, "Add new" button, selection
 * state) without depending on it: nav rows show extra columns
 * (location, item count) that don't fit the templates browser shape.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import type { SiteEditorApiConfig } from '../api-client';
import {
    listNavigations,
    type NavigationRecord,
} from './api-client';
import { LocationsPanel } from './locations-panel';
import type { MenuLocation } from './api-client';

import './navigation-browser.css';

export interface NavigationBrowserProps {
    apiConfig: SiteEditorApiConfig;
    activeEntityId: string | null;
    onOpen: (entityId: string) => void;
    onRequestCreate: () => void;
    /** Bumped after a successful create / save to refresh the list. */
    refreshKey?: number;
    locations: readonly MenuLocation[];
    isLocationsLoading: boolean;
    locationsError: string | null;
    onAssignLocation: (
        locationSlug: string,
        navigationId: number | null
    ) => Promise<void>;
}

interface ListState {
    status: 'idle' | 'loading' | 'ready' | 'error';
    rows: readonly NavigationRecord[];
    errorMessage: string | null;
}

const INITIAL_LIST: ListState = {
    status: 'idle',
    rows: [],
    errorMessage: null,
};

export function NavigationBrowser(
    props: NavigationBrowserProps
): JSX.Element {
    const {
        apiConfig,
        activeEntityId,
        onOpen,
        onRequestCreate,
        refreshKey = 0,
        locations,
        isLocationsLoading,
        locationsError,
        onAssignLocation,
    } = props;

    const [state, setState] = useState<ListState>(INITIAL_LIST);

    useEffect(() => {
        let cancelled = false;

        setState({ status: 'loading', rows: [], errorMessage: null });

        (async () => {
            try {
                const response = await listNavigations(apiConfig, {
                    perPage: 50,
                });

                if (cancelled) {
                    return;
                }

                setState({
                    status: 'ready',
                    rows: response.data,
                    errorMessage: null,
                });
            } catch (error: unknown) {
                if (cancelled) {
                    return;
                }

                setState({
                    status: 'error',
                    rows: [],
                    errorMessage:
                        error instanceof Error
                            ? error.message
                            : __(
                                  'Failed to load navigation menus.',
                                  TEXT_DOMAIN
                              ),
                });
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [apiConfig, refreshKey]);

    const locationLabelBySlug = useMemo(() => {
        const map = new Map<string, string>();

        for (const location of locations) {
            map.set(location.slug, location.label);
        }

        return map;
    }, [locations]);

    const itemCountForRow = (row: NavigationRecord): number => {
        return countNavigationLeaves(row.content.blocks);
    };

    return (
        <div
            className="ap-nav-browser"
            data-testid="ap-nav-browser"
        >
            <header className="ap-nav-browser__header">
                <h2 className="ap-nav-browser__title">
                    {__('Menus', TEXT_DOMAIN)}
                </h2>
                <button
                    type="button"
                    className="ap-nav-browser__create"
                    onClick={onRequestCreate}
                    data-testid="ap-nav-browser-create"
                >
                    {__('Add new', TEXT_DOMAIN)}
                </button>
            </header>

            {state.status === 'loading' ? (
                <p className="ap-nav-browser__status" role="status">
                    {__('Loading menus…', TEXT_DOMAIN)}
                </p>
            ) : null}

            {state.status === 'error' ? (
                <p className="ap-nav-browser__status" role="alert">
                    {state.errorMessage}
                </p>
            ) : null}

            {state.status === 'ready' && state.rows.length === 0 ? (
                <p className="ap-nav-browser__empty">
                    {__(
                        'No menus yet. Create one to get started.',
                        TEXT_DOMAIN
                    )}
                </p>
            ) : null}

            {state.status === 'ready' && state.rows.length > 0 ? (
                <ul
                    className="ap-nav-browser__list"
                    aria-label={__('Navigation menus', TEXT_DOMAIN)}
                >
                    {state.rows.map((row) => {
                        const isActive =
                            activeEntityId !== null &&
                            String(row.id) === activeEntityId;

                        const locationLabel =
                            row.location !== null
                                ? locationLabelBySlug.get(row.location) ??
                                  row.location
                                : null;

                        return (
                            <li
                                key={row.id}
                                className="ap-nav-browser__row"
                                data-active={isActive}
                            >
                                <button
                                    type="button"
                                    className="ap-nav-browser__row-button"
                                    onClick={() => onOpen(String(row.id))}
                                    aria-label={sprintf(
                                        /* translators: %s: menu title or slug. */
                                        __('Open menu: %s', TEXT_DOMAIN),
                                        row.title.rendered === ''
                                            ? row.slug
                                            : row.title.rendered
                                    )}
                                    data-testid={`ap-nav-browser-row-${row.id}`}
                                >
                                    <span className="ap-nav-browser__row-title">
                                        {row.title.rendered === ''
                                            ? row.slug
                                            : row.title.rendered}
                                    </span>
                                    <span className="ap-nav-browser__row-meta">
                                        {locationLabel !== null
                                            ? sprintf(
                                                  /* translators: %s: location label. */
                                                  __(
                                                      'Location: %s',
                                                      TEXT_DOMAIN
                                                  ),
                                                  locationLabel
                                              )
                                            : __(
                                                  'No location',
                                                  TEXT_DOMAIN
                                              )}
                                        {' · '}
                                        {sprintf(
                                            /* translators: %d: number of items. */
                                            __('%d items', TEXT_DOMAIN),
                                            itemCountForRow(row)
                                        )}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}

            <LocationsPanel
                locations={locations}
                isLoading={isLocationsLoading}
                errorMessage={locationsError}
                navigations={state.rows}
                onAssign={onAssignLocation}
            />
        </div>
    );
}

/**
 * Recursively counts `core/navigation-link` and
 * `core/navigation-submenu` blocks anywhere in the tree. Used as a
 * cheap "items" stat — exact figure isn't load-bearing, just a hint.
 */
function countNavigationLeaves(blocks: readonly unknown[]): number {
    let count = 0;

    for (const raw of blocks) {
        if (raw === null || typeof raw !== 'object') {
            continue;
        }

        const block = raw as { name?: unknown; innerBlocks?: unknown };

        if (block.name === 'core/navigation-link') {
            count += 1;
        }

        if (block.name === 'core/navigation-submenu') {
            count += 1;

            if (Array.isArray(block.innerBlocks)) {
                count += countNavigationLeaves(
                    block.innerBlocks as readonly unknown[]
                );
            }
        }
    }

    return count;
}
