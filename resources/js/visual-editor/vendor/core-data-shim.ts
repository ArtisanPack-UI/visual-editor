/**
 * Shim for `@wordpress/core-data`.
 *
 * Gutenberg's block-editor and block-library import from `@wordpress/core-data`
 * whether we want them to or not. This module is aliased in `vite.config.ts`
 * (and `vitest.config.ts`) so that every `import … from '@wordpress/core-data'`
 * in the editor bundle resolves here instead of the upstream package.
 *
 * ## Scope
 *
 * The M2 shim (#312) returned empty/no-op for every selector; that was enough
 * to keep the post-editor compiling while `disabled_blocks` hid every
 * data-dependent core block. B1 (#353) expands the surface so the five
 * entities that the site-editor UI (Phase C/D) and the post-editor's
 * new-block enablement (Phase E) depend on can round-trip through the
 * package's REST layer:
 *
 * - `postType:wp_template`       — templates
 * - `postType:wp_template_part`  — template parts
 * - `postType:wp_navigation`     — navigation menus
 * - `postType:wp_block`          — patterns (synced + unsynced)
 * - `root:globalStyles`          — theme.json-shaped site configuration
 *
 * The REST endpoints (C1–C5) do not exist yet. When they're missing, every
 * resolver falls back silently to empty state — `getEntityRecord` returns
 * null, `getEntityRecords` returns [], no errors are raised. See
 * `docs/core-data-shim.md` for the endpoint contract Phase C will implement.
 *
 * ## Out of scope for B1
 *
 * - Building the REST endpoints themselves (Phase C).
 * - Enabling the `core/navigation`, `core/post-*`, `core/site-*`,
 *   `core/template-part` blocks in `enabled_blocks` (Phase E4).
 * - Replacing the shim with the real `artisanpack-ui/cms-framework` backend
 *   (post-V1).
 *
 * This shim is temporary. Every selector it exposes is a selector we have to
 * re-verify on Gutenberg upgrades — keep the surface as narrow as observed
 * crashes allow.
 *
 * Tracked by: #312 (M2 original shim), #353 (B1 expansion), #309 (V1).
 */

import {
    createContext,
    createElement,
    useContext,
    useMemo,
    type PropsWithChildren,
    type ReactElement,
} from 'react';
import { createReduxStore, register } from '@wordpress/data';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type EntityKind = string;
export type EntityName = string;
export type EntityKey = number | string;
export type EntityRecord = Record<string, unknown>;

export interface EntityConfig {
    readonly kind: EntityKind;
    readonly name: EntityName;
    /** URL fragment appended to `apiBase` — e.g. `/templates`. */
    readonly baseURL: string;
    /** Primary-key field on the record payload. */
    readonly key: string;
    /** Human-readable label for error messages / diagnostics. */
    readonly label: string;
    /** Plural form for list-selector naming. */
    readonly plural?: string;
}

export interface GlobalStylesRecord extends EntityRecord {
    id: EntityKey;
    settings?: Record<string, unknown>;
    styles?: Record<string, unknown>;
}

interface EntityIdentity {
    readonly kind: EntityKind;
    readonly name: EntityName;
    readonly id: EntityKey | undefined;
}

interface EntityBag {
    items: Record<string, EntityRecord>;
    queries: Record<string, readonly EntityKey[]>;
    queryMeta: Record<string, { totalItems: number; totalPages: number }>;
}

type EditsBag = Record<string, EntityRecord>;

interface CoreDataState {
    entities: Record<string, EntityConfig>;
    records: Record<string, EntityBag>;
    edits: Record<string, EditsBag>;
    saving: Record<string, Record<string, boolean>>;
    deleting: Record<string, Record<string, boolean>>;
    saveErrors: Record<string, Record<string, unknown | null>>;
    deleteErrors: Record<string, Record<string, unknown | null>>;
    globalStylesBase: Record<string, unknown> | null;
    currentGlobalStylesId: EntityKey | null;
}

