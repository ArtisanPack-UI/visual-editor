/**
 * Global-styles editor state hook.
 *
 * Owns the D3 styles section's in-memory draft — the pending edits the
 * user makes in any of the six panels (typography, colors, layout,
 * blocks, elements, variations) before they hit Save. Parallel to the
 * D2 `useEntityEditor`, but shaped around global styles' singleton
 * record instead of the per-entity template flow:
 *
 *   - One record per theme, so no `entityId` navigation. The id comes
 *     from the `lookup` dispatch at bootstrap.
 *   - Edits are sparse leaf patches (one property at a time) rather
 *     than block-tree mutations. `patch(path, value)` writes a deep
 *     key; `reset(path)` clears it.
 *   - Save PUTs the merged (`base + edits`) record; 422 responses
 *     surface under `validationErrors` so panels can render inline.
 *
 * The hook is kept headless so each panel gets the same dirty /
 * customized / save primitives and the section wrapper can hand the
 * shell top-bar a single save callback.
 */

import { dispatch as wpDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    SiteEditorApiError,
    type SiteEditorApiConfig,
    type ValidationErrors,
} from '../api-client';
import type { SaveStatus } from '../use-entity-editor';

import {
    fetchGlobalStyles,
    fetchGlobalStylesBase,
    lookupGlobalStyles,
    updateGlobalStyles,
    type GlobalStylesBase,
    type GlobalStylesRecord,
} from './global-styles-api';
import {
    isCustomized,
    readPath,
    unsetPath,
    writePath,
    type ThemeJsonPath,
} from './theme-json-paths';

export interface UseGlobalStylesEditorOptions {
    apiConfig: SiteEditorApiConfig;
    /**
     * When `false` the hook short-circuits into idle — nothing fetched,
     * no state updates, safe to mount on sections that aren't styles.
     */
    enabled: boolean;
}

export interface GlobalStylesDraft {
    version: number;
    settings: Record<string, unknown>;
    styles: Record<string, unknown>;
}

export type LoadStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface UseGlobalStylesEditorResult {
    id: number | null;
    base: GlobalStylesBase | null;
    record: GlobalStylesRecord | null;
    /** Merged (record + pending edits) shape — what panels should render. */
    draft: GlobalStylesDraft | null;

    loadStatus: LoadStatus;
    loadErrorMessage: string | null;

    isDirty: boolean;

    saveStatus: SaveStatus;
    saveErrorMessage: string | null;
    validationErrors: ValidationErrors | null;
    lastSavedAt: Date | null;

    /** Value at `path`, preferring the user draft then falling back to base. */
    readValue: (path: ThemeJsonPath) => unknown;
    /** Base-payload value at `path` (no user overlay applied). */
    readBaseValue: (path: ThemeJsonPath) => unknown;
    /**
     * `true` when the user has overridden the value at `path`. Panels
     * use this to decorate customized fields + expose a "reset" affordance.
     */
    isPathCustomized: (path: ThemeJsonPath) => boolean;

    /** Writes a leaf edit; marks the record dirty. */
    setValue: (path: ThemeJsonPath, value: unknown) => void;
    /**
     * Replaces the settings or styles subtree wholesale. Used by the
     * variation picker (which bulk-applies a preset's values) and the
     * panel-scoped "reset section" affordance. The variation picker
     * also passes a nested reset (`['color', 'palette']`) to blow away a
     * specific subtree without touching sibling subtrees.
     */
    replaceSubtree: (scope: 'settings' | 'styles', path: ThemeJsonPath, value: unknown) => void;
    /** Clears the user override at `path`, reverting to base. */
    resetPath: (path: ThemeJsonPath) => void;
    /** Clears every pending edit — full revert to the last saved record. */
    resetAll: () => void;

    save: () => Promise<GlobalStylesRecord | null>;
}

const EMPTY_EDITS: Readonly<GlobalStylesDraft> = Object.freeze({
    version: 0,
    settings: {},
    styles: {},
});

function schemaVersionFrom(
    record: GlobalStylesRecord | null,
    base: GlobalStylesBase | null
): number {
    if (record !== null && typeof record.version === 'number') {
        return record.version;
    }

    if (base !== null && typeof base.version === 'number') {
        return base.version;
    }

    // The C3 backend pins schema v3 when no override is configured. The
    // fallback matches `config/visual-editor.php` so a PUT from a
    // brand-new app that hasn't yet received a lookup/base response
    // still submits a value the backend will accept.
    return 3;
}

