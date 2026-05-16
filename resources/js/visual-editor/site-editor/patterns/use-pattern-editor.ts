/**
 * Single-pattern editor state hook.
 *
 * Mirrors `use-entity-editor.ts` (templates / parts) for the patterns
 * record shape. Patterns ship a `synced` flag (immutable post-creation),
 * a `categories` array, and a `status` enum that the generic entity
 * editor doesn't know about — keeping the patterns hook separate keeps
 * the shared one from accumulating sub-section conditionals.
 */

import { serialize, type BlockInstance } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';
import { hydrateBlocks } from '../hydrate-blocks';

import {
    fetchPattern,
    SiteEditorApiError,
    updatePattern,
    type PatternRecord,
    type PatternStatus,
    type PatternUpdatePayload,
    type ValidationErrors,
} from './api-client';

export type PatternSaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export type PatternLoadStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface UsePatternEditorOptions {
    apiConfig: SiteEditorApiConfig;
    /** Pattern id from the URL. `null` short-circuits the hook into idle. */
    entityId: string | null;
}

export interface PatternEditorFields {
    title: string;
    slug: string;
    status: PatternStatus;
    categories: readonly string[];
}

export interface UsePatternEditorResult {
    pattern: PatternRecord | null;
    loadStatus: PatternLoadStatus;
    loadErrorMessage: string | null;

    blocks: readonly unknown[];
    setBlocks: (blocks: readonly unknown[]) => void;

    fields: PatternEditorFields;
    setFields: (fields: Partial<PatternEditorFields>) => void;

    isDirty: boolean;
    saveStatus: PatternSaveStatus;
    saveErrorMessage: string | null;
    validationErrors: ValidationErrors | null;
    lastSavedAt: Date | null;
    save: (overrides?: PatternUpdatePayload) => Promise<PatternRecord | null>;
}

interface LoadedContent {
    raw: string;
    blocks: readonly unknown[];
}

function isPatternContent(value: unknown): value is LoadedContent {
    return (
        typeof value === 'object' &&
        value !== null &&
        'blocks' in value &&
        Array.isArray((value as { blocks: unknown }).blocks)
    );
}

function fieldsForRecord(record: PatternRecord | null): PatternEditorFields {
    if (record === null) {
        return {
            title: '',
            slug: '',
            status: 'publish',
            categories: [],
        };
    }

    return {
        // Prefer the user-authored `raw` title so the inspector field
        // edits the unescaped form instead of the HTML-rendered one.
        title: record.title?.raw ?? record.title?.rendered ?? '',
        slug: record.slug,
        status: record.status,
        categories: [...record.categories],
    };
}

