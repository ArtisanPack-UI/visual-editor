/**
 * Navigation editor state hook.
 *
 * Parallel to `useEntityEditor` (templates / parts) but shaped around
 * the native MenuItem tree the D4 canvas works with rather than a
 * Gutenberg block tree. The hook handles loading, dirty-state
 * tracking, and saving — the canvas component owns the tree
 * mutations and dispatches them through `setTree`.
 */

import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { SiteEditorApiError } from '../api-client';
import type {
    NavigationRecord,
    NavigationUpdatePayload,
} from './api-client';
import { fetchNavigation, updateNavigation } from './api-client';
import {
    blocksToMenuTree,
    menuTreeToBlocks,
    type MenuItem,
} from './menu-tree';

import type { SiteEditorApiConfig } from '../api-client';
import type { ValidationErrors } from '../api-client';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
export type LoadStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface UseNavigationEditorOptions {
    apiConfig: SiteEditorApiConfig;
    /** `null` = no menu open — hook stays idle. */
    entityId: string | null;
}

export interface NavigationEntityFields {
    title: string;
    slug: string;
    location: string | null;
}

export interface UseNavigationEditorResult {
    entity: NavigationRecord | null;
    loadStatus: LoadStatus;
    loadErrorMessage: string | null;

    tree: readonly MenuItem[];
    setTree: (tree: readonly MenuItem[]) => void;

    fields: NavigationEntityFields;
    setFields: (
        update: Partial<NavigationEntityFields>
    ) => void;

    isDirty: boolean;

    saveStatus: SaveStatus;
    saveErrorMessage: string | null;
    validationErrors: ValidationErrors | null;
    lastSavedAt: Date | null;

    save: () => Promise<NavigationRecord | null>;
    reset: () => void;
}

const EMPTY_FIELDS: NavigationEntityFields = {
    title: '',
    slug: '',
    location: null,
};

