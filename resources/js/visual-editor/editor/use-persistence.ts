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
import { dispatch as wpDispatch, select as wpSelect } from '@wordpress/data';

import {
    ApiError,
    fetchContent,
    saveContent,
    saveEntityRecord,
    type ApiClientConfig,
} from './api-client';
import {
    VE_EDITOR_AUTOSAVE,
    VE_EDITOR_CHANGE,
    VE_EDITOR_SAVE,
    dispatchEditorEvent,
} from './editor-events';
import { setPath } from '../responsive/attribute-paths';
import {
    getAllPristineClientIds,
    getPristineSnapshot,
} from '../states/state-bridge';

/**
 * Patch blocks in-memory so the saved markup has pristine idle base
 * values rather than the synced overlay that `StateInspectorSync`
 * applies for panel lockstep. Operates on a shallow copy — the
 * original block tree (and the data store) is untouched, so the
 * user's editing session keeps the active-state overlay.
 */
function patchBlocksWithPristine(
    blocks: readonly BlockInstance[],
    snapshotIds: ReadonlySet<string>,
): BlockInstance[] {
    return blocks.map( ( block ) => {
        const inner = block.innerBlocks.length > 0
            ? patchBlocksWithPristine( block.innerBlocks, snapshotIds )
            : block.innerBlocks;

        if ( ! snapshotIds.has( block.clientId ) ) {
            return inner === block.innerBlocks ? block : { ...block, innerBlocks: inner };
        }

        const snapshot = getPristineSnapshot( block.clientId );
        if ( ! snapshot ) {
            return inner === block.innerBlocks ? block : { ...block, innerBlocks: inner };
        }

        let attributes: Record<string, unknown> = block.attributes as Record<string, unknown>;
        for ( const [ path, value ] of Object.entries( snapshot ) ) {
            attributes = setPath( attributes, path, value );
        }

        return { ...block, attributes, innerBlocks: inner };
    } );
}

type LoadStatus = 'loading' | 'ready' | 'error';
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
type SaveTrigger = 'autosave' | 'save';

/**
 * Identity tuple for a core-data entity edited by the canvas. When set,
 * the persistence loop also flushes any staged
 * `editEntityRecord(kind, name, id, ...)` edits to the G3 entity
 * endpoint after the block save lands. cms-framework Post/Page edits
 * thread their identity here so sidebar metadata edits and post-title
 * block edits both round-trip through the same save cycle.
 */
export interface EntityIdentity {
    kind: string;
    name: string;
    id: number;
}

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
     * Schedule a debounced save for entity metadata edits already staged
     * in the core-data shim's edits bag (via `editEntityRecord`). Sidebar
     * handlers + the post-title block's `useEntityProp` setter both
     * dispatch into the same bag; this hook drains it on the next
     * scheduled flush.
     */
    queueMetadataForSave: () => void;
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
    /**
     * Optional core-data entity identity. Provide for resources that
     * register a `postType:*` entity through the shim (cms-framework
     * Post/Page) so metadata edits round-trip through the G3 endpoint
     * alongside the block save.
     */
    entity?: EntityIdentity | null;
}

// 1.5s debounce — long enough to coalesce drag-bursts from the color
// picker and fast typing (which helps avoid a known upstream Gutenberg
// render-backpressure issue during color drag) while still feeling
// responsive for normal edits.
const DEFAULT_DEBOUNCE_MS = 1500;

function toApiError(error: unknown, fallback: string): ApiError {
    return error instanceof ApiError ? error : new ApiError(fallback, 0, error);
}

function hasEntityEdits(kind: string, name: string, id: number): boolean {
    const store = wpSelect('core') as
        | {
              hasEditsForEntityRecord?: (
                  kind: string,
                  name: string,
                  id: number
              ) => boolean;
          }
        | undefined;

    return store?.hasEditsForEntityRecord?.(kind, name, id) ?? false;
}

function readEntityEdits(
    kind: string,
    name: string,
    id: number
): Record<string, unknown> | null {
    const store = wpSelect('core') as
        | {
              getEntityRecordEdits?: (
                  kind: string,
                  name: string,
                  id: number
              ) => Record<string, unknown> | null;
          }
        | undefined;

    return store?.getEntityRecordEdits?.(kind, name, id) ?? null;
}

