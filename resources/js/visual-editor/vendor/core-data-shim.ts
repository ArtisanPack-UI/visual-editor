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
import { createReduxStore, register, useDispatch, useSelect } from '@wordpress/data';

import { parseNavigationContent } from './parse-navigation-content';

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
    /**
     * Read-only alias map (e.g. `<theme>//<slug>` → numeric primary key)
     * for upstream-shape lookups. Never iterated as records and never
     * counted by `getEntityRecordsTotalItems` — only used to redirect
     * `getEntityRecord(kind, name, alias)` to the real item.
     */
    aliases: Record<string, string>;
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
        // H6 (#431) restructured the navigation surface from `/navigation`
        // (plan 11 Phase D) to `/menus` to match WP REST `wp_navigation`.
        // Individual items live at `/menu-items` under the
        // `wp_navigation_link` entity below.
        baseURL: '/menus',
        key: 'id',
        label: 'Navigation',
        plural: 'navigations',
    },
    {
        kind: 'postType',
        name: 'wp_navigation_link',
        baseURL: '/menu-items',
        key: 'id',
        label: 'Navigation Link',
        plural: 'navigationLinks',
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
    // G3 cms-framework entities — see plan 12 §4.4. Always declared so
    // `core/post-*` blocks can resolve via `useEntityRecord('postType',
    // 'post', id)` regardless of whether cms-framework is installed.
    // When the package is absent the matching `/visual-editor/api/{posts,
    // pages}/{id}` endpoints are unrouted and the resolver returns `null`
    // — no different from any other entity that hasn't been seeded yet.
    {
        kind: 'postType',
        name: 'post',
        baseURL: '/posts',
        key: 'id',
        label: 'Post',
        plural: 'posts',
    },
    {
        kind: 'postType',
        name: 'page',
        baseURL: '/pages',
        key: 'id',
        label: 'Page',
        plural: 'pages',
    },
    // G4a — `core/post-featured-image` and `core/cover` resolve the
    // saved `featured_media` id via `getEntityRecord('postType',
    // 'attachment', id)`. The matching endpoint
    // (`/visual-editor/api/attachments/{id}`) emits the WP REST media
    // shape by delegating to the host's media library through
    // `apGetMedia()`. When the helper isn't available the endpoint
    // 404s and the block falls back to its empty placeholder.
    {
        kind: 'postType',
        name: 'attachment',
        baseURL: '/attachments',
        key: 'id',
        label: 'Attachment',
        plural: 'attachments',
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

const CURRENT_THEME_STUB: EntityRecord = Object.freeze({
    stylesheet: 'artisanpack-base',
    template: 'artisanpack-base',
    name: 'ArtisanPack Base',
    is_block_theme: true,
});

const EMPTY_THEME_SUPPORTS: EntityRecord = Object.freeze({});

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

/**
 * Returns the upstream `<theme>//<slug>` composite key for a record, or
 * `null` if the record can't form one. Used to mirror template-part /
 * template records under the lookup id the block-library expects without
 * changing the REST contract.
 */
function compositeRecordKey(record: EntityRecord): string | null {
    const theme = record.theme;
    const slug = record.slug;

    if (typeof theme !== 'string' || typeof slug !== 'string') {
        return null;
    }

    if (theme === '' || slug === '') {
        return null;
    }

    return `${theme}//${slug}`;
}

/**
 * Splits a `<theme>//<slug>` composite id into its parts, or returns
 * `null` if the value isn't in that shape. The separator is a literal
 * `//` rather than a regex so slugs that legitimately contain `/`
 * round-trip without ambiguity.
 */
function splitCompositeId(
    id: string,
): { theme: string; slug: string } | null {
    const separator = '//';
    const idx = id.indexOf(separator);

    if (idx <= 0 || idx === id.length - separator.length) {
        return null;
    }

    return {
        theme: id.slice(0, idx),
        slug: id.slice(idx + separator.length),
    };
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
                aliases: {},
                queries: {},
                queryMeta: {},
            };
            const config = state.entities[key];

            const nextItems: Record<string, EntityRecord> = { ...bag.items };
            const nextAliases: Record<string, string> = { ...bag.aliases };
            const receivedIds: EntityKey[] = [];

            for (const record of action.records) {
                const id = config ? recordIdOf(record, config) : null;

                if (id === null) {
                    continue;
                }

                const primaryKey = String(id);
                nextItems[primaryKey] = record;
                receivedIds.push(id);

                // Upstream `@wordpress/block-library` looks up template parts
                // (and templates) by a composite `<theme>//<slug>` id while
                // our REST endpoints return numeric ids. Track the composite
                // form in a separate alias map keyed `composite → primary`
                // so `getEntityRecord(kind, name, "<theme>//<slug>")` resolves
                // to the same record without double-counting it in
                // `getEntityRecords` / `getEntityRecordsTotalItems`. See #395.
                const compositeKey = compositeRecordKey(record);

                if (compositeKey !== null && compositeKey !== primaryKey) {
                    nextAliases[compositeKey] = primaryKey;
                }
            }

            // `query === undefined` = save-path / single-record fetch.
            // Drop every cached filter query for this entity: we can't
            // know whether the new/updated record still matches any of
            // those filters, so the safe move is to invalidate them and
            // force a refetch on next list render. The live `items` bag
            // is still authoritative for `getEntityRecords` with no
            // query, so creates/updates show up immediately there.
            //
            // `query !== undefined` = list-fetch. Cache the received
            // ids under the query's stable signature, and let sibling
            // query caches stay untouched.
            let nextQueries: Record<string, readonly EntityKey[]>;
            let nextQueryMeta: Record<
                string,
                { totalItems: number; totalPages: number }
            >;

            if (action.query === undefined) {
                nextQueries = {};
                nextQueryMeta = {};
            } else {
                const qKey = queryKey(action.query);

                nextQueries = {
                    ...bag.queries,
                    [qKey]: Object.freeze(receivedIds),
                };
                nextQueryMeta = {
                    ...bag.queryMeta,
                    [qKey]: {
                        totalItems: action.totalItems ?? receivedIds.length,
                        totalPages: action.totalPages ?? 1,
                    },
                };
            }

            return {
                ...state,
                records: {
                    ...state.records,
                    [key]: {
                        items: nextItems,
                        aliases: nextAliases,
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

            const primaryKey = String(action.id);
            const nextItems = { ...bag.items };
            delete nextItems[primaryKey];

            // Drop any composite alias that pointed at the removed primary
            // so subsequent `getEntityRecord(kind, name, "<theme>//<slug>")`
            // calls return null rather than redirecting to an evicted item.
            const nextAliases = { ...bag.aliases };

            for (const [alias, target] of Object.entries(nextAliases)) {
                if (target === primaryKey) {
                    delete nextAliases[alias];
                }
            }

            // A delete invalidates every cached filter query (the removed
            // record might have filtered in or out of any of them) and
            // their totals (`totalItems` would otherwise return a stale
            // pre-delete count). Drop both.
            const nextEdits = { ...(state.edits[key] ?? {}) };
            delete nextEdits[primaryKey];

            return {
                ...state,
                records: {
                    ...state.records,
                    [key]: {
                        items: nextItems,
                        aliases: nextAliases,
                        queries: {},
                        queryMeta: {},
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
    const bag = state.records[entityKey(kind, name)];

    if (!bag) {
        return null;
    }

    const primary = String(id);
    const direct = bag.items[primary];

    if (direct !== undefined) {
        return direct;
    }

    // Fall through to the composite alias map so upstream-shape lookups
    // like `getEntityRecord('postType', 'wp_template_part', '<theme>//<slug>')`
    // resolve to the same record stored under the numeric primary key.
    const aliasTarget = bag.aliases[primary];

    if (aliasTarget !== undefined) {
        return bag.items[aliasTarget] ?? null;
    }

    return null;
}

/**
 * WordPress's `getRawEntityRecord` flattens any `{raw, rendered}`
 * shaped property to its `raw` string. Block-library code (e.g.
 * `core/block`'s `__experimentalLabel`, `core/template-part`'s
 * label) relies on this — it passes `entity.title` straight to
 * `decodeEntities()`, which coerces the structured object to
 * `[object Object]` if the shim returns the REST shape verbatim.
 * The flattener mirrors core-data's behaviour so synced patterns
 * and template parts surface their human-readable title in the
 * canvas + inspector.
 *
 * When `raw` is absent, falls back to `rendered` — our REST
 * resources currently emit only `{rendered}` for `title`, and
 * upstream flattens to whichever string-typed key is present.
 */
function flattenRawProperties(record: EntityRecord): EntityRecord {
    const out: EntityRecord = {};

    for (const [key, value] of Object.entries(record)) {
        if (value !== null && typeof value === 'object') {
            const shape = value as { raw?: unknown; rendered?: unknown };

            if (typeof shape.raw === 'string') {
                out[key] = shape.raw;
                continue;
            }

            if (typeof shape.rendered === 'string') {
                out[key] = shape.rendered;
                continue;
            }
        }

        out[key] = value;
    }

    return out;
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

    if (query === undefined) {
        return Object.values(bag.items);
    }

    const qKey = queryKey(query);
    const ids = bag.queries[qKey];

    if (ids) {
        const records: EntityRecord[] = [];

        for (const id of ids) {
            const record = bag.items[String(id)];

            if (record !== undefined) {
                records.push(record);
            }
        }

        return records;
    }

    // No cached filter query. For the "fetched everything" slot
    // (`null`/`{}`) fall back to the live items map so UIs pick up
    // cache-invalidating saves/deletes without needing a refetch.
    if (qKey === '') {
        return Object.values(bag.items);
    }

    return EMPTY_RECORDS;
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
    ): EntityRecord | null => {
        const record = selectEntityRecord(state, kind, name, id);

        return record === null ? null : flattenRawProperties(record);
    },

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

        if (!bag) {
            return 0;
        }

        const qKey = queryKey(query);
        const meta = bag.queryMeta[qKey];

        if (meta) {
            return meta.totalItems;
        }

        // Fall back to the live items map for the "everything" slot so
        // the count reflects saves/deletes after query caches are cleared.
        if (qKey === '') {
            return Object.keys(bag.items).length;
        }

        return 0;
    },

    getEntityRecordsTotalPages: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        query?: Record<string, unknown> | null,
    ): number => {
        const bag = state.records[entityKey(kind, name)];

        if (!bag) {
            return 0;
        }

        const qKey = queryKey(query);
        const meta = bag.queryMeta[qKey];

        if (meta) {
            return meta.totalPages;
        }

        if (qKey === '') {
            return Object.keys(bag.items).length > 0 ? 1 : 0;
        }

        return 0;
    },

    getEditedEntityRecord: (
        state: CoreDataState,
        kind: EntityKind,
        name: EntityName,
        id: EntityKey,
    ): EntityRecord => {
        const base = selectEntityRecord(state, kind, name, id);
        const edits = selectEditsForRecord(state, kind, name, id);

        // Match WP core's behavior: return an empty object — never
        // `null` — when nothing's been fetched yet. Consumers like
        // Gutenberg's `core/navigation` block (`use-navigation-menu.mjs`)
        // read fields like `o.status === "publish"` synchronously
        // during render, so a `null` return crashes the block before
        // the async fetch resolver can settle (Keystone #48). An
        // empty object lets the consumer's `=== "publish"` check
        // return `false` harmlessly, the resolution flag stays
        // `false`, the picker shows its loading state, and the fetch
        // repopulates state for the next render cycle.

        // Flatten `{raw, rendered}` shaped fields before merging
        // edits on top — same as `getRawEntityRecord`, so consumers
        // can read `entity.title` as a plain string.
        const flattenedBase = base === null ? {} : flattenRawProperties(base);

        return { ...flattenedBase, ...(edits ?? {}) };
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
    //
    // `hasFinishedResolution` / `hasStartedResolution` / `isResolving` were
    // manual `true`/`false` stubs in the M2 shim. G0 (#395) registers
    // resolvers for `getEntityRecord` / `getEntityRecords`, so
    // `@wordpress/data` now auto-supplies the resolution-tracking
    // selectors and the manual stubs would shadow them. Stubs removed
    // here; selectors without a registered resolver (canUser, etc.)
    // still fall through to wp-data's default `true`.
    getCurrentUser: (): EntityRecord | null => null,
    getUsers: (): readonly EntityRecord[] => EMPTY_RECORDS,
    getMedia: (): EntityRecord | null => null,
    getMediaItems: (): readonly EntityRecord[] => EMPTY_RECORDS,
    // Pre-G0 (#395) these returned `false`, which was harmless when no
    // entity reads ever resolved — the block-library code never reached
    // the `canUser` gates because the placeholder/spinner short-circuited
    // first. Now that the resolvers populate entity records, the
    // `wp_template_part` (and post-* / site-*) edit components reach
    // their `canUser('read', …)` / `canUser('update', …)` checks and
    // refuse to render anything when both come back `false`. Until G5
    // (#98) wires real permissions through `visual_editor.*` policies,
    // assume any user with editor access has full CRUD — the editor
    // surface is already gated by the package's auth middleware.
    canUser: (): boolean => true,
    canUserEditEntityRecord: (): boolean => true,
    getAutosaves: (): readonly EntityRecord[] => EMPTY_RECORDS,
    getAutosave: (): EntityRecord | null => null,
    getReferenceByDistinctEdits: (): readonly number[] => EMPTY_RECORDS,

    // E4 stubs — synthetic theme + post-type records returned to the
    // template-part / post-* / site-* edit components so their
    // `select(coreStore).getCurrentTheme()` / `getPostType()` calls
    // don't throw against the empty-state shim. The theme value
    // matches `config('artisanpack.visual-editor.global_styles.theme')`'s
    // default ('artisanpack-base'); Phase C of cms-framework will
    // replace these with a real backend that resolves the active
    // theme and full post-type schema.
    getCurrentTheme: (): EntityRecord => CURRENT_THEME_STUB,
    getThemeSupports: (): EntityRecord => EMPTY_THEME_SUPPORTS,
    getPostType: (): EntityRecord | null => null,

    // Upstream `@wordpress/core-data` registers this as a private
    // selector, so `core/navigation`'s edit reaches it through
    // `unlock( useSelect( coreStore ) )`. `@wordpress/data` locks the
    // store's selectors object with a merged public+private map; when
    // we never register any private selectors that merged map is just
    // the public selectors. Exposing it here lets the unlock call fall
    // through to a no-op without us depending on `@wordpress/private-apis`
    // (whose lock/unlock symbol identity is fragile across module
    // copies in a mixed `node_modules` tree). Returning `undefined`
    // routes the block to its uncontrolled-inner-blocks fallback path.
    getNavigationFallbackId: (): EntityKey | undefined => undefined,
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
     *
     * Composite (`<theme>//<slug>`) ids fall back to the index endpoint
     * filtered by `theme` + `slug`. Block-library looks template parts
     * up by the composite form even though the REST `show` route only
     * accepts the numeric primary key, so the shim bridges by listing
     * one and picking the single match.
     */
    fetchEntityRecord:
        (kind: EntityKind, name: EntityName, id: EntityKey) =>
        async ({ dispatch, select }: ThunkArgs): Promise<EntityRecord | null> => {
            const config = select.getEntityConfig(kind, name);

            if (!config) {
                return null;
            }

            const compositeParts =
                typeof id === 'string' ? splitCompositeId(id) : null;

            try {
                if (compositeParts !== null) {
                    const url = `${entityUrl(config)}${queryString({
                        theme: compositeParts.theme,
                        slug: compositeParts.slug,
                    })}`;
                    const body = (await restRequest(url, {
                        method: 'GET',
                        headers: buildHeaders(false),
                    })) as unknown;

                    const parsed = normalizeListResponse(body);
                    const record = parsed.records[0] ?? null;

                    if (record !== null) {
                        dispatch.receiveEntityRecords(kind, name, [record]);
                    }

                    return record;
                }

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
     *
     * The key field is pinned onto the payload explicitly: edits staged
     * before the base record was ever cached would otherwise produce a
     * key-less payload, which `saveEntityRecord` would mis-route to
     * `POST` (create) instead of `PUT` (update).
     */
    saveEditedEntityRecord:
        (kind: EntityKind, name: EntityName, id: EntityKey) =>
        async ({ dispatch, select }: ThunkArgs): Promise<EntityRecord | null> => {
            const edited = select.getEditedEntityRecord(kind, name, id);

            if (edited === null) {
                return null;
            }

            const config = select.getEntityConfig(kind, name);
            const payload: EntityRecord = config
                ? { ...edited, [config.key]: id }
                : edited;

            const saved = await actions.saveEntityRecord(kind, name, payload)({
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
// Resolvers
// ---------------------------------------------------------------------------

/**
 * Resolvers for `getEntityRecord` / `getEntityRecords` /
 * `getEditedEntityRecord`.
 *
 * Pre-G0 (#395) the shim cache only populated through manual
 * `dispatch.fetchEntityRecord(...)` / `fetchEntityRecords(...)` or
 * `receiveEntityRecords(...)` calls; reading via selectors alone
 * (e.g. block-library's `useEntityRecords`) returned the empty cache
 * forever. Wiring the resolvers here lets `@wordpress/data` fire the
 * existing fetch thunks the first time each `(kind, name, id|query)`
 * tuple is read, populate the cache, and surface real
 * `isResolving` / `hasFinishedResolution` flags.
 *
 * `getEditedEntityRecord` forwards to the same fetch — mirroring
 * upstream `@wordpress/core-data`'s `forwardResolver('getEntityRecord')`
 * — so the template-part `edit` component (which gates on
 * `hasFinishedResolution('getEditedEntityRecord', …)`) sees the
 * record settle and stops spinning.
 *
 * The thunks already swallow network failures (see their bodies), so
 * unregistered or not-yet-built endpoints degrade to empty state
 * without throwing — preserving the empty-state contract documented
 * in `docs/core-data-shim.md`.
 */
const resolvers = {
    getEntityRecord: actions.fetchEntityRecord,
    getEntityRecords: actions.fetchEntityRecords,
    getEditedEntityRecord: actions.fetchEntityRecord,
};

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
    resolvers: resolvers as unknown as Record<string, () => unknown>,
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

/**
 * Reads + edits a single property of an entity record. Mirrors upstream
 * `@wordpress/core-data`'s `useEntityProp` so block-library Edit
 * components (e.g. `core/post-title`) round-trip the prop through the
 * shim's edits bag.
 *
 * Returns `[ editedValue, setValue, fullValue ]` where:
 * - `editedValue` is the prop read from `getEditedEntityRecord`, which
 *   already flattens `{raw, rendered}` shapes to their `raw` string and
 *   layers any pending edits on top. Block edits (e.g. typing into a
 *   `core/post-title`'s `PlainText`) read this value.
 * - `setValue` dispatches `editEntityRecord(kind, name, id, { [prop]: value })`
 *   so subsequent reads see the new value through the edits bag.
 * - `fullValue` is the prop read from the original `getEntityRecord` —
 *   the unflattened shape, used by upstream code that needs the
 *   `{rendered}` form (e.g. the post-title block's read-only fallback
 *   path).
 *
 * The `id` argument is optional; when omitted the hook reads the
 * ambient entity from {@link EntityProvider} via {@link useEntityId}.
 * That mirrors core-data's behaviour for blocks rendered in a context
 * that already declared the current entity.
 *
 * Returns `[undefined, noop, undefined]` when any of `kind`, `name`,
 * `prop`, or the resolved id are missing — same guarded shape callers
 * already destructure with default values (`const [ rawTitle = '' ]`).
 */
export function useEntityProp<T = unknown>(
    kind?: EntityKind,
    name?: EntityName,
    prop?: string,
    id?: EntityKey | null,
): [T | undefined, (value: T) => void, T | undefined] {
    const ambientId = useEntityId();
    const resolvedId = id !== undefined && id !== null ? id : ambientId;

    const { editedValue, fullValue } = useSelect<{
        editedValue: T | undefined;
        fullValue: T | undefined;
    }>(
        (select) => {
            if (
                kind === undefined ||
                name === undefined ||
                prop === undefined ||
                resolvedId === undefined ||
                resolvedId === null
            ) {
                return { editedValue: undefined, fullValue: undefined };
            }

            const store = select(STORE_NAME) as
                | {
                      getEntityRecord?: (
                          kind: EntityKind,
                          name: EntityName,
                          id: EntityKey,
                      ) => EntityRecord | null;
                      getEditedEntityRecord?: (
                          kind: EntityKind,
                          name: EntityName,
                          id: EntityKey,
                      ) => EntityRecord | null;
                  }
                | undefined;

            const edited = store?.getEditedEntityRecord?.(kind, name, resolvedId) ?? null;
            const full = store?.getEntityRecord?.(kind, name, resolvedId) ?? null;

            return {
                editedValue: (edited?.[prop] as T | undefined) ?? undefined,
                fullValue: (full?.[prop] as T | undefined) ?? undefined,
            };
        },
        [kind, name, prop, resolvedId],
    );

    const dispatchTuple = useDispatch(STORE_NAME) as
        | {
              editEntityRecord?: (
                  kind: EntityKind,
                  name: EntityName,
                  id: EntityKey,
                  edits: EntityRecord,
              ) => void;
          }
        | undefined;

    const setter = useMemo(() => {
        if (
            kind === undefined ||
            name === undefined ||
            prop === undefined ||
            resolvedId === undefined ||
            resolvedId === null
        ) {
            return noopSetter as (value: T) => void;
        }

        return (value: T): void => {
            dispatchTuple?.editEntityRecord?.(kind, name, resolvedId, {
                [prop]: value,
            });
        };
    }, [dispatchTuple, kind, name, prop, resolvedId]);

    return [editedValue, setter, fullValue];
}

/**
 * Returns the cached entity record, triggering a single REST resolution
 * the first time each `(kind, name, id)` tuple is read.
 *
 * Pre-G0 (#395) this hook ran a manual `useEffect`/`useState` lifecycle
 * to fire `fetchEntityRecord` on cache miss because the shim store had
 * no resolvers wired. With resolvers now registered for
 * `getEntityRecord`, `@wordpress/data` handles the fetch + tracking
 * automatically; the hook just reads the selector and the auto-supplied
 * `isResolving` / `hasFinishedResolution` metadata.
 *
 * `hasResolved` falls back to `record !== null` so callers that
 * pre-populate the cache via `dispatch.receiveEntityRecords(...)`
 * (e.g. the patterns inserter priming D5 synced refs) report resolved
 * even though no resolver fired. Without this OR the inserter-primed
 * `core/block` mount would see `hasResolved: false` for one render and
 * flash the "deleted" placeholder.
 *
 * `editedRecord` and `hasEdits` reflect the store's edits bag — staged
 * `editEntityRecord(...)` writes appear immediately. The `edit` and
 * `save` callbacks remain no-ops since this hook is the read-side
 * surface; the visual editor saves through dedicated paths.
 */
export function useEntityRecord<T = unknown>(
    kind?: EntityKind,
    name?: EntityName,
    id?: EntityKey | null
): {
    record: T | null;
    editedRecord: T | null;
    hasEdits: boolean;
    hasResolved: boolean;
    isResolving: boolean;
    edit: typeof noopSetter;
    save: () => Promise<null>;
} {
    return useSelect(
        (select) => {
            if (
                kind === undefined ||
                name === undefined ||
                id === undefined ||
                id === null
            ) {
                return {
                    record: null as T | null,
                    editedRecord: null as T | null,
                    hasEdits: false,
                    hasResolved: true,
                    isResolving: false,
                    edit: noopSetter,
                    save: async () => null,
                };
            }

            const store = select(STORE_NAME) as
                | {
                      getEntityRecord?: (
                          kind: EntityKind,
                          name: EntityName,
                          id: EntityKey,
                      ) => EntityRecord | null;
                      getEditedEntityRecord?: (
                          kind: EntityKind,
                          name: EntityName,
                          id: EntityKey,
                      ) => EntityRecord | null;
                      hasEditsForEntityRecord?: (
                          kind: EntityKind,
                          name: EntityName,
                          id: EntityKey,
                      ) => boolean;
                      hasFinishedResolution?: (
                          selectorName: string,
                          args: readonly unknown[],
                      ) => boolean;
                      isResolving?: (
                          selectorName: string,
                          args: readonly unknown[],
                      ) => boolean;
                  }
                | undefined;

            const args: readonly unknown[] = [kind, name, id];
            const record = (store?.getEntityRecord?.(kind, name, id) ??
                null) as T | null;
            const editedRecord = (store?.getEditedEntityRecord?.(
                kind,
                name,
                id,
            ) ?? record) as T | null;
            const hasEdits =
                store?.hasEditsForEntityRecord?.(kind, name, id) ?? false;

            return {
                record,
                editedRecord,
                hasEdits,
                hasResolved:
                    record !== null ||
                    (store?.hasFinishedResolution?.('getEntityRecord', args) ??
                        false),
                isResolving:
                    store?.isResolving?.('getEntityRecord', args) ?? false,
                edit: noopSetter,
                save: async () => null,
            };
        },
        [kind, name, id],
    );
}

/**
 * Returns the cached list of entity records for `(kind, name, query)`,
 * triggering a single REST resolution the first time the tuple is read.
 *
 * Pre-G0 (#395) this hook returned `EMPTY_RECORDS` regardless of cache
 * or query. With the `getEntityRecords` resolver wired, the first read
 * dispatches `fetchEntityRecords`; subsequent reads hit the cache.
 * `isResolving` / `hasResolved` come from `@wordpress/data`'s
 * resolution tracking so consumers (e.g. the `core/template-part`
 * placeholder picker, archives inserters) can render real loading
 * states.
 *
 * Errors are still swallowed inside the fetch thunk — endpoints that
 * 404 or are not yet built degrade to an empty list, matching the
 * empty-state contract documented in `docs/core-data-shim.md`.
 */
export function useEntityRecords<T = unknown>(
    kind?: EntityKind,
    name?: EntityName,
    query?: Record<string, unknown> | null,
): {
    records: readonly T[];
    hasResolved: boolean;
    isResolving: boolean;
    status: 'IDLE' | 'RESOLVING' | 'SUCCESS';
    totalItems: number;
    totalPages: number;
} {
    return useSelect(
        (select) => {
            if (kind === undefined || name === undefined) {
                return {
                    records: EMPTY_RECORDS as readonly T[],
                    hasResolved: true,
                    isResolving: false,
                    status: 'IDLE' as const,
                    totalItems: 0,
                    totalPages: 0,
                };
            }

            const store = select(STORE_NAME) as
                | {
                      getEntityRecords?: (
                          kind: EntityKind,
                          name: EntityName,
                          query?: Record<string, unknown> | null,
                      ) => readonly EntityRecord[];
                      getEntityRecordsTotalItems?: (
                          kind: EntityKind,
                          name: EntityName,
                          query?: Record<string, unknown> | null,
                      ) => number;
                      getEntityRecordsTotalPages?: (
                          kind: EntityKind,
                          name: EntityName,
                          query?: Record<string, unknown> | null,
                      ) => number;
                      hasFinishedResolution?: (
                          selectorName: string,
                          args: readonly unknown[],
                      ) => boolean;
                      isResolving?: (
                          selectorName: string,
                          args: readonly unknown[],
                      ) => boolean;
                  }
                | undefined;

            const args: readonly unknown[] = [kind, name, query];
            const records = (store?.getEntityRecords?.(kind, name, query) ??
                EMPTY_RECORDS) as readonly T[];
            const hasResolved =
                store?.hasFinishedResolution?.('getEntityRecords', args) ??
                false;
            const isResolving =
                store?.isResolving?.('getEntityRecords', args) ?? false;

            return {
                records,
                hasResolved,
                isResolving,
                status: isResolving
                    ? ('RESOLVING' as const)
                    : hasResolved
                      ? ('SUCCESS' as const)
                      : ('IDLE' as const),
                totalItems:
                    store?.getEntityRecordsTotalItems?.(kind, name, query) ?? 0,
                totalPages:
                    store?.getEntityRecordsTotalPages?.(kind, name, query) ?? 0,
            };
        },
        [kind, name, query],
    );
}

/**
 * Resolves the inner block tree for an entity record.
 *
 * `core/block` (synced patterns) and `core/template-part` call this
 * with `(kind, name, { id })` to load saved inner blocks. Our REST
 * endpoints ship `content.blocks` already parsed server-side, but in
 * the upstream client-shape the block editor expects each block also
 * needs a stable `clientId` and an `isValid` flag — without them,
 * `useInnerBlocksProps` flags the tree as "unexpected or invalid
 * content" (#395 follow-up).
 *
 * `decorateServerBlocks` walks the server tree, fills in the missing
 * fields, and caches the result by `(kind, name, id, raw)` so render
 * loops don't reissue fresh `clientId`s on every selector read.
 *
 * Edits are still no-ops — saving a synced pattern goes through the
 * dedicated patterns canvas in the site editor (D5), not via this
 * inline path. When the post-V1 cms-framework backend lands the
 * setters can begin dispatching real `editEntityRecord` actions.
 */
export function useEntityBlockEditor(
    kind?: EntityKind,
    name?: EntityName,
    options?: { id?: EntityKey | null }
): [
    readonly unknown[],
    (blocks: readonly unknown[]) => void,
    (blocks: readonly unknown[]) => void,
] {
    const id = options?.id ?? null;

    const blocks = useSelect(
        (select) => {
            if (kind === undefined || name === undefined || id === null) {
                return EMPTY_RECORDS as readonly unknown[];
            }

            const store = select(STORE_NAME) as
                | {
                      getEntityRecord?: (
                          kind: EntityKind,
                          name: EntityName,
                          id: EntityKey
                      ) => EntityRecord | null;
                  }
                | undefined;

            const record = store?.getEntityRecord?.(kind, name, id);

            if (!record) {
                return EMPTY_RECORDS as readonly unknown[];
            }

            const content = (record as { content?: unknown }).content;

            if (content === null || content === undefined) {
                return EMPTY_RECORDS as readonly unknown[];
            }

            // `getEditedEntityRecord` runs `flattenRawProperties` over
            // the cached record, so a server envelope of
            // `{ content: { raw, blocks } }` collapses down to a plain
            // string here — same flattening WP applies to `title`.
            // For `wp_navigation` (Keystone #48) the only thing we get
            // back is the serialized block-comment markup; parse it
            // with Gutenberg's own parser so the decorated tree we
            // hand the editor matches what `parse()` would produce
            // from any other content path.
            if (typeof content === 'string') {
                const trimmed = content.trim();

                if (trimmed === '') {
                    return EMPTY_RECORDS as readonly unknown[];
                }

                const parsed = parseNavigationContent(trimmed) as readonly unknown[];

                if (parsed.length === 0) {
                    return EMPTY_RECORDS as readonly unknown[];
                }

                return getDecoratedBlocks(kind, name, id, parsed);
            }

            if (typeof content !== 'object') {
                return EMPTY_RECORDS as readonly unknown[];
            }

            const serverBlocks = (content as { blocks?: unknown }).blocks;

            // Prefer a non-empty server-shipped `blocks` array — it's
            // the lossless form. An EMPTY `blocks` array alongside a
            // populated `raw` string can happen when the projection
            // is still catching up (e.g. a `wp_navigation` envelope
            // that didn't run the items → blocks step but kept the
            // serialized markup); fall through to the raw parser in
            // that case so the canvas still renders the menu items.
            if (Array.isArray(serverBlocks) && serverBlocks.length > 0) {
                return getDecoratedBlocks(
                    kind,
                    name,
                    id,
                    serverBlocks as readonly unknown[],
                );
            }

            const raw = (content as { raw?: unknown }).raw;

            if (typeof raw === 'string' && raw.trim() !== '') {
                const parsed = parseNavigationContent(raw.trim()) as readonly unknown[];

                if (parsed.length > 0) {
                    return getDecoratedBlocks(kind, name, id, parsed);
                }
            }

            return EMPTY_RECORDS as readonly unknown[];
        },
        [kind, name, id]
    );

    return [blocks, noopSetter, noopSetter];
}

interface ServerBlock {
    name: string;
    attributes?: Record<string, unknown>;
    innerBlocks?: readonly ServerBlock[];
}

interface DecoratedBlock {
    name: string;
    clientId: string;
    isValid: true;
    attributes: Record<string, unknown>;
    innerBlocks: readonly DecoratedBlock[];
}

const decoratedBlocksCache = new Map<
    string,
    { source: readonly unknown[]; decorated: readonly DecoratedBlock[] }
>();

/**
 * Returns the server's pre-parsed block tree decorated with the
 * `clientId` / `isValid` fields the block editor needs, memoized
 * per `(kind, name, id)` so successive selector reads return the
 * same array reference and the editor doesn't see "new" blocks
 * on every render.
 */
function getDecoratedBlocks(
    kind: EntityKind,
    name: EntityName,
    id: EntityKey,
    serverBlocks: readonly unknown[],
): readonly DecoratedBlock[] {
    const cacheKey = `${kind}|${name}|${id}`;
    const cached = decoratedBlocksCache.get(cacheKey);

    if (cached && cached.source === serverBlocks) {
        return cached.decorated;
    }

    const decorated = decorateBlockList(serverBlocks);
    decoratedBlocksCache.set(cacheKey, { source: serverBlocks, decorated });

    return decorated;
}

function decorateBlockList(
    blocks: readonly unknown[],
): readonly DecoratedBlock[] {
    const out: DecoratedBlock[] = [];

    for (const block of blocks) {
        const decorated = decorateBlock(block);

        if (decorated !== null) {
            out.push(decorated);
        }
    }

    return out;
}

function decorateBlock(block: unknown): DecoratedBlock | null {
    if (
        block === null ||
        typeof block !== 'object' ||
        typeof (block as ServerBlock).name !== 'string'
    ) {
        return null;
    }

    const server = block as ServerBlock;
    const innerBlocks = Array.isArray(server.innerBlocks)
        ? decorateBlockList(server.innerBlocks)
        : EMPTY_RECORDS;

    return {
        name: server.name,
        clientId: createClientId(),
        isValid: true,
        attributes: server.attributes ?? {},
        innerBlocks,
    };
}

let clientIdCounter = 0;

function createClientId(): string {
    if (
        typeof globalThis.crypto !== 'undefined' &&
        typeof globalThis.crypto.randomUUID === 'function'
    ) {
        return globalThis.crypto.randomUUID();
    }

    clientIdCounter += 1;

    return `shim-${Date.now().toString(36)}-${clientIdCounter.toString(36)}`;
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
