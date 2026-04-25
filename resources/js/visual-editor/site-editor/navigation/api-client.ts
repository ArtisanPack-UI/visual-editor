/**
 * Navigation REST client.
 *
 * Wraps the C4 `wp_navigation` surface and the D4-only menu-locations /
 * entity-search endpoints. Kept separate from the templates / parts
 * client (`../api-client.ts`) because the response shapes are different
 * — navigation rows carry a `location` field, the locations endpoint
 * returns its own `{ data: [{ slug, label, menu, is_fallback }] }`
 * envelope, and search rows are unrelated to the C1/C2 entity types.
 *
 * Reuses the same CSRF + error-normalization conventions as the parent
 * client so a host app's middleware setup applies uniformly.
 */

import {
    SiteEditorApiError,
    type SiteEditorApiConfig,
    type ValidationErrors,
} from '../api-client';

export interface NavigationRecord {
    id: number;
    slug: string;
    title: { rendered: string };
    content: { raw: string; blocks: readonly unknown[] };
    status: string;
    menu_order: number;
    /** `null` means no UI-driven location assignment. */
    location: string | null;
    type: 'wp_navigation';
}

export interface NavigationListMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface NavigationListResponse {
    data: readonly NavigationRecord[];
    meta: NavigationListMeta;
}

export interface MenuLocation {
    slug: string;
    label: string;
    /**
     * The menu currently resolved for this slot. `null` when nothing is
     * configured AND no published menus exist to fall back to.
     */
    menu: { id: number; slug: string; title: string } | null;
    /**
     * `true` when the resolved menu is a config / published-fallback —
     * not a direct DB assignment from the editor. The locations panel
     * uses this to render the "falling back to X" hint.
     */
    is_fallback: boolean;
}

export interface SearchResult {
    type: string;
    id: number | string;
    title: string;
    url: string | null;
}

interface NavigationCreatePayload {
    slug: string;
    title?: string;
    status?: string;
    menu_order?: number;
    content?: { raw: string; blocks: readonly unknown[] };
    location?: string | null;
}

export interface NavigationUpdatePayload {
    slug?: string;
    title?: string;
    status?: string;
    menu_order?: number;
    content?: { raw: string; blocks: readonly unknown[] };
    location?: string | null;
}

const READ_HEADERS: Readonly<Record<string, string>> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]'
    );

    return meta?.content?.trim() || null;
}

function mutatingHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const csrf = readCsrfToken();

    if (csrf !== null) {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    return headers;
}

async function parseBody(response: Response): Promise<unknown> {
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

function extractValidationErrors(body: unknown): ValidationErrors | null {
    if (
        body === null ||
        typeof body !== 'object' ||
        !('errors' in body) ||
        typeof (body as { errors: unknown }).errors !== 'object' ||
        (body as { errors: unknown }).errors === null
    ) {
        return null;
    }

    const raw = (body as { errors: Record<string, unknown> }).errors;
    const result: ValidationErrors = {};

    for (const [field, value] of Object.entries(raw)) {
        if (Array.isArray(value)) {
            result[field] = value.filter(
                (entry): entry is string => typeof entry === 'string'
            );
        }
    }

    return result;
}

async function requireOk(response: Response): Promise<unknown> {
    const body = await parseBody(response);

    if (!response.ok) {
        const baseMessage = `Navigation request failed with status ${response.status}`;
        const message =
            body !== null &&
            typeof body === 'object' &&
            'message' in body &&
            typeof (body as { message: unknown }).message === 'string'
                ? (body as { message: string }).message || baseMessage
                : baseMessage;

        throw new SiteEditorApiError(message, response.status, body);
    }

    return body;
}

function normalizeError(
    error: unknown,
    fallbackMessage: string
): SiteEditorApiError {
    if (error instanceof SiteEditorApiError) {
        return error;
    }

    const message =
        error instanceof Error && error.message
            ? error.message
            : fallbackMessage;

    return new SiteEditorApiError(message, 0, error);
}

function navigationUrl(
    config: SiteEditorApiConfig,
    suffix: string | number | null,
    query: Record<string, string | number | undefined> = {}
): string {
    const base = config.apiBase.replace(/\/+$/, '');
    let url = `${base}/navigation`;

    if (suffix !== null) {
        url += `/${encodeURIComponent(String(suffix))}`;
    }

    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(query)) {
        if (value === undefined || value === '') {
            continue;
        }

        params.set(key, String(value));
    }

    const qs = params.toString();

    return qs === '' ? url : `${url}?${qs}`;
}

