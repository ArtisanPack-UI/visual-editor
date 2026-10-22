/**
 * REST client for the visual editor's resource content endpoints.
 *
 * Thin wrapper over `fetch` that targets
 * `{apiBase}/{resource}/{id}/content` and automatically sends the session
 * CSRF token Laravel expects on mutating requests. Errors are surfaced as
 * typed `ApiError` instances so the persistence loop can distinguish
 * auth/validation/network failures.
 */

import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

export interface ContentResponse {
    id: number | string;
    resource: string;
    blocks: readonly unknown[];
    updated_at: string | null;
}

export class ApiError extends Error {
    public readonly status: number;

    public readonly body: unknown;

    public constructor(message: string, status: number, body: unknown) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }
}

export interface ApiClientConfig {
    apiBase: string;
    resource: string;
    id: string;
}

/**
 * Which metadata endpoint an entity's edits flush to: the G3 entity
 * adapter (`{resource}/{id}`, posts/pages) or the generic title/excerpt
 * endpoint (`{resource}/{id}/entry`, custom content types).
 */
export type EntityEndpoint = 'entity' | 'entry';

function contentUrl(config: ApiClientConfig): string {
    const base = config.apiBase.replace(/\/$/, '');

    return `${base}/${encodeURIComponent(config.resource)}/${encodeURIComponent(
        config.id
    )}/content`;
}

function entityRecordUrl(config: ApiClientConfig): string {
    const base = config.apiBase.replace(/\/$/, '');

    return `${base}/${encodeURIComponent(config.resource)}/${encodeURIComponent(
        config.id
    )}`;
}

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

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
        throw new ApiError(
            sprintf(
                /* translators: %d: HTTP status code. */
                __('Visual editor request failed with status %d', TEXT_DOMAIN),
                response.status
            ),
            response.status,
            body
        );
    }

    return body;
}

function normalizeError(error: unknown, fallbackMessage: string): ApiError {
    if (error instanceof ApiError) {
        return error;
    }

    const message =
        error instanceof Error && error.message ? error.message : fallbackMessage;

    return new ApiError(message, 0, error);
}

export async function fetchContent(
    config: ApiClientConfig
): Promise<ContentResponse> {
    try {
        const response = await fetch(contentUrl(config), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return (await requireOk(response)) as ContentResponse;
    } catch (error: unknown) {
        throw normalizeError(error, __('Failed to load content.', TEXT_DOMAIN));
    }
}

export async function saveContent(
    config: ApiClientConfig,
    blocks: readonly unknown[]
): Promise<ContentResponse> {
    const csrfToken = readCsrfToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    try {
        const response = await fetch(contentUrl(config), {
            method: 'PUT',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({ blocks }),
        });

        return (await requireOk(response)) as ContentResponse;
    } catch (error: unknown) {
        throw normalizeError(error, __('Failed to save content.', TEXT_DOMAIN));
    }
}

/**
 * Persists a partial WP-shape entity record to the G3 endpoint at
 * `{apiBase}/{resource}/{id}`. Used to flush metadata edits (title,
 * slug, status, excerpt, featured_media, author, categories, tags,
 * parent, menu_order, template) staged through the core-data shim's
 * `editEntityRecord` action — the legacy `/content` endpoint only
 * accepts the block tree, so metadata persistence rides this separate
 * channel. The server's `UpdatePostRequest` / `UpdatePageRequest`
 * gate every field with `sometimes`, so a partial payload is the
 * canonical shape.
 *
 * Resources without a dedicated entity adapter (custom content types)
 * pass `endpoint: 'entry'` to target `{apiBase}/{resource}/{id}/entry`,
 * which accepts title and excerpt only (#992).
 *
 * @since 1.0.0
 */
export async function saveEntityRecord(
    config: ApiClientConfig,
    edits: Record<string, unknown>,
    endpoint: EntityEndpoint = 'entity'
): Promise<unknown> {
    const csrfToken = readCsrfToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    try {
        const url = endpoint === 'entry'
            ? `${entityRecordUrl(config)}/entry`
            : entityRecordUrl(config);

        const response = await fetch(url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify(edits),
        });

        return await requireOk(response);
    } catch (error: unknown) {
        throw normalizeError(error, __('Failed to save metadata.', TEXT_DOMAIN));
    }
}

export interface PreviewBlockConfig {
    apiBase: string;
}

export interface PreviewBlockResponse {
    name: string;
    html: string;
}

function previewUrl(config: PreviewBlockConfig): string {
    const base = config.apiBase.replace(/\/$/, '');

    return `${base}/blocks/preview`;
}

export interface PreviewBlockOptions {
    name: string;
    attributes?: Record<string, unknown>;
    signal?: AbortSignal;
}

export async function previewBlock(
    config: PreviewBlockConfig,
    options: PreviewBlockOptions
): Promise<PreviewBlockResponse> {
    const csrfToken = readCsrfToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    try {
        const response = await fetch(previewUrl(config), {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({
                name: options.name,
                attributes: options.attributes ?? {},
            }),
            signal: options.signal,
        });

        return (await requireOk(response)) as PreviewBlockResponse;
    } catch (error: unknown) {
        throw normalizeError(error, __('Failed to preview block.', TEXT_DOMAIN));
    }
}