function hasAnyEdits(edits: GlobalStylesDraft | null): boolean {
    if (edits === null) {
        return false;
    }

    return (
        Object.keys(edits.settings).length > 0 ||
        Object.keys(edits.styles).length > 0
    );
}

/**
 * Merges the base record, server record, and pending edits into a
 * single object panels can read from.
 */
function buildDraft(
    record: GlobalStylesRecord | null,
    edits: GlobalStylesDraft,
    base: GlobalStylesBase | null
): GlobalStylesDraft | null {
    if (record === null && base === null) {
        return null;
    }

    const recordSettings =
        record !== null && typeof record.settings === 'object' && record.settings !== null
            ? record.settings
            : {};
    const recordStyles =
        record !== null && typeof record.styles === 'object' && record.styles !== null
            ? record.styles
            : {};
    const baseSettings = base?.settings ?? {};
    const baseStyles = base?.styles ?? {};

    const settings = mergeDeep(
        mergeDeep(baseSettings, recordSettings),
        edits.settings
    );
    const styles = mergeDeep(
        mergeDeep(baseStyles, recordStyles),
        edits.styles
    );

    return {
        version: schemaVersionFrom(record, base),
        settings,
        styles,
    };
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return (
        value !== null &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        Object.prototype.toString.call(value) === '[object Object]'
    );
}

function mergeDeep(
    base: Record<string, unknown>,
    overlay: Record<string, unknown>
): Record<string, unknown> {
    if (Object.keys(overlay).length === 0) {
        return { ...base };
    }

    const result: Record<string, unknown> = { ...base };

    for (const [key, overlayValue] of Object.entries(overlay)) {
        const baseValue = base[key];

        if (isPlainObject(baseValue) && isPlainObject(overlayValue)) {
            result[key] = mergeDeep(baseValue, overlayValue);
            continue;
        }

        result[key] = overlayValue;
    }

    return result;
}