type CoreDataAction =
    | { type: 'ADD_ENTITIES'; entities: readonly EntityConfig[] }
    | {
          type: 'RECEIVE_ENTITY_RECORDS';
          kind: EntityKind;
          name: EntityName;
          records: readonly EntityRecord[];
          query?: Record<string, unknown> | null;
          totalItems?: number;
          totalPages?: number;
      }
    | {
          type: 'REMOVE_ENTITY_RECORD';
          kind: EntityKind;
          name: EntityName;
          id: EntityKey;
      }
    | {
          type: 'EDIT_ENTITY_RECORD';
          kind: EntityKind;
          name: EntityName;
          id: EntityKey;
          edits: EntityRecord;
      }
    | {
          type: 'CLEAR_ENTITY_RECORD_EDITS';
          kind: EntityKind;
          name: EntityName;
          id: EntityKey;
      }
    | {
          type: 'SET_SAVING';
          kind: EntityKind;
          name: EntityName;
          id: EntityKey;
          saving: boolean;
          error: unknown | null;
      }
    | {
          type: 'SET_DELETING';
          kind: EntityKind;
          name: EntityName;
          id: EntityKey;
          deleting: boolean;
          error: unknown | null;
      }
    | {
          type: 'RECEIVE_GLOBAL_STYLES_BASE';
          styles: Record<string, unknown> | null;
      }
    | { type: 'RECEIVE_CURRENT_GLOBAL_STYLES_ID'; id: EntityKey | null }
    | { type: 'SHIM_NOOP' }
    | { type: 'SHIM_RESET' };

// ---------------------------------------------------------------------------
// Default entity registry
// ---------------------------------------------------------------------------

export const DEFAULT_ENTITIES: readonly EntityConfig[] = Object.freeze([
    {
        kind: 'postType',
        name: 'wp_template',
        baseURL: '/templates',
        key: 'id',
        label: 'Template',
        plural: 'templates',
    },
    {
        kind: 'postType',
        name: 'wp_template_part',
        baseURL: '/template-parts',
        key: 'id',
        label: 'Template Part',
        plural: 'templateParts',
    },
    {
        kind: 'postType',
        name: 'wp_navigation',
        baseURL: '/navigation',
        key: 'id',
        label: 'Navigation',
        plural: 'navigations',
    },
    {
        kind: 'postType',
        name: 'wp_block',
        baseURL: '/patterns',
        key: 'id',
        label: 'Pattern',
        plural: 'patterns',
    },
    {
        kind: 'root',
        name: 'globalStyles',
        baseURL: '/global-styles',
        key: 'id',
        label: 'Global Styles',
        plural: 'globalStyles',
    },
]);

// ---------------------------------------------------------------------------
// Shim configuration
// ---------------------------------------------------------------------------

export interface ShimConfig {
    /** URL prefix the package's REST endpoints live under. */
    apiBase: string;
    /** Custom fetch (for tests; defaults to the global `fetch`). */
    fetcher: typeof fetch;
}

const DEFAULT_API_BASE = '/visual-editor/api';

function defaultFetcher(): typeof fetch {
    if (typeof fetch === 'function') {
        return fetch.bind(globalThis);
    }

    return (() => {
        throw new Error('core-data-shim: fetch is not available in this environment.');
    }) as typeof fetch;
}

let shimConfig: ShimConfig = {
    apiBase: DEFAULT_API_BASE,
    fetcher: defaultFetcher(),
};

/**
 * Updates the shim's runtime configuration. Call once at editor bootstrap
 * before any block-editor / block-library code dispatches through the
 * `core` store.
 */
export function configureCoreDataShim(
    config: Partial<Readonly<ShimConfig>>,
): void {
    shimConfig = {
        apiBase: config.apiBase ?? shimConfig.apiBase,
        fetcher: config.fetcher ?? shimConfig.fetcher,
    };
}

/**
 * Resets the shim's runtime configuration to defaults. Intended for tests.
 * @internal
 */
export function __resetCoreDataShimConfig(): void {
    shimConfig = {
        apiBase: DEFAULT_API_BASE,
        fetcher: defaultFetcher(),
    };
}

// ---------------------------------------------------------------------------
// Key helpers
// ---------------------------------------------------------------------------

const STORE_NAME = 'core';
const EMPTY_RECORDS: readonly never[] = Object.freeze([]);

function entityKey(kind: EntityKind, name: EntityName): string {
    return `${kind}|${name}`;
}

