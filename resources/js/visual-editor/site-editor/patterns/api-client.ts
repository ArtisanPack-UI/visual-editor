/**
 * Patterns REST client.
 *
 * Targets the C5 surface mounted under `/visual-editor/api/patterns`.
 * Templates and parts share the generic site-editor `api-client.ts`, but
 * patterns ship a few fields that don't fit the `EntityKind` template/part
 * dichotomy (`synced`, `categories`, no `area`/`source`/`origin`) so the
 * D5 work uses its own typed client. Same CSRF + same `{ data, meta }`
 * envelope shape — only the record type and create/update payloads
 * diverge.
 */

import {
    SiteEditorApiError,
    type PaginatedResponse,
    type SiteEditorApiConfig,
    type ValidationErrors,
} from '../api-client';

export interface PatternTitle {
    rendered: string;
    /**
     * Human-readable user-editable form of the title. The C5 backend
     * ships both `raw` and `rendered` because `core/block` (the
     * synced-pattern reference) reads `raw` first and falls back to
     * the bare `title` object when `raw` is missing — surfacing
     * "[object Object]" in the canvas.
     */
    raw?: string;
}

export interface PatternContent {
    raw: string;
    blocks: readonly unknown[];
}

export type PatternStatus = 'publish' | 'draft' | 'private';

export interface PatternRecord {
    id: number;
    slug: string;
    title: PatternTitle;
    content: PatternContent;
    synced: boolean;
    categories: readonly string[];
    status: PatternStatus;
    type: 'wp_block';
}

export interface PatternListParams {
    perPage?: number;
    page?: number;
    /** Filter by sync status. Omit to return both. */
    synced?: boolean;
    /** OR-semantics — pass several to widen, omit to return all. */
    categories?: readonly string[];
    slug?: string;
    status?: PatternStatus;
}

export interface PatternCreatePayload {
    slug: string;
    title?: string;
    synced: boolean;
    content: PatternContent;
    categories?: readonly string[];
    status?: PatternStatus;
}

export interface PatternUpdatePayload {
    slug?: string;
    title?: string;
    content?: PatternContent;
    /**
     * Sync status is *immutable* after creation per design brief §3.6 —
     * the conversion flow creates a new unsynced copy rather than
     * toggling the flag in place. Kept on the type so the conversion
     * helper can flip it on the server-side echo, but UI code should
     * never send it.
     */
    synced?: boolean;
    categories?: readonly string[];
    status?: PatternStatus;
}

const PATTERNS_PATH = 'patterns';

function patternsBaseUrl(config: SiteEditorApiConfig): string {
    return `${config.apiBase.replace(/\/+$/, '')}/${PATTERNS_PATH}`;
}

function buildUrl(
    config: SiteEditorApiConfig,
    idOrSuffix: string | number | null = null,
    query: Record<string, string | number | undefined> = {}
): string {
    let url = patternsBaseUrl(config);

    if (idOrSuffix !== null) {
        url += `/${encodeURIComponent(String(idOrSuffix))}`;
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

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]'
    );

    return meta?.content?.trim() || null;
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

async function requireOk(response: Response): Promise<unknown> {
    const body = await parseBody(response);

    if (!response.ok) {
        const baseMessage = `Pattern request failed with status ${response.status}`;
        const message =
            body !== null &&
            typeof body === 'object' &&
            'message' in body &&
            typeof (body as { message: unknown }).message === 'string'
                ? ((body as { message: string }).message || baseMessage)
                : baseMessage;

        throw new SiteEditorApiError(message, response.status, body);
    }

    return body;
}

function normalizeError(error: unknown, fallback: string): SiteEditorApiError {
    if (error instanceof SiteEditorApiError) {
        return error;
    }

    const message =
        error instanceof Error && error.message ? error.message : fallback;

    return new SiteEditorApiError(message, 0, error);
}

function mutatingHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const csrf = readCsrfToken();

    if (csrf) {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    return headers;
}

const READ_HEADERS: Readonly<Record<string, string>> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

/**
 * Lists patterns. The C5 endpoint accepts a single `?categories=` value
 * or a `?categories[]=` array — we always emit the bracket form so the
 * client doesn't have to special-case the single-slug path.
 */
export async function listPatterns(
    config: SiteEditorApiConfig,
    params: PatternListParams = {}
): Promise<PaginatedResponse<PatternRecord>> {
    const url = buildUrl(config, null, {
        per_page: params.perPage,
        page: params.page,
        synced:
            params.synced === undefined
                ? undefined
                : params.synced
                  ? '1'
                  : '0',
        slug: params.slug,
        status: params.status,
    });

    let withCategories = url;

    if (params.categories !== undefined && params.categories.length > 0) {
        const search = new URLSearchParams();

        for (const slug of params.categories) {
            search.append('categories[]', slug);
        }

        withCategories =
            url + (url.includes('?') ? '&' : '?') + search.toString();
    }

    try {
        const response = await fetch(withCategories, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as PaginatedResponse<PatternRecord>;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load patterns.');
    }
}

export async function fetchPattern(
    config: SiteEditorApiConfig,
    id: number | string
): Promise<PatternRecord> {
    try {
        const response = await fetch(buildUrl(config, id), {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as PatternRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load pattern.');
    }
}

export async function createPattern(
    config: SiteEditorApiConfig,
    payload: PatternCreatePayload
): Promise<PatternRecord> {
    try {
        const response = await fetch(buildUrl(config), {
            method: 'POST',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as PatternRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to create pattern.');
    }
}

export async function updatePattern(
    config: SiteEditorApiConfig,
    id: number | string,
    payload: PatternUpdatePayload
): Promise<PatternRecord> {
    try {
        const response = await fetch(buildUrl(config, id), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as PatternRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to save pattern.');
    }
}

export async function deletePattern(
    config: SiteEditorApiConfig,
    id: number | string
): Promise<void> {
    try {
        const response = await fetch(buildUrl(config, id), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
        });

        await requireOk(response);
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to delete pattern.');
    }
}

export type { ValidationErrors };
export { SiteEditorApiError };
