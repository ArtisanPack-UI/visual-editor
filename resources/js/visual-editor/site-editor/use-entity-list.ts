/**
 * Paginated list hook for site-editor entities.
 *
 * Handles the `{ data, meta }` envelope the C1/C2 REST endpoints return,
 * tracks load/error status, and exposes a `refresh` callback so callers
 * can force a re-fetch after a create/update/delete. The hook deliberately
 * does not cache across mounts — the site-editor browses one section at a
 * time and the relative cost of refetching a list on re-open is negligible
 * compared to the complexity of invalidation.
 */

import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    listEntities,
    SiteEditorApiError,
    type EntityKind,
    type EntityRecord,
    type ListParams,
    type PaginatedResponse,
    type SiteEditorApiConfig,
} from './api-client';

export type EntityListStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface UseEntityListOptions<K extends EntityKind> extends ListParams {
    apiConfig: SiteEditorApiConfig;
    kind: K;
    /**
     * When `false`, the hook skips its initial fetch. Callers flip this
     * to `true` once prerequisites (e.g. apiBase being wired) are ready.
     * Defaults to `true` so the common path stays ergonomic.
     */
    enabled?: boolean;
    /**
     * Bump to force a re-fetch (e.g. after a sibling creates or deletes
     * a record). Any change to this value re-runs the list query with
     * the current filters. Omit when the hook's own filter state is the
     * only re-fetch trigger.
     */
    refreshKey?: number;
}

export interface UseEntityListResult<K extends EntityKind> {
    items: readonly EntityRecord<K>[];
    meta: PaginatedResponse<EntityRecord<K>>['meta'] | null;
    status: EntityListStatus;
    errorMessage: string | null;
    /** Re-fetches the current page with the same filters. */
    refresh: () => Promise<void>;
    /** Swaps the active page and triggers a fetch. */
    setPage: (page: number) => void;
    page: number;
}

export function useEntityList<K extends EntityKind>(
    options: UseEntityListOptions<K>
): UseEntityListResult<K> {
    const {
        apiConfig,
        kind,
        enabled = true,
        perPage,
        theme,
        slug,
        status: statusFilter,
        area,
        page: initialPage = 1,
        refreshKey = 0,
    } = options;

    const [items, setItems] = useState<readonly EntityRecord<K>[]>([]);
    const [meta, setMeta] = useState<PaginatedResponse<EntityRecord<K>>['meta'] | null>(null);
    const [status, setStatus] = useState<EntityListStatus>(enabled ? 'loading' : 'idle');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [page, setPage] = useState<number>(initialPage);

    // Track the most recent request so late-arriving responses can't
    // overwrite newer state (e.g. user types fast in a filter field).
    const requestCounterRef = useRef(0);

    const fetchList = useCallback(async (): Promise<void> => {
        if (!enabled) {
            return;
        }

        const requestId = ++requestCounterRef.current;

        setStatus('loading');
        setErrorMessage(null);

        try {
            const response = await listEntities(apiConfig, kind, {
                perPage,
                theme,
                slug,
                status: statusFilter,
                area,
                page,
            });

            if (requestCounterRef.current !== requestId) {
                return;
            }

            setItems(response.data);
            setMeta(response.meta);
            setStatus('ready');
        } catch (error: unknown) {
            if (requestCounterRef.current !== requestId) {
                return;
            }

            const message =
                error instanceof SiteEditorApiError
                    ? error.message
                    : __('Failed to load entities.', TEXT_DOMAIN);

            setItems([]);
            setMeta(null);
            setErrorMessage(message);
            setStatus('error');
        }
    }, [
        apiConfig,
        enabled,
        kind,
        perPage,
        theme,
        slug,
        statusFilter,
        area,
        page,
        refreshKey,
    ]);

    useEffect(() => {
        void fetchList();
    }, [fetchList]);

    return {
        items,
        meta,
        status,
        errorMessage,
        refresh: fetchList,
        setPage,
        page,
    };
}
