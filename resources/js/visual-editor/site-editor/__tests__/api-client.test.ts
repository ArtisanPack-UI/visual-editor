import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    SiteEditorApiError,
    createEntity,
    deleteEntity,
    fetchEntity,
    listEntities,
    updateEntity,
} from '../api-client';

const API_BASE = '/visual-editor/api';

function mockFetch(
    response: Response | (() => Response)
): ReturnType<typeof vi.fn> {
    const fn = vi.fn(async (): Promise<Response> =>
        typeof response === 'function' ? response() : response
    );

    vi.stubGlobal('fetch', fn);

    return fn;
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    // Clear any inherited CSRF meta between tests.
    document.querySelectorAll('meta[name="csrf-token"]').forEach((node) => {
        node.remove();
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('listEntities', () => {
    it('returns the H6 flat-array response and applies filters', async () => {
        // H7 (#432). H6's `TemplateController::index` returns a flat
        // JSON array — no `{ data, meta }` envelope.
        const body = [{ id: 1, slug: 'single', title: { rendered: 'Single' } }];
        const fetchMock = mockFetch(jsonResponse(body));

        const result = await listEntities(
            { apiBase: API_BASE },
            'template',
            { perPage: 10, theme: 'my-theme', status: 'publish' }
        );

        expect(result).toHaveLength(1);
        expect(result[0]?.id).toBe(1);

        const firstCall = fetchMock.mock.calls[0];
        expect(firstCall).toBeDefined();

        const url = firstCall![0] as string;
        expect(url).toContain('/visual-editor/api/templates');
        expect(url).toContain('per_page=10');
        expect(url).toContain('theme=my-theme');
        expect(url).toContain('status=publish');
    });

    it('coerces non-array bodies to an empty list', async () => {
        // Defensive — a malformed response should not crash the
        // navigator with `items.length === undefined`.
        mockFetch(jsonResponse({ message: 'oops' }));

        const result = await listEntities(
            { apiBase: API_BASE },
            'template'
        );

        expect(result).toEqual([]);
    });

    it('hits the template-parts endpoint with an area filter', async () => {
        const fetchMock = mockFetch(jsonResponse([]));

        await listEntities(
            { apiBase: API_BASE },
            'template-part',
            { area: 'header' }
        );

        const url = fetchMock.mock.calls[0]?.[0] as string;
        expect(url).toContain('/visual-editor/api/template-parts');
        expect(url).toContain('area=header');
    });

    it('raises SiteEditorApiError with the parsed body on non-2xx responses', async () => {
        mockFetch(
            jsonResponse({ message: 'Nope', errors: { slug: ['required'] } }, 422)
        );

        await expect(
            listEntities({ apiBase: API_BASE }, 'template')
        ).rejects.toBeInstanceOf(SiteEditorApiError);
    });
});

describe('fetchEntity', () => {
    it('returns the single-record response', async () => {
        mockFetch(
            jsonResponse({
                id: 42,
                slug: 'page',
                title: { rendered: 'Page' },
                content: { raw: '', blocks: [] },
            })
        );

        const record = await fetchEntity({ apiBase: API_BASE }, 'template', 42);

        expect(record.id).toBe(42);
        expect(record.slug).toBe('page');
    });
});

describe('createEntity', () => {
    it('POSTs with the CSRF token and Content-Type headers', async () => {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'test-token');
        document.head.appendChild(meta);

        const fetchMock = mockFetch(
            jsonResponse({ id: 1, slug: 'single', title: { rendered: '' } }, 201)
        );

        await createEntity({ apiBase: API_BASE }, 'template', {
            slug: 'single',
            theme: 'default',
        });

        const [, init] = fetchMock.mock.calls[0] ?? [];
        expect(init?.method).toBe('POST');
        const headers = init?.headers as Record<string, string>;
        expect(headers['X-CSRF-TOKEN']).toBe('test-token');
        expect(headers['Content-Type']).toBe('application/json');
    });
});

describe('updateEntity', () => {
    it('PUTs with the body payload', async () => {
        const fetchMock = mockFetch(
            jsonResponse({ id: 1, slug: 'single', title: { rendered: '' } })
        );

        await updateEntity({ apiBase: API_BASE }, 'template', 1, {
            content: { raw: '', blocks: [{ name: 'core/paragraph' }] },
        });

        const [, init] = fetchMock.mock.calls[0] ?? [];
        expect(init?.method).toBe('PUT');

        const parsedBody = JSON.parse(init?.body as string) as {
            content: { blocks: unknown[] };
        };
        expect(parsedBody.content.blocks).toHaveLength(1);
    });

    it('surfaces 422 validation errors on the error object', async () => {
        mockFetch(
            jsonResponse(
                {
                    message: 'The given data was invalid.',
                    errors: { slug: ['Slug already in use.'] },
                },
                422
            )
        );

        try {
            await updateEntity({ apiBase: API_BASE }, 'template', 1, {});
            expect.unreachable();
        } catch (error: unknown) {
            expect(error).toBeInstanceOf(SiteEditorApiError);
            const typed = error as SiteEditorApiError;
            expect(typed.status).toBe(422);
            expect(typed.validationErrors?.slug?.[0]).toBe('Slug already in use.');
        }
    });
});

describe('deleteEntity', () => {
    it('issues a DELETE and resolves on 204', async () => {
        const fetchMock = mockFetch(
            new Response(null, { status: 204 })
        );

        await deleteEntity({ apiBase: API_BASE }, 'template-part', 7);

        const [, init] = fetchMock.mock.calls[0] ?? [];
        expect(init?.method).toBe('DELETE');
    });
});
