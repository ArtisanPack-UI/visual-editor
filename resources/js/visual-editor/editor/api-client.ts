/**
 * REST client for the visual editor's resource content endpoints.
 *
 * Thin wrapper over `fetch` that targets
 * `{apiBase}/{resource}/{id}/content` and automatically sends the session
 * CSRF token Laravel expects on mutating requests. Errors are surfaced as
 * typed `ApiError` instances so the persistence loop can distinguish
 * auth/validation/network failures.
 */

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

function contentUrl(config: ApiClientConfig): string {
    const base = config.apiBase.replace(/\/$/, '');

    return `${base}/${encodeURIComponent(config.resource)}/${encodeURIComponent(
        config.id
    )}/content`;
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
            `Visual editor request failed with status ${response.status}`,
            response.status,
            body
        );
    }

    return body;
}

export async function fetchContent(
    config: ApiClientConfig
): Promise<ContentResponse> {
    const response = await fetch(contentUrl(config), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    return (await requireOk(response)) as ContentResponse;
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

    const response = await fetch(contentUrl(config), {
        method: 'PUT',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({ blocks }),
    });

    return (await requireOk(response)) as ContentResponse;
}
