/**
 * Font Library editor-side REST client (#635).
 *
 * Thin, typed wrapper over the `/visual-editor/api/fonts*` surface exposed by
 * {@see \ArtisanPackUI\VisualEditor\Http\Controllers\Fonts\FontLibraryController}.
 * The modal drives every request through here so it never has to reconstruct
 * URLs, CSRF handling, or the two response envelopes the controller returns
 * (`{ data, can_manage, read_only }` for the read surfaces, `{ data }` for a
 * single font).
 *
 * The client never talks to Google/Bunny directly — remote catalog access is
 * proxied through the provider's `catalog` endpoint so the browser stays GDPR
 * clean and the API key never leaves the server.
 *
 * @since 1.7.0
 */

export const FONTS_API_BASE = '/visual-editor/api/fonts';

/**
 * A single installed face, as serialized by the controller. `url` is the
 * public URL of the self-hosted file, or `null` when the disk driver cannot
 * build one.
 */
export interface FontFace {
    readonly id: number;
    readonly weight: number;
    readonly style: string;
    readonly format: string | null;
    readonly axes: Record<string, unknown> | null;
    readonly url: string | null;
}

/** An installed font with its faces. */
export interface InstalledFont {
    readonly id: number;
    readonly provider: string;
    readonly family: string;
    readonly slug: string;
    readonly is_variable: boolean;
    readonly license: string | null;
    readonly source_url: string | null;
    readonly installed_at: string | null;
    readonly faces: readonly FontFace[];
}

/** A registered provider tab. */
export interface FontSource {
    readonly key: string;
    readonly label: string;
    readonly is_self_hostable: boolean;
}

/**
 * One catalog family summary. `variants` are provider tokens (`400`, `700i`)
 * that {@link parseVariant} expands into installable faces; a provider may
 * also supply a `preview_url` stylesheet consumed by the live preview.
 */
export interface CatalogFamily {
    readonly slug: string;
    readonly family: string;
    readonly category: string | null;
    readonly variants: readonly string[];
    readonly is_variable: boolean;
    readonly preview_url?: string;
}

/** A page of catalog results. */
export interface CatalogPage {
    readonly families: readonly CatalogFamily[];
    readonly page: number;
    readonly has_more: boolean;
}

/** The read-surface envelope shared by the installed list and provider list. */
interface ReadEnvelope<T> {
    readonly data: T;
    readonly can_manage: boolean;
    readonly read_only: boolean;
}

/** The installed-fonts response, carrying the session's read-only signal. */
export interface InstalledFontsResult {
    readonly fonts: readonly InstalledFont[];
    readonly canManage: boolean;
    readonly readOnly: boolean;
}

/** The provider-list response, carrying the session's read-only signal. */
export interface SourcesResult {
    readonly sources: readonly FontSource[];
    readonly canManage: boolean;
    readonly readOnly: boolean;
}

/** A single requested face for an install. */
export interface InstallFace {
    readonly weight: number;
    readonly style: 'normal' | 'italic';
}

/** A single uploaded face: the file plus its optional declared metadata. */
export interface UploadFace {
    readonly file: File;
    readonly weight?: number;
    readonly style?: 'normal' | 'italic';
}

/**
 * Typed error raised for every non-2xx response. `code` mirrors the
 * controller's shaped `error` key (`forbidden`, `install_failed`,
 * `unknown_provider`, …) so callers can branch — most importantly on
 * `forbidden`, which flips the modal to read-only.
 */
export class FontLibraryApiError extends Error {
    public readonly status: number;

    public readonly code: string | null;

    public constructor(message: string, status: number, code: string | null = null) {
        super(message);
        this.name = 'FontLibraryApiError';
        this.status = status;
        this.code = code;
    }
}

const READ_HEADERS: Readonly<Record<string, string>> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

    return meta?.content?.trim() || null;
}

