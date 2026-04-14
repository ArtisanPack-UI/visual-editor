import type { Block } from '../store';

export interface PostPayload {
    id: number;
    title: string;
    blocks: Block[];
    updated_at: string;
}

export interface PostRestClientOptions {
    apiBase: string;
    fetchImpl?: typeof fetch;
    csrfToken?: string | null;
}

export interface FetchPostOptions {
    signal?: AbortSignal;
}

export interface SavePostOptions {
    signal?: AbortSignal;
}

export class PostRestError extends Error {
    public readonly status: number;
    public readonly body: unknown;

    constructor(message: string, status: number, body: unknown = null) {
        super(message);
        this.name = 'PostRestError';
        this.status = status;
        this.body = body;
    }
}

function joinUrl(base: string, path: string): string {
    const trimmedBase = base.replace(/\/+$/, '');
    const trimmedPath = path.replace(/^\/+/, '');
    return `${trimmedBase}/${trimmedPath}`;
}

function resolveCsrfToken(override: string | null | undefined): string | null {
    if (override !== undefined) {
        return override;
    }

    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');

    if (!meta) {
        return null;
    }

    const token = meta.getAttribute('content');

    return token && token.length > 0 ? token : null;
}

function buildHeaders(csrfToken: string | null, includeContentType: boolean): Headers {
    const headers = new Headers({
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    });

    if (includeContentType) {
        headers.set('Content-Type', 'application/json');
    }

    if (csrfToken) {
        headers.set('X-CSRF-TOKEN', csrfToken);
    }

    return headers;
}

function resolveFetch(override: typeof fetch | undefined): typeof fetch {
    if (override) {
        return override;
    }

    if (typeof fetch === 'function') {
        return fetch.bind(globalThis);
    }

    throw new PostRestError('No fetch implementation available.', 0);
}

async function parseJsonSafe(response: Response): Promise<unknown> {
    try {
        return await response.json();
    } catch {
        return null;
    }
}

function isPostPayload(value: unknown): value is PostPayload {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const source = value as Record<string, unknown>;

    return (
        typeof source.id === 'number' &&
        typeof source.title === 'string' &&
        Array.isArray(source.blocks) &&
        typeof source.updated_at === 'string'
    );
}

function isAbortError(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

async function fetchWithErrorWrapping(
    fetcher: typeof fetch,
    url: string,
    init: RequestInit
): Promise<Response> {
    try {
        return await fetcher(url, init);
    } catch (error) {
        if (isAbortError(error)) {
            throw error;
        }

        const message =
            error instanceof Error
                ? `Visual editor REST request failed: ${error.message}`
                : 'Visual editor REST request failed.';

        throw new PostRestError(message, 0, error);
    }
}

async function handlePostResponse(response: Response): Promise<PostPayload> {
    const body = await parseJsonSafe(response);

    if (!response.ok) {
        throw new PostRestError(
            `Visual editor REST request failed with status ${response.status}.`,
            response.status,
            body
        );
    }

    if (!isPostPayload(body)) {
        throw new PostRestError(
            'Visual editor REST response did not match the expected post shape.',
            response.status,
            body
        );
    }

    return body;
}

export async function fetchPost(
    postId: string,
    clientOptions: PostRestClientOptions,
    options: FetchPostOptions = {}
): Promise<PostPayload> {
    const fetcher = resolveFetch(clientOptions.fetchImpl);
    const csrfToken = resolveCsrfToken(clientOptions.csrfToken);
    const url = joinUrl(clientOptions.apiBase, `posts/${encodeURIComponent(postId)}`);

    const response = await fetchWithErrorWrapping(fetcher, url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: buildHeaders(csrfToken, false),
        signal: options.signal,
    });

    return handlePostResponse(response);
}

export async function savePost(
    postId: string,
    blocks: Block[],
    clientOptions: PostRestClientOptions,
    options: SavePostOptions = {}
): Promise<PostPayload> {
    const fetcher = resolveFetch(clientOptions.fetchImpl);
    const csrfToken = resolveCsrfToken(clientOptions.csrfToken);
    const url = joinUrl(clientOptions.apiBase, `posts/${encodeURIComponent(postId)}`);

    const response = await fetchWithErrorWrapping(fetcher, url, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: buildHeaders(csrfToken, true),
        body: JSON.stringify({ blocks }),
        signal: options.signal,
    });

    return handlePostResponse(response);
}
