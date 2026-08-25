/**
 * Font Library modal store (#635).
 *
 * A pure `useReducer` state container for the Font Library modal. Everything
 * with non-trivial logic — the installed list, per-provider catalog paging and
 * de-duplicated appends, the bulk-uninstall selection set, install progress,
 * and the read-only gate — lives here so it can be unit-tested without
 * rendering React. Transient view-only concerns (the editable sample text, the
 * open weight-picker) stay as component state.
 *
 * The reducer is deliberately data-only: it never performs I/O. The modal
 * dispatches lifecycle actions around each `api-client` call.
 *
 * @since 1.7.0
 */

import type { CatalogFamily, FontSource, InstalledFont } from './api-client';

export type LoadStatus = 'idle' | 'loading' | 'ready' | 'error';

/** The synthetic tab id for the installed-fonts tab. */
export const INSTALLED_TAB = 'installed';

/** The synthetic tab id for the custom-upload tab. */
export const UPLOAD_TAB = 'upload';

/** Per-provider catalog browsing state. */
export interface CatalogState {
    readonly query: string;
    readonly page: number;
    readonly families: readonly CatalogFamily[];
    readonly hasMore: boolean;
    readonly status: LoadStatus;
    readonly error: string | null;
    /**
     * The id of the most recently issued request for this provider. A
     * success/failure carrying an older id lost a race (fast paging or
     * typing) and is ignored so a delayed response can't clobber newer state.
     */
    readonly requestId: number;
}

/** Install-flow progress for the currently installing family. */
export interface InstallState {
    readonly status: 'idle' | 'installing' | 'success' | 'error';
    readonly slug: string | null;
    readonly family: string | null;
    readonly error: string | null;
}

export interface FontLibraryState {
    readonly activeTab: string;
    readonly readOnly: boolean;
    readonly installed: readonly InstalledFont[];
    readonly installedStatus: LoadStatus;
    readonly installedError: string | null;
    readonly sources: readonly FontSource[];
    readonly sourcesStatus: LoadStatus;
    readonly sourcesError: string | null;
    readonly catalogs: Readonly<Record<string, CatalogState>>;
    readonly selectedForRemoval: readonly number[];
    readonly install: InstallState;
}

export const emptyCatalog: CatalogState = {
    query: '',
    page: 1,
    families: [],
    hasMore: false,
    status: 'idle',
    error: null,
    requestId: 0,
};

export const initialState: FontLibraryState = {
    activeTab: INSTALLED_TAB,
    // Start read-only until the installed-list response confirms the
    // `manage_fonts` capability, so mutating controls never flash enabled.
    readOnly: true,
    installed: [],
    installedStatus: 'idle',
    installedError: null,
    sources: [],
    sourcesStatus: 'idle',
    sourcesError: null,
    catalogs: {},
    selectedForRemoval: [],
    install: { status: 'idle', slug: null, family: null, error: null },
};

export type FontLibraryAction =
    | { type: 'SET_ACTIVE_TAB'; tab: string }
    | { type: 'INSTALLED_LOADING' }
    | { type: 'INSTALLED_LOADED'; fonts: readonly InstalledFont[]; readOnly: boolean }
    | { type: 'INSTALLED_ERROR'; message: string }
    | { type: 'SOURCES_LOADING' }
    | { type: 'SOURCES_LOADED'; sources: readonly FontSource[]; readOnly: boolean }
    | { type: 'SOURCES_ERROR'; message: string }
    | { type: 'CATALOG_REQUEST'; provider: string; query: string; page: number; requestId: number }
    | {
          type: 'CATALOG_SUCCESS';
          provider: string;
          page: number;
          families: readonly CatalogFamily[];
          hasMore: boolean;
          requestId: number;
      }
    | { type: 'CATALOG_FAILURE'; provider: string; message: string; requestId: number }
    | { type: 'TOGGLE_SELECT'; id: number }
    | { type: 'SELECT_ALL'; ids: readonly number[] }
    | { type: 'CLEAR_SELECTION' }
    | { type: 'INSTALL_START'; slug: string; family: string }
    | { type: 'INSTALL_SUCCESS'; font: InstalledFont }
    | { type: 'INSTALL_ERROR'; message: string }
    | { type: 'INSTALL_RESET' }
    | { type: 'FONTS_REMOVED'; ids: readonly number[] }
    | { type: 'SET_READ_ONLY'; readOnly: boolean };

/**
 * The catalog state for a provider, or the empty default when it has not been
 * requested yet.
 *
 * @since 1.7.0
 */
function catalogFor(state: FontLibraryState, provider: string): CatalogState {
    return state.catalogs[provider] ?? emptyCatalog;
}

/**
 * Merge a freshly loaded catalog page. Page 1 (a fresh browse or search)
 * replaces the list; later pages append, skipping any family slug already
 * present so a provider that repeats an entry across page boundaries can't
 * produce duplicate React keys.
 *
 * @since 1.7.0
 */
function mergeFamilies(
    existing: readonly CatalogFamily[],
    incoming: readonly CatalogFamily[],
    page: number
): readonly CatalogFamily[] {
    if (page <= 1) {
        return incoming;
    }

    const seen = new Set(existing.map((family) => family.slug));
    const appended = incoming.filter((family) => !seen.has(family.slug));

    return [...existing, ...appended];
}