export function useGlobalStylesEditor(
    options: UseGlobalStylesEditorOptions
): UseGlobalStylesEditorResult {
    const { apiConfig, enabled } = options;

    const [id, setId] = useState<number | null>(null);
    const [base, setBase] = useState<GlobalStylesBase | null>(null);
    const [record, setRecord] = useState<GlobalStylesRecord | null>(null);
    const [edits, setEdits] = useState<GlobalStylesDraft>({
        ...EMPTY_EDITS,
    });

    const [loadStatus, setLoadStatus] = useState<LoadStatus>('idle');
    const [loadErrorMessage, setLoadErrorMessage] = useState<string | null>(
        null
    );

    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [saveErrorMessage, setSaveErrorMessage] = useState<string | null>(
        null
    );
    const [validationErrors, setValidationErrors] =
        useState<ValidationErrors | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

    // Bumped on every local edit or save so in-flight fetches that
    // started before a subsequent edit/unmount can't hydrate over the
    // user's fresher state.
    const requestCounterRef = useRef(0);

    useEffect(() => {
        if (!enabled) {
            setLoadStatus('idle');
            return;
        }

        const requestId = ++requestCounterRef.current;

        setLoadStatus('loading');
        setLoadErrorMessage(null);

        void (async () => {
            try {
                const [lookup, baseRecord] = await Promise.all([
                    lookupGlobalStyles(apiConfig),
                    fetchGlobalStylesBase(apiConfig),
                ]);

                if (requestCounterRef.current !== requestId) {
                    return;
                }

                // Dispatch into the core-data store so Gutenberg's
                // globalStyles selectors (__experimentalGetCurrentGlobalStylesId
                // etc.) resolve for any block-editor surface that asks —
                // the hook also tracks its own copy for the panels'
                // deep-leaf edits.
                const coreDispatch = wpDispatch('core') as unknown as {
                    receiveCurrentGlobalStylesId?: (id: number) => unknown;
                    receiveGlobalStylesBase?: (
                        payload: Record<string, unknown>
                    ) => unknown;
                };
                coreDispatch.receiveCurrentGlobalStylesId?.(lookup.id);
                coreDispatch.receiveGlobalStylesBase?.(
                    baseRecord as unknown as Record<string, unknown>
                );

                setId(lookup.id);
                setBase(baseRecord);

                const fetched = await fetchGlobalStyles(
                    apiConfig,
                    lookup.id
                );

                if (requestCounterRef.current !== requestId) {
                    return;
                }

                setRecord(fetched);
                setEdits({ ...EMPTY_EDITS });
                setLoadStatus('ready');
            } catch (error: unknown) {
                if (requestCounterRef.current !== requestId) {
                    return;
                }

                const message =
                    error instanceof SiteEditorApiError
                        ? error.message
                        : __(
                              'Failed to load global styles.',
                              TEXT_DOMAIN
                          );

                setLoadStatus('error');
                setLoadErrorMessage(message);
            }
        })();
    }, [apiConfig, enabled]);

    const draft = useMemo(
        () => buildDraft(record, edits, base),
        [base, edits, record]
    );

    const readValue = useCallback(
        (path: ThemeJsonPath): unknown => {
            if (draft === null) {
                return undefined;
            }

            return readPath(draft, path);
        },
        [draft]
    );

    const readBaseValue = useCallback(
        (path: ThemeJsonPath): unknown => {
            if (base === null) {
                return undefined;
            }

            return readPath(base, path);
        },
        [base]
    );

    const isPathCustomized = useCallback(
        (path: ThemeJsonPath): boolean => {
            if (base === null) {
                return false;
            }

            const draftValue = readPath(draft, path);
            const baseValue = readPath(base, path);

            return isCustomized(draftValue, baseValue);
        },
        [base, draft]
    );

    const mutate = useCallback(
        (
            mutator: (edits: GlobalStylesDraft) => GlobalStylesDraft
        ): void => {
            requestCounterRef.current += 1;
            setEdits((prev) => mutator(prev));
            setSaveStatus('idle');
            setSaveErrorMessage(null);
            setValidationErrors(null);
        },
        []
    );

    const setValue = useCallback(
        (path: ThemeJsonPath, value: unknown): void => {
            if (path.length === 0) {
                return;
            }

            const [scope, ...rest] = path;

            if (scope !== 'settings' && scope !== 'styles') {
                return;
            }

            mutate((prev) => ({
                ...prev,
                [scope]: writePath(prev[scope], rest, value) as Record<
                    string,
                    unknown
                >,
            }));
        },
        [mutate]
    );

    const replaceSubtree = useCallback(
        (
            scope: 'settings' | 'styles',
            path: ThemeJsonPath,
            value: unknown
        ): void => {
            mutate((prev) => ({
                ...prev,
                [scope]:
                    path.length === 0
                        ? ((value as Record<string, unknown>) ?? {})
                        : (writePath(prev[scope], path, value) as Record<
                              string,
                              unknown
                          >),
            }));
        },
        [mutate]
    );

    const resetPath = useCallback(
        (path: ThemeJsonPath): void => {
            if (path.length === 0) {
                return;
            }

            const [scope, ...rest] = path;

            if (scope !== 'settings' && scope !== 'styles') {
                return;
            }

            mutate((prev) => ({
                ...prev,
                [scope]: unsetPath(prev[scope], rest) as Record<
                    string,
                    unknown
                >,
            }));
        },
        [mutate]
    );

    const resetAll = useCallback((): void => {
        mutate(() => ({ ...EMPTY_EDITS }));
    }, [mutate]);

    const isDirty = useMemo(() => hasAnyEdits(edits), [edits]);

    const save = useCallback(async (): Promise<GlobalStylesRecord | null> => {
        if (id === null) {
            return null;
        }

        if (draft === null) {
            return null;
        }

        const requestId = ++requestCounterRef.current;
        const isStale = (): boolean =>
            requestCounterRef.current !== requestId;

        setSaveStatus('saving');
        setSaveErrorMessage(null);
        setValidationErrors(null);

        try {
            const saved = await updateGlobalStyles(apiConfig, id, {
                version: draft.version,
                settings: draft.settings,
                styles: draft.styles,
            });

            if (isStale()) {
                return saved;
            }

            setRecord(saved);
            setEdits({ ...EMPTY_EDITS });
            setSaveStatus('saved');
            setLastSavedAt(new Date());

            return saved;
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
                    __('Failed to save global styles.', TEXT_DOMAIN)
                );
            }

            return null;
        }
    }, [apiConfig, draft, id]);

    return {
        id,
        base,
        record,
        draft,
        loadStatus,
        loadErrorMessage,
        isDirty,
        saveStatus,
        saveErrorMessage,
        validationErrors,
        lastSavedAt,
        readValue,
        readBaseValue,
        isPathCustomized,
        setValue,
        replaceSubtree,
        resetPath,
        resetAll,
        save,
    };
}