export function useNavigationEditor(
    options: UseNavigationEditorOptions
): UseNavigationEditorResult {
    const { apiConfig, entityId } = options;

    const [entity, setEntity] = useState<NavigationRecord | null>(null);
    const [loadStatus, setLoadStatus] = useState<LoadStatus>('idle');
    const [loadErrorMessage, setLoadErrorMessage] = useState<string | null>(
        null
    );
    const [tree, setTreeState] = useState<readonly MenuItem[]>([]);
    const [fields, setFieldsState] =
        useState<NavigationEntityFields>(EMPTY_FIELDS);
    const [savedTreeKey, setSavedTreeKey] = useState<string>('[]');
    const [savedFields, setSavedFields] =
        useState<NavigationEntityFields>(EMPTY_FIELDS);

    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [saveErrorMessage, setSaveErrorMessage] = useState<string | null>(
        null
    );
    const [validationErrors, setValidationErrors] =
        useState<ValidationErrors | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

    // Tracks the most recent load — abort flag prevents a slow earlier
    // request from clobbering state when the user opens a different
    // menu mid-fetch.
    const loadEpoch = useRef(0);
    // Same idea for saves; a stale save mid-flight when the user closes
    // the menu must not flip status back to 'saved'.
    const saveEpoch = useRef(0);

    const treeKey = useMemo(() => stringifyTree(tree), [tree]);
    const fieldsKey = useMemo(() => stringifyFields(fields), [fields]);
    const savedFieldsKey = useMemo(
        () => stringifyFields(savedFields),
        [savedFields]
    );

    const isDirty =
        treeKey !== savedTreeKey || fieldsKey !== savedFieldsKey;

    useEffect(() => {
        if (entityId === null) {
            loadEpoch.current += 1;
            setEntity(null);
            setLoadStatus('idle');
            setLoadErrorMessage(null);
            setTreeState([]);
            setFieldsState(EMPTY_FIELDS);
            setSavedFields(EMPTY_FIELDS);
            setSavedTreeKey('[]');
            setSaveStatus('idle');
            setSaveErrorMessage(null);
            setValidationErrors(null);
            setLastSavedAt(null);

            return undefined;
        }

        loadEpoch.current += 1;
        const epoch = loadEpoch.current;

        setLoadStatus('loading');
        setLoadErrorMessage(null);
        setSaveStatus('idle');
        setSaveErrorMessage(null);
        setValidationErrors(null);

        let cancelled = false;

        (async () => {
            try {
                const record = await fetchNavigation(apiConfig, entityId);

                if (cancelled || epoch !== loadEpoch.current) {
                    return;
                }

                const initialTree = blocksToMenuTree(record.content.blocks);
                const initialFields: NavigationEntityFields = {
                    title: record.title.rendered,
                    slug: record.slug,
                    location: record.location,
                };

                setEntity(record);
                setLoadStatus('ready');
                setTreeState(initialTree);
                setFieldsState(initialFields);
                setSavedFields(initialFields);
                setSavedTreeKey(stringifyTree(initialTree));
                setLastSavedAt(null);
            } catch (error: unknown) {
                if (cancelled || epoch !== loadEpoch.current) {
                    return;
                }

                setLoadStatus('error');
                setLoadErrorMessage(extractErrorMessage(error));
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [apiConfig, entityId]);

    const setTree = useCallback((next: readonly MenuItem[]): void => {
        setTreeState(next);
    }, []);

    const setFields = useCallback(
        (update: Partial<NavigationEntityFields>): void => {
            setFieldsState((current) => ({ ...current, ...update }));
        },
        []
    );

    const save = useCallback(async (): Promise<NavigationRecord | null> => {
        if (entity === null) {
            return null;
        }

        saveEpoch.current += 1;
        const epoch = saveEpoch.current;

        setSaveStatus('saving');
        setSaveErrorMessage(null);
        setValidationErrors(null);

        const blocks = menuTreeToBlocks(tree);
        const payload: NavigationUpdatePayload = {
            content: { raw: '', blocks },
            title: fields.title,
            slug: fields.slug,
            location: fields.location,
        };

        try {
            const updated = await updateNavigation(
                apiConfig,
                entity.id,
                payload
            );

            if (epoch !== saveEpoch.current) {
                return null;
            }

            const updatedTree = blocksToMenuTree(updated.content.blocks);
            const updatedFields: NavigationEntityFields = {
                title: updated.title.rendered,
                slug: updated.slug,
                location: updated.location,
            };

            setEntity(updated);
            setTreeState(updatedTree);
            setFieldsState(updatedFields);
            setSavedFields(updatedFields);
            setSavedTreeKey(stringifyTree(updatedTree));
            setSaveStatus('saved');
            setLastSavedAt(new Date());

            return updated;
        } catch (error: unknown) {
            if (epoch !== saveEpoch.current) {
                return null;
            }

            setSaveStatus('error');
            setSaveErrorMessage(extractErrorMessage(error));

            if (error instanceof SiteEditorApiError) {
                setValidationErrors(error.validationErrors);
            }

            return null;
        }
    }, [apiConfig, entity, fields, tree]);

    const reset = useCallback((): void => {
        if (entity === null) {
            return;
        }

        const initialTree = blocksToMenuTree(entity.content.blocks);
        const initialFields: NavigationEntityFields = {
            title: entity.title.rendered,
            slug: entity.slug,
            location: entity.location,
        };

        setTreeState(initialTree);
        setFieldsState(initialFields);
        setSavedFields(initialFields);
        setSavedTreeKey(stringifyTree(initialTree));
        setSaveStatus('idle');
        setSaveErrorMessage(null);
        setValidationErrors(null);
    }, [entity]);

    return {
        entity,
        loadStatus,
        loadErrorMessage,
        tree,
        setTree,
        fields,
        setFields,
        isDirty,
        saveStatus,
        saveErrorMessage,
        validationErrors,
        lastSavedAt,
        save,
        reset,
    };
}

function stringifyTree(tree: readonly MenuItem[]): string {
    // Strip `localId` before signing — id only lives in memory and
    // gets regenerated each load, so including it would always show
    // dirty after a refetch.
    return JSON.stringify(tree.map(stripLocalIds));
}

function stripLocalIds(
    item: MenuItem
): Omit<MenuItem, 'localId' | 'children'> & {
    children: ReturnType<typeof stripLocalIds>[];
} {
    const { localId, children, ...rest } = item;
    void localId;

    return {
        ...rest,
        children: children.map(stripLocalIds),
    };
}

function stringifyFields(fields: NavigationEntityFields): string {
    return JSON.stringify(fields);
}

function extractErrorMessage(error: unknown): string {
    if (error instanceof SiteEditorApiError) {
        return error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return __('Unknown error.', TEXT_DOMAIN);
}