export function usePatternEditor(
    options: UsePatternEditorOptions
): UsePatternEditorResult {
    const { apiConfig, entityId } = options;

    const [pattern, setPattern] = useState<PatternRecord | null>(null);
    const [loadStatus, setLoadStatus] = useState<PatternLoadStatus>('idle');
    const [loadErrorMessage, setLoadErrorMessage] = useState<string | null>(
        null
    );

    const [blocks, setBlocksState] = useState<readonly unknown[]>([]);
    const [fields, setFieldsState] = useState<PatternEditorFields>(
        fieldsForRecord(null)
    );

    const [isDirty, setIsDirty] = useState(false);
    const [saveStatus, setSaveStatus] = useState<PatternSaveStatus>('idle');
    const [saveErrorMessage, setSaveErrorMessage] = useState<string | null>(
        null
    );
    const [validationErrors, setValidationErrors] =
        useState<ValidationErrors | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

    const requestCounterRef = useRef(0);
    const committedBlocksRef = useRef<readonly unknown[]>([]);
    const committedFieldsRef = useRef<PatternEditorFields>(
        fieldsForRecord(null)
    );
    const editVersionRef = useRef(0);

    const resetEditorState = useCallback((): void => {
        committedBlocksRef.current = [];
        committedFieldsRef.current = fieldsForRecord(null);
        setPattern(null);
        setBlocksState([]);
        setFieldsState(fieldsForRecord(null));
        setIsDirty(false);
        setSaveStatus('idle');
        setSaveErrorMessage(null);
        setValidationErrors(null);
        setLastSavedAt(null);
    }, []);

    const hydrateFromRecord = useCallback((record: PatternRecord): void => {
        const nextBlocks = isPatternContent(record.content)
            ? hydrateBlocks(record.content)
            : [];
        const nextFields = fieldsForRecord(record);

        committedBlocksRef.current = nextBlocks;
        committedFieldsRef.current = nextFields;

        setPattern(record);
        setBlocksState(nextBlocks);
        setFieldsState(nextFields);
        setIsDirty(false);
        setValidationErrors(null);
    }, []);

    useEffect(() => {
        if (entityId === null) {
            requestCounterRef.current += 1;
            resetEditorState();
            setLoadStatus('idle');
            setLoadErrorMessage(null);

            return;
        }

        const requestId = ++requestCounterRef.current;

        resetEditorState();
        setLoadStatus('loading');
        setLoadErrorMessage(null);

        void (async () => {
            try {
                const record = await fetchPattern(apiConfig, entityId);

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
                        : __('Failed to load pattern.', TEXT_DOMAIN);

                setPattern(null);
                setBlocksState([]);
                setLoadStatus('error');
                setLoadErrorMessage(message);
            }
        })();
    }, [apiConfig, entityId, hydrateFromRecord, resetEditorState]);

    const setBlocks = useCallback((next: readonly unknown[]): void => {
        setBlocksState((prev) => {
            if (prev === next) {
                return prev;
            }

            editVersionRef.current += 1;

            if (next === committedBlocksRef.current) {
                // Blocks reset back to committed state — clear dirty
                // unless field overrides remain outstanding.
                const hasFieldOverrides = !areFieldsEqual(
                    committedFieldsRef.current,
                    // Capture latest fields from state via setter
                    // form. We can't access `fields` from inside this
                    // setter without re-creating the callback, so we
                    // approximate by reading from a ref-mirror that
                    // `setFields` keeps current.
                    fieldsRefMirrorRef.current
                );

                if (!hasFieldOverrides) {
                    setIsDirty(false);
                }
            } else {
                setIsDirty(true);
            }

            return next;
        });
    }, []);

    // Mirror of the latest `fields` so `setBlocks` can read it without
    // taking the value as a dep (which would re-create the callback on
    // every keystroke and thrash BlockEditorProvider memoization).
    const fieldsRefMirrorRef = useRef<PatternEditorFields>(
        committedFieldsRef.current
    );
    fieldsRefMirrorRef.current = fields;

    // Same trick for `blocks`: `setFields`'s dirty-tracking branch
    // needs to know whether the canvas matches the committed tree, but
    // depending on `blocks` directly would tie the callback identity
    // to every keystroke in the canvas. Mirror it through a ref so the
    // callback stays stable.
    const blocksRefMirrorRef = useRef<readonly unknown[]>(
        committedBlocksRef.current
    );
    blocksRefMirrorRef.current = blocks;

    const setFields = useCallback(
        (next: Partial<PatternEditorFields>): void => {
            setFieldsState((prev) => {
                const merged: PatternEditorFields = {
                    title: next.title !== undefined ? next.title : prev.title,
                    slug: next.slug !== undefined ? next.slug : prev.slug,
                    status:
                        next.status !== undefined ? next.status : prev.status,
                    categories:
                        next.categories !== undefined
                            ? [...next.categories]
                            : prev.categories,
                };

                if (areFieldsEqual(prev, merged)) {
                    return prev;
                }

                editVersionRef.current += 1;

                if (areFieldsEqual(committedFieldsRef.current, merged)) {
                    if (blocksRefMirrorRef.current === committedBlocksRef.current) {
                        setIsDirty(false);
                    }
                } else {
                    setIsDirty(true);
                }

                return merged;
            });
        },
        []
    );

    const save = useCallback(
        async (
            overrides: PatternUpdatePayload = {}
        ): Promise<PatternRecord | null> => {
            if (entityId === null) {
                return null;
            }

            const blockInstances = blocks as BlockInstance[];
            const raw = serialize(blockInstances);

            // Sync status is immutable post-creation. Strip any callers
            // that try to flip it on update — conversion uses a
            // separate "create new" path.
            const safeOverrides: PatternUpdatePayload = { ...overrides };

            if ('synced' in safeOverrides) {
                delete safeOverrides.synced;
            }

            const payload: PatternUpdatePayload = {
                title: fields.title,
                slug: fields.slug,
                status: fields.status,
                categories: [...fields.categories],
                content: { raw, blocks },
                ...safeOverrides,
            };

            const requestId = ++requestCounterRef.current;
            const isStale = (): boolean =>
                requestCounterRef.current !== requestId;
            const draftVersion = editVersionRef.current;

            setSaveStatus('saving');
            setSaveErrorMessage(null);
            setValidationErrors(null);

            try {
                const updated = await updatePattern(
                    apiConfig,
                    entityId,
                    payload
                );

                if (isStale()) {
                    return updated;
                }

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
                    setSaveErrorMessage(
                        __('Failed to save.', TEXT_DOMAIN)
                    );
                }

                return null;
            }
        },
        [apiConfig, blocks, entityId, fields, hydrateFromRecord]
    );

    const draftPattern = useMemo<PatternRecord | null>(() => {
        if (pattern === null) {
            return null;
        }

        if (
            areFieldsEqual(committedFieldsRef.current, fields) &&
            blocks === committedBlocksRef.current
        ) {
            return pattern;
        }

        return {
            ...pattern,
            slug: fields.slug,
            status: fields.status,
            title: { ...pattern.title, rendered: fields.title },
            categories: [...fields.categories],
        };
    }, [blocks, fields, pattern]);

    return {
        pattern: draftPattern,
        loadStatus,
        loadErrorMessage,
        blocks,
        setBlocks,
        fields,
        setFields,
        isDirty,
        saveStatus,
        saveErrorMessage,
        validationErrors,
        lastSavedAt,
        save,
    };
}

function areFieldsEqual(
    a: PatternEditorFields,
    b: PatternEditorFields
): boolean {
    if (
        a.title !== b.title ||
        a.slug !== b.slug ||
        a.status !== b.status ||
        a.categories.length !== b.categories.length
    ) {
        return false;
    }

    for (let index = 0; index < a.categories.length; index += 1) {
        if (a.categories[index] !== b.categories[index]) {
            return false;
        }
    }

    return true;
}