function queryKey(query?: Record<string, unknown> | null): string {
    if (!query) {
        return '';
    }

    const entries = Object.entries(query);

    if (entries.length === 0) {
        return '';
    }

    return entries
        .slice()
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([k, v]) => `${k}=${JSON.stringify(v)}`)
        .join('&');
}

function recordIdOf(
    record: EntityRecord,
    config: EntityConfig,
): EntityKey | null {
    const raw = record[config.key];

    if (typeof raw === 'string' || typeof raw === 'number') {
        return raw;
    }

    return null;
}

function entityUrl(config: EntityConfig, id?: EntityKey): string {
    const base = shimConfig.apiBase.replace(/\/$/, '');
    const path = config.baseURL.startsWith('/')
        ? config.baseURL
        : `/${config.baseURL}`;

    if (id === undefined) {
        return `${base}${path}`;
    }

    return `${base}${path}/${encodeURIComponent(String(id))}`;
}

function queryString(query?: Record<string, unknown> | null): string {
    if (!query) {
        return '';
    }

    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(query)) {
        if (value === undefined || value === null) {
            continue;
        }

        if (Array.isArray(value)) {
            for (const item of value) {
                params.append(key, String(item));
            }

            continue;
        }

        params.append(key, String(value));
    }

    const serialized = params.toString();

    return serialized.length > 0 ? `?${serialized}` : '';
}

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );

    return meta?.content?.trim() || null;
}

function buildHeaders(includeCsrf: boolean): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (includeCsrf) {
        const token = readCsrfToken();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }
    }

    return headers;
}

