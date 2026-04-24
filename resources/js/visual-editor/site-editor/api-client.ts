/**
 * Site-editor REST client for template and template-part entities.
 *
 * The site-editor talks to the C1/C2 REST surface exposed by the package
 * under `/visual-editor/api/templates` and `/visual-editor/api/template-parts`.
 * This client wraps the five endpoints per entity behind typed helpers so
 * D2's browsers and editors don't have to reconstruct URLs, CSRF handling,
 * or error shapes ad hoc.
 *
 * The client is intentionally separate from `editor/api-client.ts` (which
 * targets the generic `/resources/{type}/{id}/content` surface used by
 * posts and pages). Templates and parts have their own routes because the
 * core-data shim expects the WordPress `wp_template` / `wp_template_part`
 * record shape — not the `{ blocks }` envelope the post editor sends.
 */

export type EntityKind = 'template' | 'template-part';

/**
 * The common fields every list row / detail response carries. Kept loose
 * because the two entity kinds share 80% of their shape — the `area` field
 * on template parts and the `source`/`origin`/`description`/`status`
 * fields on templates are the only divergences.
 */
export interface EntityTitle {
    rendered: string;
}

export interface EntityContent {
    raw: string;
    blocks: readonly unknown[];
}

export interface TemplateRecord {
    id: number;
    slug: string;
    title: EntityTitle;
    description: string;
    content: EntityContent;
    status: string;
    theme: string;
    type: 'wp_template';
    source: string;
    origin: string | null;
}

export interface TemplatePartRecord {
    id: number;
    slug: string;
    title: EntityTitle;
    content: EntityContent;
    area: string;
    theme: string;
    type: 'wp_template_part';
    /** Populated only on `show` responses. */
    referenced_by?: readonly string[];
}

export type EntityRecord<K extends EntityKind> = K extends 'template'
    ? TemplateRecord
    : TemplatePartRecord;

export interface PaginatedResponse<T> {
    data: readonly T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface ListParams {
    perPage?: number;
    page?: number;
    theme?: string;
    slug?: string;
    /** Templates only. */
    status?: string;
    /** Template parts only. */
    area?: string;
}

export interface ValidationErrors {
    [field: string]: readonly string[];
}

/**
 * Typed error raised for every non-2xx response so callers can inline a
 * validation summary (status 422 carries `{ errors: {...} }`) or a generic
 * failure banner (other statuses).
 */
export class SiteEditorApiError extends Error {
    public readonly status: number;

    public readonly body: unknown;

    public readonly validationErrors: ValidationErrors | null;

    public constructor(message: string, status: number, body: unknown) {
        super(message);
        this.name = 'SiteEditorApiError';
        this.status = status;
        this.body = body;
        this.validationErrors = extractValidationErrors(body);
    }
}

export interface SiteEditorApiConfig {
    /** Absolute base URL for the site-editor API (e.g. `/visual-editor/api`). */
    apiBase: string;
}

const RESOURCE_PATH: Record<EntityKind, string> = {
    template: 'templates',
    'template-part': 'template-parts',
};

function buildUrl(
    config: SiteEditorApiConfig,
    kind: EntityKind,
    idOrSuffix: string | number | null = null,
    query: Record<string, string | number | undefined> = {}
): string {
    const base = config.apiBase.replace(/\/+$/, '');
    let url = `${base}/${RESOURCE_PATH[kind]}`;

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
            result[field] = value
                .filter((entry): entry is string => typeof entry === 'string');
        }
    }

    return result;
}

async function requireOk(response: Response): Promise<unknown> {
    const body = await parseBody(response);

    if (!response.ok) {
        const baseMessage = `Site-editor request failed with status ${response.status}`;
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

function normalizeError(error: unknown, fallbackMessage: string): SiteEditorApiError {
    if (error instanceof SiteEditorApiError) {
        return error;
    }

    const message =
        error instanceof Error && error.message ? error.message : fallbackMessage;

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

export async function listEntities<K extends EntityKind>(
    config: SiteEditorApiConfig,
    kind: K,
    params: ListParams = {}
): Promise<PaginatedResponse<EntityRecord<K>>> {
    try {
        const response = await fetch(
            buildUrl(config, kind, null, {
                per_page: params.perPage,
                page: params.page,
                theme: params.theme,
                slug: params.slug,
                status: params.status,
                area: params.area,
            }),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: READ_HEADERS,
            }
        );

        return (await requireOk(response)) as PaginatedResponse<EntityRecord<K>>;
    } catch (error: unknown) {
        throw normalizeError(error, `Failed to load ${kind} list.`);
    }
}

export async function fetchEntity<K extends EntityKind>(
    config: SiteEditorApiConfig,
    kind: K,
    id: number | string
): Promise<EntityRecord<K>> {
    try {
        const response = await fetch(buildUrl(config, kind, id), {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        return (await requireOk(response)) as EntityRecord<K>;
    } catch (error: unknown) {
        throw normalizeError(error, `Failed to load ${kind}.`);
    }
}

export interface TemplateCreatePayload {
    slug: string;
    title?: string;
    description?: string | null;
    theme: string;
    status?: string;
    source?: string;
    origin?: string | null;
    content?: EntityContent;
}

export interface TemplatePartCreatePayload {
    slug: string;
    title?: string;
    area: string;
    theme: string;
    content?: EntityContent;
}

export type CreatePayload<K extends EntityKind> = K extends 'template'
    ? TemplateCreatePayload
    : TemplatePartCreatePayload;

export async function createEntity<K extends EntityKind>(
    config: SiteEditorApiConfig,
    kind: K,
    payload: CreatePayload<K>
): Promise<EntityRecord<K>> {
    try {
        const response = await fetch(buildUrl(config, kind), {
            method: 'POST',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as EntityRecord<K>;
    } catch (error: unknown) {
        throw normalizeError(error, `Failed to create ${kind}.`);
    }
}

export interface UpdatePayload {
    content?: EntityContent;
    title?: string;
    slug?: string;
    description?: string | null;
    status?: string;
    source?: string;
    origin?: string | null;
    area?: string;
    theme?: string;
}

export async function updateEntity<K extends EntityKind>(
    config: SiteEditorApiConfig,
    kind: K,
    id: number | string,
    payload: UpdatePayload
): Promise<EntityRecord<K>> {
    try {
        const response = await fetch(buildUrl(config, kind, id), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
            body: JSON.stringify(payload),
        });

        return (await requireOk(response)) as EntityRecord<K>;
    } catch (error: unknown) {
        throw normalizeError(error, `Failed to save ${kind}.`);
    }
}

export async function deleteEntity(
    config: SiteEditorApiConfig,
    kind: EntityKind,
    id: number | string
): Promise<void> {
    try {
        const response = await fetch(buildUrl(config, kind, id), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: mutatingHeaders(),
        });

        await requireOk(response);
    } catch (error: unknown) {
        throw normalizeError(error, `Failed to delete ${kind}.`);
    }
}