/**
 * The Font Library modal's reducer. Pure and I/O-free: the modal dispatches
 * lifecycle actions around each `api-client` call and this folds them into the
 * next state.
 *
 * @since 1.7.0
 */
export function fontLibraryReducer(
    state: FontLibraryState,
    action: FontLibraryAction
): FontLibraryState {
    switch (action.type) {
        case 'SET_ACTIVE_TAB':
            return { ...state, activeTab: action.tab };

        case 'INSTALLED_LOADING':
            return { ...state, installedStatus: 'loading', installedError: null };

        case 'INSTALLED_LOADED':
            return {
                ...state,
                installed: action.fonts,
                installedStatus: 'ready',
                installedError: null,
                readOnly: action.readOnly,
                // A read-only session can't act on a selection.
                selectedForRemoval: action.readOnly ? [] : state.selectedForRemoval,
            };

        case 'INSTALLED_ERROR':
            return { ...state, installedStatus: 'error', installedError: action.message };

        case 'SOURCES_LOADING':
            return { ...state, sourcesStatus: 'loading', sourcesError: null };

        case 'SOURCES_LOADED':
            return {
                ...state,
                sources: action.sources,
                sourcesStatus: 'ready',
                sourcesError: null,
                readOnly: action.readOnly,
            };

        case 'SOURCES_ERROR':
            return { ...state, sourcesStatus: 'error', sourcesError: action.message };

        case 'CATALOG_REQUEST': {
            const previous = catalogFor(state, action.provider);

            return {
                ...state,
                catalogs: {
                    ...state.catalogs,
                    [action.provider]: {
                        ...previous,
                        query: action.query,
                        page: action.page,
                        status: 'loading',
                        error: null,
                        requestId: action.requestId,
                        // Clear the list for a fresh browse/search so stale
                        // results don't linger under the spinner.
                        families: action.page <= 1 ? [] : previous.families,
                    },
                },
            };
        }

        case 'CATALOG_SUCCESS': {
            const previous = catalogFor(state, action.provider);

            // A response from a superseded request (the user paged or typed
            // again before it landed) must not overwrite the current results.
            if (action.requestId !== previous.requestId) {
                return state;
            }

            return {
                ...state,
                catalogs: {
                    ...state.catalogs,
                    [action.provider]: {
                        ...previous,
                        page: action.page,
                        hasMore: action.hasMore,
                        status: 'ready',
                        error: null,
                        families: mergeFamilies(previous.families, action.families, action.page),
                    },
                },
            };
        }

        case 'CATALOG_FAILURE': {
            const previous = catalogFor(state, action.provider);

            if (action.requestId !== previous.requestId) {
                return state;
            }

            return {
                ...state,
                catalogs: {
                    ...state.catalogs,
                    [action.provider]: {
                        ...previous,
                        status: 'error',
                        error: action.message,
                    },
                },
            };
        }

        case 'TOGGLE_SELECT': {
            if (state.readOnly) {
                return state;
            }

            const selected = state.selectedForRemoval.includes(action.id)
                ? state.selectedForRemoval.filter((id) => id !== action.id)
                : [...state.selectedForRemoval, action.id];

            return { ...state, selectedForRemoval: selected };
        }

        case 'SELECT_ALL':
            return state.readOnly ? state : { ...state, selectedForRemoval: [...action.ids] };

        case 'CLEAR_SELECTION':
            return { ...state, selectedForRemoval: [] };

        case 'INSTALL_START':
            return {
                ...state,
                install: {
                    status: 'installing',
                    slug: action.slug,
                    family: action.family,
                    error: null,
                },
            };

        case 'INSTALL_SUCCESS': {
            // Replace an existing entry for the same font id, or prepend the
            // new one, keeping the list sorted by family for stable display.
            const withoutDupe = state.installed.filter((font) => font.id !== action.font.id);
            const installed = [...withoutDupe, action.font].sort((a, b) =>
                a.family.localeCompare(b.family)
            );

            return {
                ...state,
                installed,
                install: {
                    status: 'success',
                    slug: action.font.slug,
                    family: action.font.family,
                    error: null,
                },
            };
        }

        case 'INSTALL_ERROR':
            return {
                ...state,
                install: { ...state.install, status: 'error', error: action.message },
            };

        case 'INSTALL_RESET':
            return { ...state, install: initialState.install };

        case 'FONTS_REMOVED': {
            const removed = new Set(action.ids);

            return {
                ...state,
                installed: state.installed.filter((font) => !removed.has(font.id)),
                selectedForRemoval: state.selectedForRemoval.filter((id) => !removed.has(id)),
            };
        }

        case 'SET_READ_ONLY':
            return {
                ...state,
                readOnly: action.readOnly,
                selectedForRemoval: action.readOnly ? [] : state.selectedForRemoval,
            };

        default:
            return state;
    }
}

/**
 * Whether a catalog family is already installed for a given provider. Used to
 * badge catalog rows and disable their install control.
 *
 * @since 1.7.0
 */
export function isInstalled(
    state: FontLibraryState,
    provider: string,
    slug: string
): boolean {
    return state.installed.some(
        (font) => font.provider === provider && font.slug === slug
    );
}
