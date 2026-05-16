/**
 * Single-entity editor state hook.
 *
 * Loads the currently-open template or template part, exposes its content
 * envelope for the `BlockEditorProvider`, and carries the dirty flag +
 * save status the top bar needs to render "Save template" / "Saving…" /
 * "Saved" (design brief §4.3: Save affects just this one entity, and the
 * top bar always names the scope).
 *
 * Decoupled from the canvas so the shell can lift status into the top
 * bar and expose a single `save()` callback — which the top-bar button
 * calls — without the canvas having to render the button itself.
 */

import { serialize, type BlockInstance } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { hydrateBlocks } from './hydrate-blocks';

import {
    fetchEntity,
    SiteEditorApiError,
    updateEntity,
    type EntityKind,
    type EntityRecord,
    type SiteEditorApiConfig,
    type UpdatePayload,
    type ValidationErrors,
} from './api-client';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export type EntityLoadStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface UseEntityEditorOptions<K extends EntityKind> {
    apiConfig: SiteEditorApiConfig;
    kind: K;
    /**
     * Entity id from the URL. `null` switches the hook into "no entity"
     * state — useful when the URL maps to the section list rather than a
     * specific entity.
     */
    entityId: string | null;
}

export interface UseEntityEditorResult<K extends EntityKind> {
    entity: EntityRecord<K> | null;
    loadStatus: EntityLoadStatus;
    loadErrorMessage: string | null;

    blocks: readonly unknown[];
    /** Notifies the hook of a block-tree mutation from the canvas. */
    setBlocks: (blocks: readonly unknown[]) => void;

    isDirty: boolean;

    saveStatus: SaveStatus;
    saveErrorMessage: string | null;
    validationErrors: ValidationErrors | null;
    lastSavedAt: Date | null;
    /**
     * Persists the current blocks + any pending field overrides to the
     * backend. Returns the updated entity on success, `null` on error.
     */
    save: (overrides?: UpdatePayload) => Promise<EntityRecord<K> | null>;

    /**
     * Merges partial field updates (slug, title, area, …) into the
     * working copy and marks the entity dirty. Block-tree changes should
     * go through `setBlocks` instead.
     */
    patch: (overrides: UpdatePayload) => void;

    /** Hard reset of the in-memory working copy to the last saved state. */
    reset: () => void;
}

interface LoadedContent {
    raw: string;
    blocks: readonly unknown[];
}

function isEntityContent(value: unknown): value is LoadedContent {
    return (
        typeof value === 'object' &&
        value !== null &&
        'blocks' in value &&
        Array.isArray((value as { blocks: unknown }).blocks)
    );
}


