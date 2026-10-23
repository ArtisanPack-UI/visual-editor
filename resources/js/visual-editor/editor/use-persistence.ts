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
import { __ } from '@wordpress/i18n';

import {
    ApiError,
    fetchContent,
    saveContent,
    saveEntityRecord,
    type ApiClientConfig,
    type EntityEndpoint,
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
import { TEXT_DOMAIN } from '../vendor/i18n';

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
    /**
     * Endpoint the staged edits flush to. Defaults to the G3 entity
     * adapter; custom content types use the generic `entry` endpoint
     * (#992).
     */
    endpoint?: EntityEndpoint;
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

/**
 * Folds saved edits into the entity's base record, then drops them from
 * the edits bag. Without the fold, clearing the edits would leave
 * `getEditedEntityRecord` reading the pre-save base, so a just-saved
 * title or excerpt would snap back on the canvas (#992). `{raw,
 * rendered}` fields keep their shape. Only the saved values are
 * cleared, so edits staged while the request was in flight survive for
 * the next save.
 */
function commitEntityEdits(
    kind: string,
    name: string,
    id: number,
    saved: Record<string, unknown>
): void {
    const store = wpSelect('core') as
        | {
              getEntityRecord?: (
                  kind: string,
                  name: string,
                  id: number
              ) => Record<string, unknown> | null;
          }
        | undefined;
    const dispatcher = wpDispatch('core') as
        | {
              receiveEntityRecords?: (
                  kind: string,
                  name: string,
                  records: readonly Record<string, unknown>[],
                  query?: Record<string, unknown> | null,
                  totalItems?: number,
                  totalPages?: number,
                  invalidateQueries?: boolean
              ) => void;
              clearEntityRecordEdits?: (
                  kind: string,
                  name: string,
                  id: number,
                  acknowledged?: Record<string, unknown>
              ) => void;
          }
        | undefined;

    const base = store?.getEntityRecord?.(kind, name, id) ?? null;

    if (base !== null && dispatcher?.receiveEntityRecords !== undefined) {
        const next: Record<string, unknown> = { ...base };

        for (const [field, value] of Object.entries(saved)) {
            const current = base[field];

            next[field] = typeof value === 'string'
                && current !== null
                && typeof current === 'object'
                && 'raw' in current
                ? { ...current, raw: value, rendered: value }
                : value;
        }

        // `invalidateQueries: false` — this updates a known record, so
        // cached list queries for the entity must survive (as the shim's
        // own save path does).
        dispatcher.receiveEntityRecords(kind, name, [next], undefined, undefined, undefined, false);
    }

    dispatcher?.clearEntityRecordEdits?.(kind, name, id, saved);
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
                // The loaded tree *is* the persisted state, so the editor
                // starts clean. Staying `idle` here labelled every page
                // "Unsaved changes" until its first save, which pages
                // that write nothing on load never reach (#1011).
                setSaveStatus('saved');
                setLoadStatus('ready');
            })
            .catch((error: unknown) => {
                if (version !== targetVersionRef.current) {
                    return;
                }

                setLoadError(toApiError(error, __('Failed to load content.', TEXT_DOMAIN)));
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
            let failed = false;

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
                            edits,
                            ent.endpoint
                        );

                        if (
                            unmountedRef.current
                            || version !== targetVersionRef.current
                        ) {
                            return;
                        }

                        commitEntityEdits(ent.kind, ent.name, ent.id, edits);
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

                failed = true;
                setSaveError(toApiError(error, __('Failed to save content.', TEXT_DOMAIN)));
                setSaveStatus('error');
            } finally {
                inFlightRef.current = false;
            }

            // A change landed while the save was in flight — drain the
            // trailing edit now so we don't lose it. Trailing drains always
            // count as autosaves: they're reacting to buffered edits, not a
            // fresh explicit Save press. Skip if the target changed during
            // the save. After a failed save the metadata it tried to send
            // is still staged, so only a fresh block edit re-arms the
            // drain — otherwise a rejected PUT (403/422) would retry in a
            // tight loop.
            const trailingEnt = entityRef.current;
            const hasTrailingMetadata =
                !failed
                && trailingEnt !== null
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
            // Mark dirty immediately, as `queueMetadataForSave` does. A
            // repeat `idle` while typing is a same-value bail-out, so
            // this doesn't re-render the editor per keystroke.
            setSaveStatus('idle');

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

