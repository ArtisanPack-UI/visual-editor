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

/**
 * Read the CSRF token from the page's `csrf-token` meta tag, or `null` when
 * it is absent (e.g. in a non-browser test environment).
 *
 * @since 1.7.0
 */
function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

    return meta?.content?.trim() || null;
}

/**
 * Build the header set for a JSON mutating request, including the CSRF token
 * when one is present.
 *
 * @since 1.7.0
 */
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

/**
 * Build the header set for a multipart upload. The `Content-Type` is left
 * unset so the browser can add the multipart boundary itself.
 *
 * @since 1.7.0
 */
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

/**
 * Parse a response body as JSON, falling back to the raw text for a non-JSON
 * body and to `null` for an empty one.
 *
 * @since 1.7.0
 */
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

/**
 * Read a string-valued field from a parsed JSON body, or `null` when it is
 * missing or not a string. Used to pull `message` / `error` off error shapes.
 *
 * @since 1.7.0
 */
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

/**
 * Return the parsed body for a 2xx response, or throw a {@link FontLibraryApiError}
 * carrying the server's `message` and `error` code for any other status.
 *
 * @since 1.7.0
 */
async function requireOk(response: Response, fallback: string): Promise<unknown> {
    const body = await parseBody(response);

    if (!response.ok) {
        const message = stringField(body, 'message') ?? `${fallback} (HTTP ${response.status})`;
        const code = stringField(body, 'error');

        throw new FontLibraryApiError(message, response.status, code);
    }

    return body;
}

/**
 * Coerce any thrown value into a {@link FontLibraryApiError}, preserving an
 * existing one and wrapping a network/other failure with a fallback message.
 *
 * @since 1.7.0
 */
function normalizeError(error: unknown, fallback: string): FontLibraryApiError {
    if (error instanceof FontLibraryApiError) {
        return error;
    }

    // A request cancelled via AbortController is not a failure — tag it so
    // callers can silently ignore it rather than surfacing an error state.
    if (error instanceof DOMException && error.name === 'AbortError') {
        return new FontLibraryApiError(error.message || 'Request aborted.', 0, 'aborted');
    }

    const message = error instanceof Error && error.message ? error.message : fallback;

    return new FontLibraryApiError(message, 0, null);
}

/**
 * Resolve a session-only preview stylesheet URL for a catalog family the user
 * hasn't installed yet, so the modal can render the font in its real face
 * while browsing.
 *
 * Only a provider-supplied `preview_url` is honored, and it is expected to be
 * a first-party (same-origin) URL served by the package. This client never
 * synthesizes a Google/Bunny CDN URL: contacting those hosts from the editor
 * would bypass the REST surface and the feature's self-hosting/GDPR guarantee
 * (see the issue note and the first-party preview endpoint follow-up, #735).
 * Until a provider exposes such a URL, catalog previews fall back to a system
 * font — installed fonts always preview from their self-hosted `@font-face`.
 *
 * @since 1.7.0
 */
export function catalogPreviewUrl(_provider: string, family: CatalogFamily): string | undefined {
    const url = family.preview_url;

    // Honor only a same-origin, root-relative path. A third-party provider
    // registered through `ap.visualEditor.registerFontSources` could
    // otherwise return an absolute CDN URL that the modal would load as a
    // stylesheet — exactly the cross-origin font request the self-hosting /
    // GDPR guarantee forbids. Protocol-relative (`//host`) and absolute URLs
    // are dropped so the preview falls back to a system font.
    if (typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//')) {
        return undefined;
    }

    return url;
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
export async function fetchInstalledFonts(signal?: AbortSignal): Promise<InstalledFontsResult> {
    try {
        const response = await fetch(FONTS_API_BASE, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
            signal,
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
export async function fetchSources(signal?: AbortSignal): Promise<SourcesResult> {
    try {
        const response = await fetch(`${FONTS_API_BASE}/sources`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
            signal,
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
    page: number,
    signal?: AbortSignal
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
            signal,
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