function jsonHeaders(): Record<string, string> {
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

function multipartHeaders(): Record<string, string> {
    // No Content-Type: the browser sets the multipart boundary itself.
    const headers: Record<string, string> = {
        Accept: 'application/json',
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

function stringField(body: unknown, field: string): string | null {
    if (
        body !== null &&
        typeof body === 'object' &&
        field in body &&
        typeof (body as Record<string, unknown>)[field] === 'string'
    ) {
        return (body as Record<string, string>)[field];
    }

    return null;
}

async function requireOk(response: Response, fallback: string): Promise<unknown> {
    const body = await parseBody(response);

    if (!response.ok) {
        const message = stringField(body, 'message') ?? `${fallback} (HTTP ${response.status})`;
        const code = stringField(body, 'error');

        throw new FontLibraryApiError(message, response.status, code);
    }

    return body;
}

function normalizeError(error: unknown, fallback: string): FontLibraryApiError {
    if (error instanceof FontLibraryApiError) {
        return error;
    }

    const message = error instanceof Error && error.message ? error.message : fallback;

    return new FontLibraryApiError(message, 0, null);
}

/**
 * Derive a session-only preview stylesheet URL for a catalog family the user
 * hasn't installed yet, so the modal can render the font in its real face
 * while browsing.
 *
 * A provider-supplied `preview_url` wins; otherwise the known remote providers
 * are mapped to their public CSS endpoints (Google's `css2`, Bunny's `css`).
 * This is loaded only in the editor for the modal session — installing a font
 * still self-hosts every file, so a site's visitors never hit these CDNs.
 * Providers we don't recognize (and custom uploads) return `undefined`, and
 * the preview simply falls back to a system font.
 *
 * @since 1.7.0
 */
export function catalogPreviewUrl(provider: string, family: CatalogFamily): string | undefined {
    if (family.preview_url) {
        return family.preview_url;
    }

    switch (provider) {
        case 'google':
            return `https://fonts.googleapis.com/css2?family=${encodeURIComponent(family.family).replace(
                /%20/g,
                '+'
            )}&display=swap`;
        case 'bunny':
            return `https://fonts.bunny.net/css?family=${encodeURIComponent(family.slug)}`;
        default:
            return undefined;
    }
}

/**
 * Expand a provider variant token (`400`, `700i`) into an installable face.
 * A trailing `i` marks italic; the numeric prefix is the weight.
 *
 * @since 1.7.0
 */
export function parseVariant(variant: string): InstallFace {
    const token = variant.trim();

    if (token.toLowerCase().endsWith('i')) {
        return { weight: parseInt(token.slice(0, -1), 10) || 400, style: 'italic' };
    }

    return { weight: parseInt(token, 10) || 400, style: 'normal' };
}

/**
 * List every installed font, with the session's read-only signal.
 *
 * @since 1.7.0
 */
export async function fetchInstalledFonts(): Promise<InstalledFontsResult> {
    try {
        const response = await fetch(FONTS_API_BASE, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        const body = (await requireOk(response, 'Failed to load installed fonts.')) as
            | ReadEnvelope<readonly InstalledFont[]>
            | null;

        return {
            fonts: Array.isArray(body?.data) ? body!.data : [],
            canManage: Boolean(body?.can_manage),
            readOnly: body ? Boolean(body.read_only) : true,
        };
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load installed fonts.');
    }
}

/**
 * List the registered provider tabs, with the session's read-only signal.
 *
 * @since 1.7.0
 */
export async function fetchSources(): Promise<SourcesResult> {
    try {
        const response = await fetch(`${FONTS_API_BASE}/sources`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        const body = (await requireOk(response, 'Failed to load font providers.')) as
            | ReadEnvelope<readonly FontSource[]>
            | null;

        return {
            sources: Array.isArray(body?.data) ? body!.data : [],
            canManage: Boolean(body?.can_manage),
            readOnly: body ? Boolean(body.read_only) : true,
        };
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load font providers.');
    }
}

/**
 * Browse or search a single provider's catalog, one page at a time.
 *
 * @since 1.7.0
 */
export async function fetchCatalog(
    provider: string,
    query: string,
    page: number
): Promise<CatalogPage> {
    try {
        const params = new URLSearchParams();

        if (query.trim() !== '') {
            params.set('q', query.trim());
        }

        if (page > 1) {
            params.set('page', String(page));
        }

        const qs = params.toString();
        const url = `${FONTS_API_BASE}/sources/${encodeURIComponent(provider)}/catalog${
            qs === '' ? '' : `?${qs}`
        }`;

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        const body = (await requireOk(response, 'Failed to load the font catalog.')) as
            | { data?: Partial<CatalogPage> }
            | null;

        const data = body?.data ?? {};

        return {
            families: Array.isArray(data.families) ? data.families : [],
            page: typeof data.page === 'number' ? data.page : page,
            has_more: Boolean(data.has_more),
        };
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to load the font catalog.');
    }
}

/**
 * Install a catalog font's selected faces from a registered provider.
 *
 * @since 1.7.0
 */
export async function installFont(
    provider: string,
    slug: string,
    faces: readonly InstallFace[]
): Promise<InstalledFont> {
    try {
        const response = await fetch(FONTS_API_BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonHeaders(),
            body: JSON.stringify({ provider, slug, faces }),
        });

        const body = (await requireOk(response, 'Failed to install the font.')) as {
            data: InstalledFont;
        };

        return body.data;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to install the font.');
    }
}

/**
 * Upload one or more custom font files as a single family.
 *
 * @since 1.7.0
 */
export async function uploadFont(
    family: string,
    faces: readonly UploadFace[]
): Promise<InstalledFont> {
    try {
        const form = new FormData();
        form.set('family', family);

        faces.forEach((face, index) => {
            form.set(`faces[${index}][file]`, face.file);

            if (typeof face.weight === 'number') {
                form.set(`faces[${index}][weight]`, String(face.weight));
            }

            if (face.style) {
                form.set(`faces[${index}][style]`, face.style);
            }
        });

        const response = await fetch(`${FONTS_API_BASE}/upload`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: multipartHeaders(),
            body: form,
        });

        const body = (await requireOk(response, 'Failed to upload the font.')) as {
            data: InstalledFont;
        };

        return body.data;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to upload the font.');
    }
}

/**
 * Uninstall several fonts at once. Returns how many rows were removed.
 *
 * @since 1.7.0
 */
export async function bulkUninstall(ids: readonly number[]): Promise<number> {
    try {
        const response = await fetch(`${FONTS_API_BASE}/bulk-uninstall`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonHeaders(),
            body: JSON.stringify({ ids }),
        });

        const body = (await requireOk(response, 'Failed to uninstall the selected fonts.')) as {
            data?: { removed?: number };
        } | null;

        return typeof body?.data?.removed === 'number' ? body.data.removed : 0;
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to uninstall the selected fonts.');
    }
}

/**
 * Uninstall a single font.
 *
 * @since 1.7.0
 */
export async function uninstallFont(id: number): Promise<void> {
    try {
        const response = await fetch(`${FONTS_API_BASE}/${encodeURIComponent(String(id))}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: jsonHeaders(),
        });

        await requireOk(response, 'Failed to uninstall the font.');
    } catch (error: unknown) {
        throw normalizeError(error, 'Failed to uninstall the font.');
    }
}
