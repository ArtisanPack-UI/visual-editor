/**
 * Persistence hook for the visual editor.
 *
 * Fetches the block tree for a resource on mount, exposes the initial state
 * for `BlockEditorProvider`, and PUTs debounced changes back to the server.
 * Dedupes concurrent saves so the React editor can fire `onChange` on every
 * keystroke without overwhelming the Laravel endpoint.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import type { BlockInstance } from '@wordpress/blocks';

import {
    ApiError,
    fetchContent,
    saveContent,
    type ApiClientConfig,
} from './api-client';
import {
    VE_EDITOR_AUTOSAVE,
    VE_EDITOR_CHANGE,
    VE_EDITOR_SAVE,
    dispatchEditorEvent,
} from './editor-events';

type LoadStatus = 'loading' | 'ready' | 'error';
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
type SaveTrigger = 'autosave' | 'save';

export interface PersistenceState {
    blocks: BlockInstance[];
    loadStatus: LoadStatus;
    saveStatus: SaveStatus;
    loadError: ApiError | null;
    saveError: ApiError | null;
    lastSavedAt: string | null;
    /**
     * Mirror the new tree into React state *and* schedule a debounced save.
     * Used for persistent commits (`onChange`) — typing, drops, inserts —
     * where React state needs to reflect the latest value for undo/redo and
     * the controlled `<BlockEditorProvider value>` pattern.
     */
    onBlocksChange: (next: BlockInstance[]) => void;
    /**
     * Schedule a debounced save *without* calling `setBlocks`. Used for
     * intermediate events (`onInput` — e.g., color picker drag frames)
     * where re-rendering the whole editor tree on every frame causes
     * cascading update loops inside Gutenberg's block support hooks
     * (#343 A1). Saves still land because the debounce timer reads
     * `pendingRef.current` at flush time, not the React state.
     */
    queueBlocksForSave: (next: BlockInstance[]) => void;
    /**
     * Cancels the pending debounce timer and fires the save immediately.
     * Wired to the ⌘S shortcut in the top bar so explicit saves bypass the
     * 800ms coalescing window.
     */
    flush: () => void;
}

export interface UsePersistenceOptions extends ApiClientConfig {
    /**
     * Delay in milliseconds between the last change and the triggered save.
     * Defaults to 800ms so rapid edits coalesce into a single request.
     */
    debounceMs?: number;
}

// 1.5s debounce — long enough to coalesce drag-bursts from the color
// picker and fast typing (which helps avoid a known upstream Gutenberg
// render-backpressure issue during color drag) while still feeling
// responsive for normal edits.
const DEFAULT_DEBOUNCE_MS = 1500;

function toApiError(error: unknown, fallback: string): ApiError {
    return error instanceof ApiError ? error : new ApiError(fallback, 0, error);
}