async function parseJson(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text === '') {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

async function restRequest(
    url: string,
    init: RequestInit,
): Promise<unknown> {
    const response = await shimConfig.fetcher(url, {
        credentials: 'same-origin',
        ...init,
    });

    const body = await parseJson(response);

    if (!response.ok) {
        throw new Error(
            `core-data-shim: ${init.method ?? 'GET'} ${url} failed with status ${response.status}`,
        );
    }

    return body;
}

// ---------------------------------------------------------------------------
// Reducer
// ---------------------------------------------------------------------------

function buildInitialEntities(): Record<string, EntityConfig> {
    const entries: Record<string, EntityConfig> = {};

    for (const config of DEFAULT_ENTITIES) {
        entries[entityKey(config.kind, config.name)] = config;
    }

    return entries;
}

const INITIAL_STATE: CoreDataState = {
    entities: buildInitialEntities(),
    records: {},
    edits: {},
    saving: {},
    deleting: {},
    saveErrors: {},
    deleteErrors: {},
    globalStylesBase: null,
    currentGlobalStylesId: null,
};

function reducer(
    state: CoreDataState = INITIAL_STATE,
    action: CoreDataAction,
): CoreDataState {
    switch (action.type) {
        case 'ADD_ENTITIES': {
            if (action.entities.length === 0) {
                return state;
            }

            const next = { ...state.entities };

            for (const config of action.entities) {
                next[entityKey(config.kind, config.name)] = config;
            }

            return { ...state, entities: next };
        }

        case 'RECEIVE_ENTITY_RECORDS': {
            const key = entityKey(action.kind, action.name);
            const bag: EntityBag = state.records[key] ?? {
                items: {},
                queries: {},
                queryMeta: {},
            };
            const config = state.entities[key];

            const nextItems: Record<string, EntityRecord> = { ...bag.items };
            const receivedIds: EntityKey[] = [];

            for (const record of action.records) {
                const id = config ? recordIdOf(record, config) : null;

                if (id === null) {
                    continue;
                }

                nextItems[String(id)] = record;
                receivedIds.push(id);
            }

            const nextQueries = { ...bag.queries };
            const nextQueryMeta = { ...bag.queryMeta };

            // `query === undefined` means a single-record fetch just landed,
            // so only the `items` map needs updating. `query === null` (or
            // `{}`) is the callers' "I fetched the whole list" signal and
            // caches under the empty-string slot; a real filter query
            // caches under its stable signature.
            if (action.query !== undefined) {
                const qKey = queryKey(action.query);

                nextQueries[qKey] = Object.freeze(receivedIds);
                nextQueryMeta[qKey] = {
                    totalItems: action.totalItems ?? receivedIds.length,
                    totalPages: action.totalPages ?? 1,
                };
            }

            return {
                ...state,
                records: {
                    ...state.records,
                    [key]: {
                        items: nextItems,
                        queries: nextQueries,
                        queryMeta: nextQueryMeta,
                    },
                },
            };
        }

        case 'REMOVE_ENTITY_RECORD': {
            const key = entityKey(action.kind, action.name);
            const bag = state.records[key];

            if (!bag) {
                return state;
            }

            const nextItems = { ...bag.items };
            delete nextItems[String(action.id)];

            const nextQueries: Record<string, readonly EntityKey[]> = {};

            for (const [qKey, ids] of Object.entries(bag.queries)) {
                nextQueries[qKey] = Object.freeze(
                    ids.filter((existing) => String(existing) !== String(action.id)),
                );
            }

            const nextEdits = { ...(state.edits[key] ?? {}) };
            delete nextEdits[String(action.id)];

            return {
                ...state,
                records: {
                    ...state.records,
                    [key]: {
                        items: nextItems,
                        queries: nextQueries,
                        queryMeta: bag.queryMeta,
                    },
                },
                edits: {
                    ...state.edits,
                    [key]: nextEdits,
                },
            };
        }

        case 'EDIT_ENTITY_RECORD': {
            const key = entityKey(action.kind, action.name);
            const existing = state.edits[key]?.[String(action.id)] ?? {};

            return {
                ...state,
                edits: {
                    ...state.edits,
                    [key]: {
                        ...(state.edits[key] ?? {}),
                        [String(action.id)]: { ...existing, ...action.edits },
                    },
                },
            };
        }

        case 'CLEAR_ENTITY_RECORD_EDITS': {
            const key = entityKey(action.kind, action.name);
            const bag = state.edits[key];

            if (!bag || bag[String(action.id)] === undefined) {
                return state;
            }

            const nextBag = { ...bag };
            delete nextBag[String(action.id)];

            return {
                ...state,
                edits: { ...state.edits, [key]: nextBag },
            };
        }

        case 'SET_SAVING': {
            const key = entityKey(action.kind, action.name);

            return {
                ...state,
                saving: {
                    ...state.saving,
                    [key]: {
                        ...(state.saving[key] ?? {}),
                        [String(action.id)]: action.saving,
                    },
                },
                saveErrors: {
                    ...state.saveErrors,
                    [key]: {
                        ...(state.saveErrors[key] ?? {}),
                        [String(action.id)]: action.error,
                    },
                },
            };
        }

        case 'SET_DELETING': {
            const key = entityKey(action.kind, action.name);

            return {
                ...state,
                deleting: {
                    ...state.deleting,
                    [key]: {
                        ...(state.deleting[key] ?? {}),
                        [String(action.id)]: action.deleting,
                    },
                },
                deleteErrors: {
                    ...state.deleteErrors,
                    [key]: {
                        ...(state.deleteErrors[key] ?? {}),
                        [String(action.id)]: action.error,
                    },
                },
            };
        }

        case 'RECEIVE_GLOBAL_STYLES_BASE':
            return { ...state, globalStylesBase: action.styles };

        case 'RECEIVE_CURRENT_GLOBAL_STYLES_ID':
            return { ...state, currentGlobalStylesId: action.id };

        case 'SHIM_RESET':
            return INITIAL_STATE;

        default:
            return state;
    }
}

// ---------------------------------------------------------------------------
// Selectors
// ---------------------------------------------------------------------------

function selectEntityConfig(
    state: CoreDataState,
    kind: EntityKind,
    name: EntityName,
): EntityConfig | null {
    return state.entities[entityKey(kind, name)] ?? null;
}

function selectEntityRecord(
    state: CoreDataState,
    kind: EntityKind,
    name: EntityName,
    id: EntityKey,
): EntityRecord | null {
    return (
        state.records[entityKey(kind, name)]?.items[String(id)] ?? null
    );
}

function selectEntityRecords(
    state: CoreDataState,
    kind: EntityKind,
    name: EntityName,
    query?: Record<string, unknown> | null,
): readonly EntityRecord[] {
    const bag = state.records[entityKey(kind, name)];

    if (!bag) {
        return EMPTY_RECORDS;
    }

    const qKey = queryKey(query);

    if (query === undefined) {
        return Object.values(bag.items);
    }

    const ids = bag.queries[qKey];

    if (!ids) {
        return EMPTY_RECORDS;
    }

    const records: EntityRecord[] = [];

    for (const id of ids) {
        const record = bag.items[String(id)];

        if (record !== undefined) {
            records.push(record);
        }
    }

    return records;
}

function selectEditsForRecord(
    state: CoreDataState,
    kind: EntityKind,
    name: EntityName,
    id: EntityKey,
): EntityRecord | null {
    const edits = state.edits[entityKey(kind, name)]?.[String(id)];

    return edits ?? null;
}

const selectors = {
    getEntities: (state: CoreDataState): readonly EntityConfig[] =>
        Object.values(state.entities),

    getEntityConfig: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
    ): EntityConfig | null => selectEntityConfig(state, kind, name),

    getEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): EntityRecord | null => selectEntityRecord(state, kind, name, id),

    getRawEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): EntityRecord | null => selectEntityRecord(state, kind, name, id),

    getEntityRecords: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        query?: Record<string, unknown> | null,
    ): readonly EntityRecord[] =>
        selectEntityRecords(state, kind, name, query),

    getEntityRecordsTotalItems: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        query?: Record<string, unknown> | null,
    ): number => {
        const bag = state.records[entityKey(kind, name)];

        return bag?.queryMeta[queryKey(query)]?.totalItems ?? 0;
    },

    getEntityRecordsTotalPages: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        query?: Record<string, unknown> | null,
    ): number => {
        const bag = state.records[entityKey(kind, name)];

        return bag?.queryMeta[queryKey(query)]?.totalPages ?? 0;
    },

    getEditedEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): EntityRecord | null => {
        const base = selectEntityRecord(state, kind, name, id);
        const edits = selectEditsForRecord(state, kind, name, id);

        if (base === null && edits === null) {
            return null;
        }

        return { ...(base ?? {}), ...(edits ?? {}) };
    },

    getEntityRecordEdits: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): EntityRecord | null => selectEditsForRecord(state, kind, name, id),

    getEntityRecordNonTransientEdits: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): EntityRecord | null => selectEditsForRecord(state, kind, name, id),

    hasEditsForEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): boolean => {
        const edits = selectEditsForRecord(state, kind, name, id);

        return edits !== null && Object.keys(edits).length > 0;
    },

    isSavingEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): boolean => state.saving[entityKey(kind, name)]?.[String(id)] === true,

    isDeletingEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): boolean =>
        state.deleting[entityKey(kind, name)]?.[String(id)] === true,

    getLastEntitySaveError: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): unknown | null =>
        state.saveErrors[entityKey(kind, name)]?.[String(id)] ?? null,

    getLastEntityDeleteError: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): unknown | null =>
        state.deleteErrors[entityKey(kind, name)]?.[String(id)] ?? null,

    __experimentalGetCurrentGlobalStylesId: (
        state: CoreDataState,
    ): EntityKey | null => state.currentGlobalStylesId,

    __experimentalGlobalStylesBaseStyles: (
        state: CoreDataState,
    ): Record<string, unknown> | null => state.globalStylesBase,

    // Stubs retained for back-compat with pre-B1 callsites.
    getCurrentUser: (): EntityRecord | null => null,
    getUsers: (): readonly EntityRecord[] => EMPTY_RECORDS,
    getMedia: (): EntityRecord | null => null,
    getMediaItems: (): readonly EntityRecord[] => EMPTY_RECORDS,
    hasFinishedResolution: (): boolean => true,
    hasStartedResolution: (): boolean => true,
    isResolving: (): boolean => false,
    canUser: (): boolean => false,
    canUserEditEntityRecord: (): boolean => false,
    getAutosaves: (): readonly EntityRecord[] => EMPTY_RECORDS,
    getAutosave: (): EntityRecord | null => null,
    getReferenceByDistinctEdits: (): readonly number[] => EMPTY_RECORDS,
};