export interface ListNavigationParams {
    perPage?: number;
    page?: number;
    status?: string;
}

export async function listNavigations(
    config: SiteEditorApiConfig,
    params: ListNavigationParams = {}
): Promise<NavigationListResponse> {
    try {
        const response = await fetch(
            navigationUrl(config, null, {
                per_page: params.perPage,
                page: params.page,
                status: params.status,
            }),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: READ_HEADERS,
            }
        );

        return (await requireOk(response)) as NavigationListResponse;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load navigation list.');
    }
}

export async function fetchNavigation(
    config: SiteEditorApiConfig,
    id: number | string
): Promise<NavigationRecord> {
    try {
        const response = await fetch(navigationUrl(config, id), {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as NavigationRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load navigation.');
    }
}

export async function createNavigation(
    config: SiteEditorApiConfig,
    payload: NavigationCreatePayload
): Promise<NavigationRecord> {
    try {
        const response = await fetch(navigationUrl(config, null), {
            method: 'POST',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as NavigationRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to create navigation.');
    }
}

export async function updateNavigation(
    config: SiteEditorApiConfig,
    id: number | string,
    payload: NavigationUpdatePayload
): Promise<NavigationRecord> {
    try {
        const response = await fetch(navigationUrl(config, id), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as NavigationRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to save navigation.');
    }
}

export async function fetchMenuLocations(
    config: SiteEditorApiConfig
): Promise<readonly MenuLocation[]> {
    try {
        const base = config.apiBase.replace(/\/+$/, '');
        const response = await fetch(`${base}/menu-locations`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        const body = (await requireOk(response)) as { data?: unknown };

        if (!Array.isArray(body.data)) {
            return [];
        }

        return body.data.filter(isMenuLocation);
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load menu locations.');
    }
}

function isMenuLocation(value: unknown): value is MenuLocation {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    const row = value as Record<string, unknown>;

    if (
        typeof row.slug !== 'string' ||
        typeof row.label !== 'string' ||
        typeof row.is_fallback !== 'boolean'
    ) {
        return false;
    }

    // `menu` is `null` when nothing is currently resolved for the
    // location, otherwise the matching record envelope. Reject
    // anything else (string, number, malformed object) so a stray
    // payload can't slip past the predicate's narrowing. `undefined`
    // is allowed too so older API builds that omit the key entirely
    // still pass — same lenient handling we use for `url` in
    // `isSearchResult`.
    if (row.menu === null || row.menu === undefined) {
        return true;
    }

    if (typeof row.menu !== 'object') {
        return false;
    }

    const menu = row.menu as Record<string, unknown>;

    return (
        typeof menu.id === 'number' &&
        typeof menu.slug === 'string' &&
        typeof menu.title === 'string'
    );
}

export interface SearchEntitiesParams {
    type: string;
    q: string;
}

export async function searchEntities(
    config: SiteEditorApiConfig,
    params: SearchEntitiesParams
): Promise<readonly SearchResult[]> {
    try {
        const base = config.apiBase.replace(/\/+$/, '');
        const search = new URLSearchParams({
            type: params.type,
            q: params.q,
        });
        const response = await fetch(`${base}/search?${search.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        const body = (await requireOk(response)) as { data?: unknown };

        if (!Array.isArray(body.data)) {
            return [];
        }

        return body.data.filter(isSearchResult);
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to search entities.');
    }
}

function isSearchResult(value: unknown): value is SearchResult {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    const row = value as Record<string, unknown>;

    if (
        typeof row.type !== 'string' ||
        (typeof row.id !== 'number' && typeof row.id !== 'string') ||
        typeof row.title !== 'string'
    ) {
        return false;
    }

    // `url` is `string | null` per `SearchResult` — anything else is
    // a malformed payload. Allow `undefined` too so older API
    // builds that omit the key entirely still pass.
    return (
        row.url === null ||
        row.url === undefined ||
        typeof row.url === 'string'
    );
}

export { extractValidationErrors };