export function useEntityEditor<K extends EntityKind>(
    options: UseEntityEditorOptions<K>
): UseEntityEditorResult<K> {
    const { apiConfig, kind, entityId } = options;

    const [entity, setEntity] = useState<EntityRecord<K> | null>(null);
    const [loadStatus, setLoadStatus] = useState<EntityLoadStatus>('idle');
    const [loadErrorMessage, setLoadErrorMessage] = useState<string | null>(null);

    const [blocks, setBlocksState] = useState<readonly unknown[]>([]);
    const [pendingPatch, setPendingPatch] = useState<UpdatePayload>({});
    const [isDirty, setIsDirty] = useState<boolean>(false);

    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [saveErrorMessage, setSaveErrorMessage] = useState<string | null>(null);
    const [validationErrors, setValidationErrors] = useState<ValidationErrors | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

    const requestCounterRef = useRef(0);

    // Snapshot of the last committed blocks so we can distinguish "user
    // typed then undid" from "user typed" without comparing trees.
    const committedBlocksRef = useRef<readonly unknown[]>([]);

    // Bumped on every local edit (setBlocks, patch, reset). A save
    // snapshots this at start; if the value has moved by the time the
    // response arrives, the user kept typing during the save and we
    // must NOT overwrite their newer edits with the server snapshot.
    const editVersionRef = useRef(0);

    const resetEditorState = useCallback((): void => {
        committedBlocksRef.current = [];
        setEntity(null);
        setBlocksState([]);
        setPendingPatch({});
        setIsDirty(false);
        setSaveStatus('idle');
        setSaveErrorMessage(null);
        setValidationErrors(null);
        setLastSavedAt(null);
    }, []);

    const hydrateFromRecord = useCallback((record: EntityRecord<K>): void => {
        const nextBlocks = isEntityContent(record.content)
            ? hydrateBlocks(record.content)
            : [];

        committedBlocksRef.current = nextBlocks;
        setEntity(record);
        setBlocksState(nextBlocks);
        setPendingPatch({});
        setIsDirty(false);
        setValidationErrors(null);
    }, []);

    useEffect(() => {
        if (entityId === null) {
            // Bump the counter alongside the state reset so a load or
            // save that resolves after the user closed the editor can't
            // slip past the stale-request guard and re-populate the
            // cleared state.
            requestCounterRef.current += 1;
            resetEditorState();
            setLoadStatus('idle');
            setLoadErrorMessage(null);
            return;
        }

        const requestId = ++requestCounterRef.current;

        // Clear every field that belonged to the previous entity before
        // kicking off the fetch — otherwise entity A's `saveStatus`,
        // `lastSavedAt`, validation errors, and dirty flag stay visible
        // in the top bar / inspector until entity B finishes loading.
        resetEditorState();
        setLoadStatus('loading');
        setLoadErrorMessage(null);

        void (async () => {
            try {
                const record = await fetchEntity(apiConfig, kind, entityId);

                if (requestCounterRef.current !== requestId) {
                    return;
                }

                hydrateFromRecord(record);
                setLoadStatus('ready');
            } catch (error: unknown) {
                if (requestCounterRef.current !== requestId) {
                    return;
                }

                const message =
                    error instanceof SiteEditorApiError
                        ? error.message
                        : __('Failed to load entity.', TEXT_DOMAIN);

                setEntity(null);
                setBlocksState([]);
                setLoadStatus('error');
                setLoadErrorMessage(message);
            }
        })();
    }, [apiConfig, kind, entityId, hydrateFromRecord, resetEditorState]);

    // Read the latest `pendingPatch` from inside `setBlocks` without
    // re-creating the callback on every keystroke — reading from state
    // directly would force a new `setBlocks` identity each render and
    // thrash BlockEditorProvider's memoized handlers.
    const pendingPatchRef = useRef<UpdatePayload>({});
    pendingPatchRef.current = pendingPatch;

    const setBlocks = useCallback((next: readonly unknown[]): void => {
        setBlocksState((prev) => {
            // BlockEditorProvider calls `onInput` / `onChange` with the
            // same reference when nothing changed; short-circuit to avoid
            // flipping the dirty flag on every mouse move.
            if (prev === next) {
                return prev;
            }

            editVersionRef.current += 1;

            if (next === committedBlocksRef.current) {
                // User undid every block edit back to the saved tree —
                // clear the dirty flag unless a pending field override
                // (slug / title / area / …) is still outstanding.
                if (Object.keys(pendingPatchRef.current).length === 0) {
                    setIsDirty(false);
                }
            } else {
                setIsDirty(true);
            }

            return next;
        });
    }, []);

    const patch = useCallback((overrides: UpdatePayload): void => {
        editVersionRef.current += 1;
        setPendingPatch((prev) => ({ ...prev, ...overrides }));
        setIsDirty(true);
    }, []);

    const reset = useCallback((): void => {
        editVersionRef.current += 1;
        setBlocksState(committedBlocksRef.current);
        setPendingPatch({});
        setIsDirty(false);
        setValidationErrors(null);
    }, []);

    // Merge pending field overrides back into the loaded record so the
    // inspector inputs bound to `entity.title.rendered`, `entity.slug`,
    // etc. reflect what the user typed. Without this, each keystroke
    // updates `pendingPatch` but the bound input re-renders with the
    // committed record — keystrokes vanish from the UI even though the
    // dirty flag is set correctly.
    const draftEntity = useMemo<EntityRecord<K> | null>(() => {
        if (entity === null) {
            return null;
        }

        if (Object.keys(pendingPatch).length === 0) {
            return entity;
        }

        const merged = { ...entity } as EntityRecord<K> & Record<string, unknown>;

        if (pendingPatch.slug !== undefined) {
            merged.slug = pendingPatch.slug;
        }

        if (pendingPatch.title !== undefined) {
            merged.title = { ...entity.title, rendered: pendingPatch.title };
        }

        if (pendingPatch.description !== undefined && 'description' in entity) {
            merged.description = pendingPatch.description ?? '';
        }

        if (pendingPatch.area !== undefined && 'area' in entity) {
            merged.area = pendingPatch.area;
        }

        if (pendingPatch.status !== undefined && 'status' in entity) {
            merged.status = pendingPatch.status;
        }

        if (pendingPatch.source !== undefined && 'source' in entity) {
            merged.source = pendingPatch.source;
        }

        if (pendingPatch.theme !== undefined && 'theme' in entity) {
            merged.theme = pendingPatch.theme;
        }

        return merged as EntityRecord<K>;
    }, [entity, pendingPatch]);

    const save = useCallback(
        async (overrides: UpdatePayload = {}): Promise<EntityRecord<K> | null> => {
            if (entityId === null) {
                return null;
            }

            // Serialize the in-memory `BlockInstance` tree back to the raw
            // Gutenberg string so the backend and the canonical re-hydration
            // stay aligned. `blocks` stays alongside raw for consumers that
            // want the parsed form without re-running `parse()`.
            const blockInstances = blocks as BlockInstance[];
            const raw = serialize(blockInstances);

            const payload: UpdatePayload = {
                ...pendingPatch,
                ...overrides,
                content: {
                    raw,
                    blocks,
                },
            };

            // Allocate a unique request id for this save so a later save
            // (or a navigate/close) always invalidates us. Snapshotting
            // without incrementing would let two concurrent saves share
            // an id — the slower one's stale response could then
            // hydrate the editor with outdated fields on top of the
            // faster one's authoritative result.
            const requestId = ++requestCounterRef.current;
            const isStale = (): boolean => requestCounterRef.current !== requestId;

            // Snapshot the edit-version so we can detect typing that
            // happened after the payload was captured. On a slow network
            // the user can keep editing while the PUT is in-flight;
            // hydrating the response on top of those fresh edits would
            // silently discard them.
            const draftVersion = editVersionRef.current;

            setSaveStatus('saving');
            setSaveErrorMessage(null);
            setValidationErrors(null);

            try {
                const updated = await updateEntity(apiConfig, kind, entityId, payload);

                if (isStale()) {
                    return updated;
                }

                // The save itself succeeded either way, so confirm it to
                // the user. Only re-sync the canvas + pending patch from
                // the server when no new edits happened during the
                // request — otherwise preserve the user's in-flight
                // edits and leave `isDirty` true so they know there's
                // still newer state to push.
                setSaveStatus('saved');
                setLastSavedAt(new Date());

                if (editVersionRef.current === draftVersion) {
                    hydrateFromRecord(updated);
                }

                return updated;
            } catch (error: unknown) {
                if (isStale()) {
                    return null;
                }

                if (error instanceof SiteEditorApiError) {
                    setSaveStatus('error');
                    setSaveErrorMessage(error.message);
                    setValidationErrors(error.validationErrors);
                } else {
                    setSaveStatus('error');
                    setSaveErrorMessage(__('Failed to save.', TEXT_DOMAIN));
                }

                return null;
            }
        },
        [apiConfig, blocks, entityId, hydrateFromRecord, kind, pendingPatch]
    );

    return {
        entity: draftEntity,
        loadStatus,
        loadErrorMessage,
        blocks,
        setBlocks,
        isDirty,
        saveStatus,
        saveErrorMessage,
        validationErrors,
        lastSavedAt,
        save,
        patch,
        reset,
    };
}