// ---------------------------------------------------------------------------
// Thunk-style actions
// ---------------------------------------------------------------------------

interface ThunkArgs {
    dispatch: {
        (action: CoreDataAction): CoreDataAction;
        receiveEntityRecords: (
            kind: EntityKind,
            name: EntityName,
            records: readonly EntityRecord[],
            query?: Record<string, unknown> | null,
            totalItems?: number,
            totalPages?: number,
        ) => CoreDataAction;
        editEntityRecord: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
            edits: EntityRecord,
        ) => CoreDataAction;
        removeEntityRecord: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
        ) => CoreDataAction;
        setEntitySaving: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
            saving: boolean,
            error: unknown | null,
        ) => CoreDataAction;
        setEntityDeleting: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
            deleting: boolean,
            error: unknown | null,
        ) => CoreDataAction;
        clearEntityRecordEdits: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
        ) => CoreDataAction;
    };
    select: {
        getEntityConfig: (
            kind: EntityKind,
            name: EntityName,
        ) => EntityConfig | null;
        getEntityRecord: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
        ) => EntityRecord | null;
        getEditedEntityRecord: (
            kind: EntityKind,
            name: EntityName,
            id: EntityKey,
        ) => EntityRecord | null;
    };
}

const actions = {
    addEntities: (entities: readonly EntityConfig[]): CoreDataAction => ({
        type: 'ADD_ENTITIES',
        entities,
    }),

    receiveEntityRecords: (
        kind: EntityKind,
        name: EntityName,
        records: readonly EntityRecord[],
        query?: Record<string, unknown> | null,
        totalItems?: number,
        totalPages?: number,
    ): CoreDataAction => ({
        type: 'RECEIVE_ENTITY_RECORDS',
        kind,
        name,
        records,
        query,
        totalItems,
        totalPages,
    }),

    removeEntityRecord: (
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): CoreDataAction => ({
        type: 'REMOVE_ENTITY_RECORD',
        kind,
        name,
        id,
    }),

    editEntityRecord: (
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
        edits: EntityRecord,
    ): CoreDataAction => ({
        type: 'EDIT_ENTITY_RECORD',
        kind,
        name,
        id,
        edits,
    }),

    clearEntityRecordEdits: (
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): CoreDataAction => ({
        type: 'CLEAR_ENTITY_RECORD_EDITS',
        kind,
        name,
        id,
    }),

    setEntitySaving: (
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
        saving: boolean,
        error: unknown | null = null,
    ): CoreDataAction => ({
        type: 'SET_SAVING',
        kind,
        name,
        id,
        saving,
        error,
    }),

    setEntityDeleting: (
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
        deleting: boolean,
        error: unknown | null = null,
    ): CoreDataAction => ({
        type: 'SET_DELETING',
        kind,
        name,
        id,
        deleting,
        error,
    }),

    receiveGlobalStylesBase: (
        styles: Record<string, unknown> | null,
    ): CoreDataAction => ({ type: 'RECEIVE_GLOBAL_STYLES_BASE', styles }),

    receiveCurrentGlobalStylesId: (
        id: EntityKey | null,
    ): CoreDataAction => ({ type: 'RECEIVE_CURRENT_GLOBAL_STYLES_ID', id }),

    /**
     * Resets the store to its initial state. Tests and HMR wire this up
     * between runs; the production editor never needs it.
     * @internal
     */
    reset: (): CoreDataAction => ({ type: 'SHIM_RESET' }),

    // Retained for back-compat.
    receiveUserQuery: (): CoreDataAction => ({ type: 'SHIM_NOOP' }),
    receiveCurrentUser: (): CoreDataAction => ({ type: 'SHIM_NOOP' }),
    undo: (): CoreDataAction => ({ type: 'SHIM_NOOP' }),
    redo: (): CoreDataAction => ({ type: 'SHIM_NOOP' }),
    __unstableCreateUndoLevel: (): CoreDataAction => ({ type: 'SHIM_NOOP' }),

    /**
     * Fetches a single entity record from the REST API and caches it.
     * Swallows network/endpoint failures so unregistered or not-yet-built
     * endpoints degrade to an empty result instead of throwing.
     */
    fetchEntityRecord:
        (kind: EntityKind, name: EntityName, id: EntityKey) =>
        async ({ dispatch, select }: ThunkArgs): Promise<EntityRecord | null> => {
            const config = select.getEntityConfig(kind, name);

            if (!config) {
                return null;
            }

            try {
                const record = (await restRequest(entityUrl(config, id), {
                    method: 'GET',
                    headers: buildHeaders(false),
                })) as EntityRecord | null;

                if (record !== null) {
                    dispatch.receiveEntityRecords(kind, name, [record]);
                }

                return record;
            } catch {
                return null;
            }
        },

    /**
     * Fetches a list of entity records, keyed by query signature.
     * Swallows failures — returns [] when the endpoint is missing.
     */
    fetchEntityRecords:
        (
            kind: EntityKind,
            name: EntityName,
            query?: Record<string, unknown> | null,
        ) =>
        async ({
            dispatch,
            select,
        }: ThunkArgs): Promise<readonly EntityRecord[]> => {
            const config = select.getEntityConfig(kind, name);

            if (!config) {
                return EMPTY_RECORDS;
            }

            try {
                const url = `${entityUrl(config)}${queryString(query)}`;
                const body = (await restRequest(url, {
                    method: 'GET',
                    headers: buildHeaders(false),
                })) as unknown;

                const parsed = normalizeListResponse(body);

                dispatch.receiveEntityRecords(
                    kind,
                    name,
                    parsed.records,
                    query ?? null,
                    parsed.totalItems,
                    parsed.totalPages,
                );

                return parsed.records;
            } catch {
                dispatch.receiveEntityRecords(
                    kind,
                    name,
                    EMPTY_RECORDS,
                    query ?? null,
                    0,
                    0,
                );

                return EMPTY_RECORDS;
            }
        },

    /**
     * Creates or updates an entity record in one shot.
     *
     * - Record without its key field → POST to the collection URL.
     * - Record with its key field → PUT to the item URL.
     */
    saveEntityRecord:
        (kind: EntityKind, name: EntityName, record: EntityRecord) =>
        async ({ dispatch, select }: ThunkArgs): Promise<EntityRecord | null> => {
            const config = select.getEntityConfig(kind, name);

            if (!config) {
                return null;
            }

            const existingId = recordIdOf(record, config);
            const saveSlotId = existingId ?? '__new__';

            dispatch.setEntitySaving(kind, name, saveSlotId, true, null);

            try {
                const url =
                    existingId === null
                        ? entityUrl(config)
                        : entityUrl(config, existingId);
                const method = existingId === null ? 'POST' : 'PUT';

                const saved = (await restRequest(url, {
                    method,
                    headers: buildHeaders(true),
                    body: JSON.stringify(record),
                })) as EntityRecord | null;

                if (saved !== null) {
                    dispatch.receiveEntityRecords(kind, name, [saved]);
                }

                dispatch.setEntitySaving(kind, name, saveSlotId, false, null);

                return saved;
            } catch (error) {
                dispatch.setEntitySaving(kind, name, saveSlotId, false, error);

                return null;
            }
        },

    /**
     * PUTs the edited record (base + edits) back to the server, then
     * clears the edits bag on success. Leaves the edits intact on failure
     * so the UI can retry.
     */
    saveEditedEntityRecord:
        (kind: EntityKind, name: EntityName, id: EntityKey) =>
        async ({ dispatch, select }: ThunkArgs): Promise<EntityRecord | null> => {
            const edited = select.getEditedEntityRecord(kind, name, id);

            if (edited === null) {
                return null;
            }

            const saved = await actions.saveEntityRecord(kind, name, edited)({
                dispatch,
                select,
            });

            if (saved !== null) {
                dispatch.clearEntityRecordEdits(kind, name, id);
            }

            return saved;
        },

    /**
     * DELETEs an entity record and evicts it from the store on success.
     */
    deleteEntityRecord:
        (kind: EntityKind, name: EntityName, id: EntityKey) =>
        async ({ dispatch, select }: ThunkArgs): Promise<boolean> => {
            const config = select.getEntityConfig(kind, name);

            if (!config) {
                return false;
            }

            dispatch.setEntityDeleting(kind, name, id, true, null);

            try {
                await restRequest(entityUrl(config, id), {
                    method: 'DELETE',
                    headers: buildHeaders(true),
                });

                dispatch.removeEntityRecord(kind, name, id);
                dispatch.setEntityDeleting(kind, name, id, false, null);

                return true;
            } catch (error) {
                dispatch.setEntityDeleting(kind, name, id, false, error);

                return false;
            }
        },
};