export function usePersistence(
    options: UsePersistenceOptions
): PersistenceState {
    const { debounceMs = DEFAULT_DEBOUNCE_MS, apiBase, resource, id } = options;

    const [blocks, setBlocks] = useState<BlockInstance[]>([]);
    const [loadStatus, setLoadStatus] = useState<LoadStatus>('loading');
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [loadError, setLoadError] = useState<ApiError | null>(null);
    const [saveError, setSaveError] = useState<ApiError | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);

    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingRef = useRef<BlockInstance[] | null>(null);
    const inFlightRef = useRef<boolean>(false);
    const unmountedRef = useRef<boolean>(false);
    // Incremented on every target change so in-flight saves for the previous
    // (apiBase, resource, id) tuple can detect they've been superseded before
    // writing state or scheduling a trailing flush.
    const targetVersionRef = useRef<number>(0);
    const loadStatusRef = useRef<LoadStatus>('loading');

    loadStatusRef.current = loadStatus;

    useEffect(() => {
        unmountedRef.current = false;
        const version = ++targetVersionRef.current;
        pendingRef.current = null;
        inFlightRef.current = false;

        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        setLoadStatus('loading');
        setLoadError(null);
        setSaveStatus('idle');
        setSaveError(null);

        fetchContent({ apiBase, resource, id })
            .then((response) => {
                if (version !== targetVersionRef.current) {
                    return;
                }

                setBlocks(response.blocks as BlockInstance[]);
                setLastSavedAt(response.updated_at);
                setLoadStatus('ready');
            })
            .catch((error: unknown) => {
                if (version !== targetVersionRef.current) {
                    return;
                }

                setLoadError(toApiError(error, 'Failed to load content.'));
                setLoadStatus('error');
            });

        return () => {
            unmountedRef.current = true;
            pendingRef.current = null;

            if (timerRef.current !== null) {
                clearTimeout(timerRef.current);
                timerRef.current = null;
            }
        };
    }, [apiBase, resource, id]);

    const runFlush = useCallback(
        async function runFlush(trigger: SaveTrigger): Promise<void> {
            if (inFlightRef.current || unmountedRef.current) {
                return;
            }

            const next = pendingRef.current;

            if (next === null) {
                return;
            }

            const version = targetVersionRef.current;

            pendingRef.current = null;
            inFlightRef.current = true;
            setSaveStatus('saving');
            setSaveError(null);

            try {
                const response = await saveContent({ apiBase, resource, id }, next);

                if (unmountedRef.current || version !== targetVersionRef.current) {
                    return;
                }

                setLastSavedAt(response.updated_at);
                setSaveStatus('saved');

                dispatchEditorEvent(
                    trigger === 'save' ? VE_EDITOR_SAVE : VE_EDITOR_AUTOSAVE,
                    {
                        resource,
                        id,
                        blocks: next,
                        updatedAt: response.updated_at,
                    }
                );
            } catch (error: unknown) {
                if (unmountedRef.current || version !== targetVersionRef.current) {
                    return;
                }

                setSaveError(toApiError(error, 'Failed to save content.'));
                setSaveStatus('error');
            } finally {
                inFlightRef.current = false;
            }

            // A change landed while the save was in flight — drain the
            // trailing edit now so we don't lose it. Trailing drains always
            // count as autosaves: they're reacting to buffered edits, not a
            // fresh explicit Save press. Skip if the target changed during
            // the save.
            if (
                pendingRef.current !== null
                && !unmountedRef.current
                && version === targetVersionRef.current
            ) {
                void runFlush('autosave');
            }
        },
        [apiBase, resource, id]
    );

    const queueBlocksForSave = useCallback(
        (next: BlockInstance[]): void => {
            if (loadStatusRef.current !== 'ready') {
                return;
            }

            pendingRef.current = next;

            if (timerRef.current !== null) {
                clearTimeout(timerRef.current);
            }

            timerRef.current = setTimeout(() => {
                timerRef.current = null;
                // The change event is debounced: host listeners see one
                // event per coalesced edit burst, timed with the autosave
                // they're about to observe. `pendingRef.current` is used
                // instead of the closed-over `next` so trailing updates
                // land in the same event.
                const latest = pendingRef.current ?? next;
                dispatchEditorEvent(VE_EDITOR_CHANGE, {
                    resource,
                    id,
                    blocks: latest,
                });
                void runFlush('autosave');
            }, debounceMs);
        },
        [debounceMs, id, resource, runFlush]
    );

    const onBlocksChange = useCallback(
        (next: BlockInstance[]): void => {
            setBlocks(next);
            queueBlocksForSave(next);
        },
        [queueBlocksForSave]
    );

    const flush = useCallback((): void => {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        if (
            pendingRef.current === null
            || loadStatusRef.current !== 'ready'
        ) {
            return;
        }

        void runFlush('save');
    }, [runFlush]);

    return {
        blocks,
        loadStatus,
        saveStatus,
        loadError,
        saveError,
        lastSavedAt,
        onBlocksChange,
        queueBlocksForSave,
        flush,
    };
}

