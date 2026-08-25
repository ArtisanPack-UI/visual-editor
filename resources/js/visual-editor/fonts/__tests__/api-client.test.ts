/**
 * Font Library REST client — unit tests (#635).
 *
 * Mocks `fetch` to verify URL/verb/body shaping, envelope unwrapping, the
 * `read_only` signal, variant parsing, and the shaped-error path (a 403 with a
 * `forbidden` code).
 *
 * @since 1.7.0
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    bulkUninstall,
    catalogPreviewUrl,
    fetchCatalog,
    fetchInstalledFonts,
    fetchSources,
    FontLibraryApiError,
    installFont,
    parseVariant,
    uninstallFont,
    uploadFont,
    type CatalogFamily,
} from '../api-client';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

const fetchMock = vi.fn();

beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('parseVariant', () => {
    it('parses normal and italic tokens', () => {
        expect(parseVariant('400')).toEqual({ weight: 400, style: 'normal' });
        expect(parseVariant('700i')).toEqual({ weight: 700, style: 'italic' });
    });

    it('falls back to 400 for a malformed token', () => {
        expect(parseVariant('abc')).toEqual({ weight: 400, style: 'normal' });
    });
});

describe('catalogPreviewUrl', () => {
    function fam(overrides: Partial<CatalogFamily> = {}): CatalogFamily {
        return {
            slug: 'open-sans',
            family: 'Open Sans',
            category: null,
            variants: ['400'],
            is_variable: false,
            ...overrides,
        };
    }

    it('returns a provider-supplied same-origin preview_url', () => {
        expect(catalogPreviewUrl('google', fam({ preview_url: '/visual-editor/api/fonts/x.css' }))).toBe(
            '/visual-editor/api/fonts/x.css'
        );
    });

    it('never synthesizes a Google/Bunny CDN URL for a known provider', () => {
        expect(catalogPreviewUrl('google', fam())).toBeUndefined();
        expect(catalogPreviewUrl('bunny', fam())).toBeUndefined();
    });

    it('returns undefined when no preview_url is supplied', () => {
        expect(catalogPreviewUrl('custom', fam())).toBeUndefined();
    });
});

describe('fetchInstalledFonts', () => {
    it('unwraps the data list and read-only signal', async () => {
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ data: [{ id: 1, family: 'Inter' }], can_manage: true, read_only: false })
        );

        const result = await fetchInstalledFonts();

        expect(fetchMock).toHaveBeenCalledWith('/visual-editor/api/fonts', expect.objectContaining({ method: 'GET' }));
        expect(result.fonts).toHaveLength(1);
        expect(result.canManage).toBe(true);
        expect(result.readOnly).toBe(false);
    });
});

describe('fetchSources', () => {
    it('unwraps the provider list', async () => {
        fetchMock.mockResolvedValueOnce(
            jsonResponse({
                data: [{ key: 'google', label: 'Google Fonts', is_self_hostable: true }],
                can_manage: false,
                read_only: true,
            })
        );

        const result = await fetchSources();

        expect(fetchMock.mock.calls[0][0]).toBe('/visual-editor/api/fonts/sources');
        expect(result.sources[0].key).toBe('google');
        expect(result.readOnly).toBe(true);
    });
});

describe('fetchCatalog', () => {
    it('omits the query and page for a first browse', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({ data: { families: [], page: 1, has_more: false } }));

        await fetchCatalog('google', '', 1);

        expect(fetchMock.mock.calls[0][0]).toBe('/visual-editor/api/fonts/sources/google/catalog');
    });

    it('encodes the query and page for search + pagination', async () => {
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ data: { families: [{ slug: 'inter' }], page: 2, has_more: true } })
        );

        const result = await fetchCatalog('google', 'in ter', 2);

        expect(fetchMock.mock.calls[0][0]).toBe(
            '/visual-editor/api/fonts/sources/google/catalog?q=in+ter&page=2'
        );
        expect(result.families).toHaveLength(1);
        expect(result.has_more).toBe(true);
    });
});

describe('installFont', () => {
    it('posts the provider, slug, and faces', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({ data: { id: 3, slug: 'inter' } }, 201));

        const font = await installFont('google', 'inter', [{ weight: 400, style: 'normal' }]);

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toBe('/visual-editor/api/fonts');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body)).toEqual({
            provider: 'google',
            slug: 'inter',
            faces: [{ weight: 400, style: 'normal' }],
        });
        expect(font.id).toBe(3);
    });

    it('throws a shaped error carrying the forbidden code', async () => {
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ error: 'forbidden', message: 'You do not have permission to manage fonts.' }, 403)
        );

        await expect(installFont('google', 'inter', [{ weight: 400, style: 'normal' }])).rejects.toMatchObject(
            {
                name: 'FontLibraryApiError',
                status: 403,
                code: 'forbidden',
            }
        );
    });
});

describe('uploadFont', () => {
    it('builds a multipart body with indexed face files', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({ data: { id: 9 } }, 201));

        const file = new File([new Uint8Array([1, 2, 3])], 'brand.woff2', { type: 'font/woff2' });
        await uploadFont('Brand', [{ file, weight: 500, style: 'italic' }]);

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toBe('/visual-editor/api/fonts/upload');
        expect(options.body).toBeInstanceOf(FormData);
        const form = options.body as FormData;
        expect(form.get('family')).toBe('Brand');
        expect(form.get('faces[0][weight]')).toBe('500');
        expect(form.get('faces[0][style]')).toBe('italic');
        expect(form.get('faces[0][file]')).toBeInstanceOf(File);
    });
});

describe('bulkUninstall', () => {
    it('posts the ids and returns the removed count', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({ data: { removed: 2 } }));

        const removed = await bulkUninstall([1, 2]);

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ ids: [1, 2] });
        expect(removed).toBe(2);
    });
});

describe('uninstallFont', () => {
    it('issues a DELETE against the font id', async () => {
        fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));

        await uninstallFont(7);

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toBe('/visual-editor/api/fonts/7');
        expect(options.method).toBe('DELETE');
    });

    it('surfaces a network failure as a FontLibraryApiError', async () => {
        fetchMock.mockRejectedValueOnce(new Error('offline'));

        await expect(uninstallFont(7)).rejects.toBeInstanceOf(FontLibraryApiError);
    });
});