interface NormalizedList {
    records: readonly EntityRecord[];
    totalItems: number;
    totalPages: number;
}

/**
 * Accepts either a bare array of records or a `{ data, meta }` envelope
 * (Laravel's default pagination shape) and normalizes to the store's
 * record-list shape.
 */
function normalizeListResponse(body: unknown): NormalizedList {
    if (Array.isArray(body)) {
        return {
            records: body as readonly EntityRecord[],
            totalItems: body.length,
            totalPages: 1,
        };
    }

    if (body && typeof body === 'object') {
        const envelope = body as {
            data?: unknown;
            meta?: { total?: unknown; last_page?: unknown };
        };
        const data = Array.isArray(envelope.data)
            ? (envelope.data as readonly EntityRecord[])
            : EMPTY_RECORDS;
        const meta = envelope.meta ?? {};
        const totalItems =
            typeof meta.total === 'number' ? meta.total : data.length;
        const totalPages =
            typeof meta.last_page === 'number' ? meta.last_page : 1;

        return { records: data, totalItems, totalPages };
    }

    return { records: EMPTY_RECORDS, totalItems: 0, totalPages: 0 };
}

// ---------------------------------------------------------------------------
// Store registration
// ---------------------------------------------------------------------------

export const store = createReduxStore(STORE_NAME, {
    reducer: reducer as unknown as (
        state: CoreDataState,
        action: { type: string },
    ) => CoreDataState,
    actions: actions as unknown as Record<string, unknown>,
    selectors: selectors as unknown as Record<string, unknown>,
});

