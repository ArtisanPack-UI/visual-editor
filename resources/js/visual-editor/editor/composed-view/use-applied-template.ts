/**
 * React hook that fetches the applied template for the composed view once
 * the toggle is first flipped on. Caches the result per `(resource, id)`
 * pair for the lifetime of the editor mount so subsequent flips are
 * instant.
 *
 * States:
 *   - `idle`     — hook has not been triggered yet (viewMode never left `content`).
 *   - `loading`  — fetch in flight.
 *   - `ok`       — template resolved; `.template` populated.
 *   - `missing`  — server returned the discriminated 404 payload.
 *   - `error`    — network / auth / unexpected failure.
 *
 * @since 1.1.0
 */

import { useCallback, useEffect, useRef, useState } from 'react';

import { ApiError } from '../api-client';
import {
    fetchAppliedTemplate,
    type AppliedTemplate,
    type AppliedTemplateConfig,
    type AppliedTemplateMissing,
} from './api';

export type AppliedTemplateState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'ok'; template: AppliedTemplate }
    | { status: 'missing'; missing: AppliedTemplateMissing }
    | { status: 'error'; error: Error };

export interface UseAppliedTemplateOptions extends AppliedTemplateConfig {
    /**
     * When `false` the hook stays in `idle` state. Flip to `true` on first
     * use of the composed view to defer the network call until it matters.
     */
    enabled: boolean;
}

export function useAppliedTemplate(
    options: UseAppliedTemplateOptions
): AppliedTemplateState {
    const { apiBase, resource, id, enabled } = options;
    const [state, setState] = useState<AppliedTemplateState>({ status: 'idle' });
    const cacheRef = useRef<{
        key: string;
        state: AppliedTemplateState;
    } | null>(null);

    const cacheKey = `${apiBase}::${resource}::${id}`;

    const run = useCallback(async (): Promise<void> => {
        setState({ status: 'loading' });

        try {
            const result = await fetchAppliedTemplate({ apiBase, resource, id });

            const next: AppliedTemplateState =
                result.status === 'ok'
                    ? { status: 'ok', template: result.template }
                    : { status: 'missing', missing: result };

            cacheRef.current = { key: cacheKey, state: next };
            setState(next);
        } catch (error: unknown) {
            const normalized =
                error instanceof ApiError
                    ? error
                    : error instanceof Error
                        ? error
                        : new Error('Failed to load applied template.');

            const next: AppliedTemplateState = {
                status: 'error',
                error: normalized,
            };

            cacheRef.current = { key: cacheKey, state: next };
            setState(next);
        }
    }, [apiBase, cacheKey, id, resource]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        if (cacheRef.current !== null && cacheRef.current.key === cacheKey) {
            setState(cacheRef.current.state);

            return;
        }

        void run();
    }, [cacheKey, enabled, run]);

    return state;
}