function clearEntityEdits(kind: string, name: string, id: number): void {
    const dispatcher = wpDispatch('core') as
        | {
              clearEntityRecordEdits?: (
                  kind: string,
                  name: string,
                  id: number
              ) => void;
          }
        | undefined;

    dispatcher?.clearEntityRecordEdits?.(kind, name, id);
}

export function usePersistence(
    options: UsePersistenceOptions
): PersistenceState {
    const {
        debounceMs = DEFAULT_DEBOUNCE_MS,
        apiBase,
        resource,
        id,
        entity = null,
    } = options;

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
    // Mirrors the entity tuple in a ref so the runFlush closure can read
    // the latest identity without re-binding `runFlush` on every render
    // (which would replace `queueBlocksForSave` / `queueMetadataForSave`
    // identity and churn the consuming components downstream).
    const entityRef = useRef<EntityIdentity | null>(entity);
    entityRef.current = entity;

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

            const nextBlocks = pendingRef.current;
            const ent = entityRef.current;
            const hasMetadata =
                ent !== null && hasEntityEdits(ent.kind, ent.name, ent.id);

            if (nextBlocks === null && !hasMetadata) {
                return;
            }

            const version = targetVersionRef.current;

            pendingRef.current = null;
            inFlightRef.current = true;
            setSaveStatus('saving');
            setSaveError(null);

            let savedAt: string | null = null;

            try {
                if (nextBlocks !== null) {
                    // #515: replace overlaid base values with the
                    // pristine idle snapshot so the saved markup keeps
                    // idle as the canonical base. The overlay stays in
                    // the data store for the live editing session.
                    const pristineIds = getAllPristineClientIds();
                    const blocksToSave = pristineIds.length > 0
                        ? patchBlocksWithPristine( nextBlocks, new Set( pristineIds ) )
                        : nextBlocks;

                    const response = await saveContent(
                        { apiBase, resource, id },
                        blocksToSave
                    );

                    if (unmountedRef.current || version !== targetVersionRef.current) {
                        return;
                    }

                    savedAt = response.updated_at;

                    dispatchEditorEvent(
                        trigger === 'save' ? VE_EDITOR_SAVE : VE_EDITOR_AUTOSAVE,
                        {
                            resource,
                            id,
                            blocks: nextBlocks,
                            updatedAt: response.updated_at,
                        }
                    );
                }

                if (
                    ent !== null
                    && hasEntityEdits(ent.kind, ent.name, ent.id)
                ) {
                    const edits = readEntityEdits(ent.kind, ent.name, ent.id);

                    if (edits !== null && Object.keys(edits).length > 0) {
                        // PUT only the staged edits — sending the merged
                        // record would re-stamp the cached `content.blocks`
                        // (potentially clobbering the just-saved block tree
                        // when `nextBlocks` was non-null).
                        await saveEntityRecord(
                            { apiBase, resource, id },
                            edits
                        );

                        if (
                            unmountedRef.current
                            || version !== targetVersionRef.current
                        ) {
                            return;
                        }

                        clearEntityEdits(ent.kind, ent.name, ent.id);
                    }
                }

                if (savedAt !== null) {
                    setLastSavedAt(savedAt);
                }

                setSaveStatus('saved');
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
            const trailingEnt = entityRef.current;
            const hasTrailingMetadata =
                trailingEnt !== null
                && hasEntityEdits(
                    trailingEnt.kind,
                    trailingEnt.name,
                    trailingEnt.id
                );

            if (
                (pendingRef.current !== null || hasTrailingMetadata)
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

    const queueMetadataForSave = useCallback((): void => {
        if (
            loadStatusRef.current !== 'ready'
            || entityRef.current === null
        ) {
            return;
        }

        // Mark dirty immediately so the top-bar indicator reflects the
        // pending edit before the debounce window elapses.
        setSaveStatus('idle');

        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
        }

        timerRef.current = setTimeout(() => {
            timerRef.current = null;
            void runFlush('autosave');
        }, debounceMs);
    }, [debounceMs, runFlush]);

    const flush = useCallback((): void => {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        if (loadStatusRef.current !== 'ready') {
            return;
        }

        const ent = entityRef.current;
        const hasMetadata =
            ent !== null && hasEntityEdits(ent.kind, ent.name, ent.id);

        if (pendingRef.current === null && !hasMetadata) {
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
        queueMetadataForSave,
        flush,
    };
}

