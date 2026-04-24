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

import { parse, serialize, type BlockInstance } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

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

/**
 * Hydrate a raw-serialized block tree into BlockInstances Gutenberg can
 * render. Prefers `content.raw` (the canonical Gutenberg HTML form —
 * guarantees fresh `clientId`s) and only falls back to the parsed
 * `blocks` array when raw is empty. This matches what the legacy
 * post-editor's `use-persistence` does for its response shape.
 */
function hydrateBlocks(content: LoadedContent): BlockInstance[] {
    const raw = typeof content.raw === 'string' ? content.raw.trim() : '';

    if (raw !== '') {
        return parse(raw);
    }

    // No raw serialization — trust the parsed array. `parse()` cannot run
    // against an already-parsed tree, so cast through `BlockInstance` and
    // let Gutenberg regenerate missing metadata when it renders.
    return content.blocks as BlockInstance[];
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
            committedBlocksRef.current = [];
            setEntity(null);
            setBlocksState([]);
            setPendingPatch({});
            setIsDirty(false);
            setLoadStatus('idle');
            setLoadErrorMessage(null);
            setSaveStatus('idle');
            setSaveErrorMessage(null);
            setValidationErrors(null);
            setLastSavedAt(null);
            return;
        }

        const requestId = ++requestCounterRef.current;

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
    }, [apiConfig, kind, entityId, hydrateFromRecord]);

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
        setPendingPatch((prev) => ({ ...prev, ...overrides }));
        setIsDirty(true);
    }, []);

    const reset = useCallback((): void => {
        setBlocksState(committedBlocksRef.current);
        setPendingPatch({});
        setIsDirty(false);
        setValidationErrors(null);
    }, []);

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

            setSaveStatus('saving');
            setSaveErrorMessage(null);
            setValidationErrors(null);

            try {
                const updated = await updateEntity(apiConfig, kind, entityId, payload);

                hydrateFromRecord(updated);
                setSaveStatus('saved');
                setLastSavedAt(new Date());

                return updated;
            } catch (error: unknown) {
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
        entity,
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