register(store);

// ---------------------------------------------------------------------------
// React context + hooks
// ---------------------------------------------------------------------------

/**
 * `EntityProvider` in upstream `@wordpress/core-data` supplies the ambient
 * entity identity (kind/name/id) to hooks like `useEntityProp`. The shim
 * preserves that contract so blocks reading the context don't throw.
 */
const EntityContext = createContext<EntityIdentity>({
    kind: '',
    name: '',
    id: undefined,
});

export function EntityProvider(
    props: PropsWithChildren<EntityIdentity>,
): ReactElement {
    const { kind, name, id, children } = props;
    const value = useMemo<EntityIdentity>(
        () => ({ kind, name, id }),
        [kind, name, id],
    );

    return createElement(EntityContext.Provider, { value }, children);
}

export function useEntityId(): EntityKey | undefined {
    return useContext(EntityContext).id;
}

/**
 * No-op setter returned by write-capable hooks. Kept as a module-level
 * singleton so React memoization downstream doesn't see a fresh function
 * identity on every render.
 */
const noopSetter = (): void => {};

export function useEntityProp<T = unknown>(): [
    T | undefined,
    typeof noopSetter,
    T | undefined,
] {
    return [undefined, noopSetter, undefined];
}

export function useEntityRecord<T = unknown>(): {
    record: T | null;
    editedRecord: T | null;
    hasEdits: boolean;
    hasResolved: boolean;
    isResolving: boolean;
    edit: typeof noopSetter;
    save: () => Promise<null>;
} {
    return {
        record: null,
        editedRecord: null,
        hasEdits: false,
        hasResolved: true,
        isResolving: false,
        edit: noopSetter,
        save: async () => null,
    };
}

export function useEntityRecords<T = unknown>(): {
    records: readonly T[];
    hasResolved: boolean;
    isResolving: boolean;
    status: 'IDLE';
    totalItems: number;
    totalPages: number;
} {
    return {
        records: EMPTY_RECORDS as readonly T[],
        hasResolved: true,
        isResolving: false,
        status: 'IDLE',
        totalItems: 0,
        totalPages: 0,
    };
}

export function useEntityBlockEditor(): [
    readonly never[],
    typeof noopSetter,
    typeof noopSetter,
] {
    return [EMPTY_RECORDS, noopSetter, noopSetter];
}

export function useResourcePermissions(): {
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    isResolving: boolean;
} {
    return {
        canCreate: false,
        canUpdate: false,
        canDelete: false,
        isResolving: false,
    };
}
