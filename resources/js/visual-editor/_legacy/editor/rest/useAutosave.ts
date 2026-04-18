import { useEffect, useRef, useState } from 'react';
import type { EditorStore } from '../store';
import { PostRestError, savePost, type PostRestClientOptions } from './postRestClient';

export type AutosaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export interface AutosaveState {
    status: AutosaveStatus;
    lastSavedAt: number | null;
    lastError: Error | null;
    retryCount: number;
}

export interface UseAutosaveOptions {
    store: EditorStore;
    postId: string;
    clientOptions: PostRestClientOptions;
    debounceMs?: number;
    maxRetries?: number;
    retryBaseMs?: number;
    enabled?: boolean;
    now?: () => number;
    setTimeoutImpl?: typeof setTimeout;
    clearTimeoutImpl?: typeof clearTimeout;
}

const DEFAULT_DEBOUNCE_MS = 1500;
const DEFAULT_MAX_RETRIES = 3;
const DEFAULT_RETRY_BASE_MS = 1000;

const INITIAL_STATE: AutosaveState = {
    status: 'idle',
    lastSavedAt: null,
    lastError: null,
    retryCount: 0,
};

/**
 * Subscribes directly to the editor store's `isDirty` flag and debounces
 * `savePost` calls through it. React's rendering cycle is intentionally
 * bypassed for scheduling — re-rendering the owning component must not
 * cancel an in-flight debounce timer.
 */
export function useAutosave(options: UseAutosaveOptions): AutosaveState {
    const {
        store,
        postId,
        clientOptions,
        debounceMs = DEFAULT_DEBOUNCE_MS,
        maxRetries = DEFAULT_MAX_RETRIES,
        retryBaseMs = DEFAULT_RETRY_BASE_MS,
        enabled = true,
        now = Date.now,
        setTimeoutImpl,
        clearTimeoutImpl,
    } = options;

    const [state, setState] = useState<AutosaveState>(INITIAL_STATE);

    // Keep the most recent values in refs so the subscription effect can
    // read them without needing to resubscribe on every render.
    const configRef = useRef({
        store,
        postId,
        clientOptions,
        debounceMs,
        maxRetries,
        retryBaseMs,
        now,
        setTimeoutImpl,
        clearTimeoutImpl,
    });

    configRef.current = {
        store,
        postId,
        clientOptions,
        debounceMs,
        maxRetries,
        retryBaseMs,
        now,
        setTimeoutImpl,
        clearTimeoutImpl,
    };

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let timerId: ReturnType<typeof setTimeout> | null = null;
        let abortController: AbortController | null = null;
        let retryCount = 0;
        let disposed = false;
        let inFlight = false;

        function schedule(delay: number): void {
            const setter = configRef.current.setTimeoutImpl ?? setTimeout;
            const clearer = configRef.current.clearTimeoutImpl ?? clearTimeout;

            if (timerId !== null) {
                clearer(timerId);
            }

            timerId = setter(() => {
                timerId = null;
                void runSave();
            }, delay);
        }

        async function runSave(): Promise<void> {
            if (disposed) {
                return;
            }

            const {
                store: currentStore,
                postId: currentPostId,
                clientOptions: currentClient,
                retryBaseMs: currentRetryBase,
                maxRetries: currentMaxRetries,
                now: currentNow,
            } = configRef.current;

            const snapshot = currentStore.getState().blocks;

            if (abortController) {
                abortController.abort();
            }

            abortController = new AbortController();
            const controller = abortController;

            inFlight = true;

            setState((previous) => ({
                ...previous,
                status: 'saving',
                lastError: null,
            }));

            try {
                await savePost(currentPostId, snapshot, currentClient, {
                    signal: controller.signal,
                });
            } catch (error) {
                inFlight = false;

                if (controller.signal.aborted || disposed) {
                    return;
                }

                const wrapped = normalizeError(error);
                retryCount += 1;

                const exhausted = retryCount > currentMaxRetries;

                setState((previous) => ({
                    ...previous,
                    status: 'error',
                    lastError: wrapped,
                    retryCount: exhausted ? 0 : retryCount,
                }));

                if (exhausted) {
                    retryCount = 0;
                    return;
                }

                schedule(currentRetryBase * Math.pow(2, retryCount - 1));
                return;
            }

            inFlight = false;

            if (disposed) {
                return;
            }

            retryCount = 0;

            // Only clear the dirty flag when the snapshot we saved still
            // matches the current blocks — otherwise the user kept typing
            // mid-save and we need another pass.
            const latestBlocks = currentStore.getState().blocks;

            if (latestBlocks === snapshot) {
                currentStore.getState().markClean();
            }

            setState({
                status: 'saved',
                lastSavedAt: currentNow(),
                lastError: null,
                retryCount: 0,
            });

            // If the store drifted during the save, schedule another pass.
            if (latestBlocks !== snapshot && currentStore.getState().isDirty) {
                schedule(configRef.current.debounceMs);
            }
        }

        const unsubscribe = store.subscribe((next, previous) => {
            if (disposed) {
                return;
            }

            if (next.isDirty && !inFlight && !previous.isDirty) {
                schedule(configRef.current.debounceMs);
                return;
            }

            if (next.isDirty && !inFlight && timerId === null) {
                schedule(configRef.current.debounceMs);
            }
        });

        // If we mount with a dirty store, schedule immediately.
        if (store.getState().isDirty) {
            schedule(debounceMs);
        }

        return () => {
            disposed = true;
            const clearer = configRef.current.clearTimeoutImpl ?? clearTimeout;

            if (timerId !== null) {
                clearer(timerId);
                timerId = null;
            }

            if (abortController) {
                abortController.abort();
                abortController = null;
            }

            unsubscribe();
        };
        // Intentionally subscribe only once per store / enabled change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [store, enabled]);

    return state;
}

function normalizeError(error: unknown): Error {
    if (error instanceof PostRestError) {
        return error;
    }

    if (error instanceof Error) {
        return error;
    }

    return new Error(typeof error === 'string' ? error : 'Unknown autosave error');
}
