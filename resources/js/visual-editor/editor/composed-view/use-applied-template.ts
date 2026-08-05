/**
 * React hook that fetches the applied template for the composed view once
 * the toggle is first flipped on. Caches the result per
 * `(resource, id, template)` triple for the lifetime of the editor mount so
 * subsequent flips are instant.
 *
 * `template` is part of the key on purpose: picking a different template in
 * the document panel has to invalidate the cached preview, otherwise the
 * composed view keeps showing the previous template until the editor
 * remounts. The slug also rides along on the request so the response
 * reflects the selection rather than whatever the debounced save has
 * managed to persist so far.
 *
 * States:
 *   - `idle`     — hook has not been triggered yet (viewMode never left `content`).
 *   - `loading`  — fetch in flight.
 *   - `ok`       — template resolved; `.template` populated.
 *   - `missing`  — server returned the discriminated `missing` payload.
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

/**
 * Cache entries are keyed per hook instance (a `useRef`), so this only ever
 * holds the templates one editor mount has looked at.
 */

export function useAppliedTemplate(
    options: UseAppliedTemplateOptions
): AppliedTemplateState {
    const { apiBase, resource, id, template, enabled } = options;
    const [state, setState] = useState<AppliedTemplateState>({ status: 'idle' });
    const cacheRef = useRef<{
        key: string;
        state: AppliedTemplateState;
    } | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const cacheKey = `${apiBase}::${resource}::${id}::${template ?? ''}`;

    // The key the hook currently wants a result for. `aborted` alone can't
    // carry this: the cache-hit path below settles a key without going
    // through `run()`, so an earlier fetch could still be in flight and
    // unaborted when it does.
    //
    // Written in the effect, never in the render body: React can render a
    // new `cacheKey` and discard that render without committing it, which
    // would leave this pointing at a key that never took effect — and the
    // in-flight request for the committed key would then be dropped as
    // stale, stranding the hook in `loading` with no chrome and no error.
    const latestKeyRef = useRef<string>(cacheKey);

    const run = useCallback(async (): Promise<void> => {
        abortRef.current?.abort();

        const controller = new AbortController();

        abortRef.current = controller;

        // Captured before the await so a response that arrives after the
        // selection moved on commits under the key it was requested for —
        // and the staleness checks below drop it entirely.
        const key = cacheKey;

        setState({ status: 'loading' });

        try {
            const result = await fetchAppliedTemplate({
                apiBase,
                resource,
                id,
                template,
                signal: controller.signal,
            });

            if (controller.signal.aborted || latestKeyRef.current !== key) {
                return;
            }

            const next: AppliedTemplateState =
                result.status === 'ok'
                    ? { status: 'ok', template: result }
                    : { status: 'missing', missing: result };

            cacheRef.current = { key, state: next };
            setState(next);
        } catch (error: unknown) {
            if (controller.signal.aborted || latestKeyRef.current !== key) {
                return;
            }

            const normalized =
                error instanceof ApiError
                    ? error
                    : error instanceof Error
                        ? error
                        : new Error('Failed to load applied template.');

            // Deliberately not cached: a transient failure must not lock the
            // author out of the composed view for the rest of the mount. The
            // next toggle-on misses the cache and refetches.
            setState({ status: 'error', error: normalized });
        }
    }, [apiBase, cacheKey, id, resource, template]);

    useEffect(() => (): void => {
        abortRef.current?.abort();
    }, []);

    useEffect(() => {
        latestKeyRef.current = cacheKey;

        if (!enabled) {
            return;
        }

        if (cacheRef.current !== null && cacheRef.current.key === cacheKey) {
            // Abort whatever `run()` last started: this key is settling from
            // cache, so an older in-flight response has nothing to add and
            // would otherwise land on top of the state set here.
            abortRef.current?.abort();
            setState(cacheRef.current.state);

            return;
        }

        void run();
    }, [cacheKey, enabled, run]);

    return state;
}
