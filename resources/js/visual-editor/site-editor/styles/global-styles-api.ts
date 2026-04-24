/**
 * Global-styles REST client.
 *
 * The site-editor talks to the C3 REST surface exposed under
 * `/visual-editor/api/global-styles` (see
 * `src/Http/Controllers/GlobalStylesController.php`). This client wraps the
 * four endpoints — lookup / base / show / update — behind typed helpers so
 * the D3 styles UI doesn't have to rebuild URLs, CSRF handling, or error
 * shapes ad hoc.
 *
 * Kept alongside the `site-editor/api-client.ts` pattern but in its own
 * file because global-styles uses a singleton record shape (no pagination,
 * one-record-per-theme) rather than the post-type envelope templates and
 * template parts ride on.
 */
import {
    SiteEditorApiError,
    type SiteEditorApiConfig,
    type ValidationErrors,
} from '../api-client';

export interface GlobalStylesRecord {
    id: number;
    version: number;
    settings: Record<string, unknown>;
    styles: Record<string, unknown>;
}

export type GlobalStylesBase = Pick<
    GlobalStylesRecord,
    'version' | 'settings' | 'styles'
>;

export interface GlobalStylesUpdatePayload {
    version: number;
    settings: Record<string, unknown>;
    styles: Record<string, unknown>;
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

    if (csrf) {
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
        const baseMessage = `Global-styles request failed with status ${response.status}`;
        const message =
            body !== null &&
            typeof body === 'object' &&
            'message' in body &&
            typeof (body as { message: unknown }).message === 'string'
                ? (body as { message: string }).message || baseMessage
                : baseMessage;

        // Reuse the shell's `SiteEditorApiError` so 422 handling flows
        // through the same extraction path the D2 editor already uses —
        // the inspector renders field-level errors the same way.
        throw new SiteEditorApiError(message, response.status, body);
    }

    return body;
}

function buildUrl(
    config: SiteEditorApiConfig,
    suffix: string | number | null = null
): string {
    const base = config.apiBase.replace(/\/+$/, '');

    if (suffix === null) {
        return `${base}/global-styles`;
    }

    return `${base}/global-styles/${encodeURIComponent(String(suffix))}`;
}

function normalizeError(
    error: unknown,
    fallback: string
): SiteEditorApiError {
    if (error instanceof SiteEditorApiError) {
        return error;
    }

    const message =
        error instanceof Error && error.message ? error.message : fallback;

    return new SiteEditorApiError(message, 0, error);
}

/**
 * Returns the active-theme's singleton id. The D3 bootstrap dispatches
 * this to `receiveCurrentGlobalStylesId` so downstream selectors
 * (`__experimentalGetCurrentGlobalStylesId`) resolve without a network
 * call.
 */
export async function lookupGlobalStyles(
    config: SiteEditorApiConfig
): Promise<{ id: number }> {
    try {
        const response = await fetch(buildUrl(config, 'lookup'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as { id: number };
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to look up global styles.');
    }
}

/**
 * Returns the theme's unmodified theme.json payload — the baseline the
 * inspector panels diff against when highlighting customized values.
 */
export async function fetchGlobalStylesBase(
    config: SiteEditorApiConfig
): Promise<GlobalStylesBase> {
    try {
        const response = await fetch(buildUrl(config, 'base'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as GlobalStylesBase;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load global-styles base.');
    }
}

export async function fetchGlobalStyles(
    config: SiteEditorApiConfig,
    id: number | string
): Promise<GlobalStylesRecord> {
    try {
        const response = await fetch(buildUrl(config, id), {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as GlobalStylesRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load global styles.');
    }
}

export async function updateGlobalStyles(
    config: SiteEditorApiConfig,
    id: number | string,
    payload: GlobalStylesUpdatePayload
): Promise<GlobalStylesRecord> {
    try {
        const response = await fetch(buildUrl(config, id), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as GlobalStylesRecord;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to save global styles.');
    }
}

export { extractValidationErrors };
