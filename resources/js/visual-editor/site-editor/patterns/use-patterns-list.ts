/**
 * Paginated list hook for patterns.
 *
 * Mirrors `use-entity-list.ts` (template/template-part) but typed against
 * the patterns-specific record + filter shape. Patterns ship with two
 * filter axes (sync status + categories) that the generic entity list
 * doesn't model, so the patterns section gets its own hook rather than
 * widening the shared one.
 */

import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';

import {
    listPatterns,
    SiteEditorApiError,
    type PatternListParams,
    type PatternRecord,
} from './api-client';

export type PatternsListStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface UsePatternsListOptions extends PatternListParams {
    apiConfig: SiteEditorApiConfig;
    enabled?: boolean;
    /** Bump to force a re-fetch with the same filters. */
    refreshKey?: number;
}

export interface UsePatternsListResult {
    items: readonly PatternRecord[];
    status: PatternsListStatus;
    errorMessage: string | null;
    refresh: () => Promise<void>;
    page: number;
    setPage: (page: number) => void;
    total: number;
}

export function usePatternsList(
    options: UsePatternsListOptions
): UsePatternsListResult {
    const {
        apiConfig,
        enabled = true,
        perPage = 25,
        synced,
        categories,
        slug,
        status: statusFilter,
        page: initialPage = 1,
        refreshKey = 0,
    } = options;

    const [items, setItems] = useState<readonly PatternRecord[]>([]);
    const [status, setStatus] = useState<PatternsListStatus>(
        enabled ? 'loading' : 'idle'
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [page, setPage] = useState<number>(initialPage);
    const [total, setTotal] = useState<number>(0);

    const requestCounterRef = useRef(0);

    // Re-create the categories signature so React can compare arrays
    // by value rather than reference — callers passing a fresh array on
    // every render would otherwise re-fire the fetch.
    const categoriesSignature =
        categories === undefined ? '' : categories.join('|');

    const fetchList = useCallback(async (): Promise<void> => {
        if (!enabled) {
            return;
        }

        const requestId = ++requestCounterRef.current;

        setStatus('loading');
        setErrorMessage(null);

        try {
            const response = await listPatterns(apiConfig, {
                perPage,
                page,
                synced,
                categories,
                slug,
                status: statusFilter,
            });

            if (requestCounterRef.current !== requestId) {
                return;
            }

            setItems(response.data);
            setTotal(response.meta.total);
            setStatus('ready');
        } catch (error: unknown) {
            if (requestCounterRef.current !== requestId) {
                return;
            }

            const message =
                error instanceof SiteEditorApiError
                    ? error.message
                    : __('Failed to load patterns.', TEXT_DOMAIN);

            setItems([]);
            setTotal(0);
            setErrorMessage(message);
            setStatus('error');
        }
        // `categories` itself is excluded from the dep list because the
        // signature string captures its contents — listing the array
        // would re-fire the fetch on every render that builds a fresh
        // identity, even when nothing changed.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        apiConfig,
        enabled,
        perPage,
        page,
        synced,
        categoriesSignature,
        slug,
        statusFilter,
        refreshKey,
    ]);

    useEffect(() => {
        void fetchList();
    }, [fetchList]);

    // Drop the list back to idle when the parent disables the hook
    // (e.g. switching away from the patterns section). Without this,
    // the status would freeze on whatever state was active when the
    // section unmounted, so a later re-enable would briefly leak
    // stale loading/error chrome before the next fetch settled.
    useEffect(() => {
        if (!enabled) {
            setStatus('idle');
            setErrorMessage(null);
        }
    }, [enabled]);

    return {
        items,
        status,
        errorMessage,
        refresh: fetchList,
        page,
        setPage,
        total,
    };
}
