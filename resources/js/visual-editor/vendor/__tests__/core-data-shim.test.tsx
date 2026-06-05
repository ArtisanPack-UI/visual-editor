import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { dispatch, resolveSelect, select } from '@wordpress/data';

import {
    DEFAULT_ENTITIES,
    EntityProvider,
    MISSING_RECORD_MARKER,
    RestRequestError,
    __resetCoreDataShimConfig,
    configureCoreDataShim,
    store,
    useEntityBlockEditor,
    useEntityId,
    useEntityProp,
    useEntityRecord,
    useEntityRecords,
    useResourcePermissions,
    type EntityConfig,
    type EntityRecord,
} from '../core-data-shim';

type CoreSelect = Record<string, (...args: unknown[]) => unknown>;
type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;

const coreSelect = (): CoreSelect => select('core') as CoreSelect;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;
const coreResolveSelect = (): Record<
    string,
    (...args: unknown[]) => Promise<unknown>
> =>
    resolveSelect('core') as Record<
        string,
        (...args: unknown[]) => Promise<unknown>
    >;

async function resetStore(): Promise<void> {
    // Drain in-flight resolvers from the previous test so their
    // pending `receiveEntityRecords` dispatches don't land mid-reset.
    // Replacing the fetcher with a fast-fail responder forces any
    // outstanding `restRequest` awaits to resolve immediately; the
    // 50ms macrotask wait then settles the resolver thunks (which
    // chain through several microtasks) before we wipe state and
    // resolution metadata.
    configureCoreDataShim({
        apiBase: '/_drain_',
        fetcher: async () =>
            new Response(null, {
                status: 503,
                headers: { 'Content-Type': 'application/json' },
            }),
    });

    await new Promise((resolve) => setTimeout(resolve, 50));

    coreDispatch().reset();
    coreDispatch().invalidateResolutionForStore();
}

function mockFetcher(
    impl: (input: string, init: RequestInit) => Promise<Response>,
): { fetcher: typeof fetch; calls: Array<{ url: string; init: RequestInit }> } {
    const calls: Array<{ url: string; init: RequestInit }> = [];
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : String(input);
        const normalized = init ?? {};

        calls.push({ url, init: normalized });

        return impl(url, normalized);
    }) as unknown as typeof fetch;

    return { fetcher, calls };
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
});

afterEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
});

describe('core-data-shim entity registry', () => {
    it('registers the V1 site-editor + G3 cms-framework entities by default', () => {
        const names = DEFAULT_ENTITIES.map((entity) => `${entity.kind}|${entity.name}`);

        expect(names).toEqual([
            'postType|wp_template',
            'postType|wp_template_part',
            'postType|wp_navigation',
            // H6 (#431) split items out from the navigation envelope
            // into their own entity so the editor can do incremental
            // item edits without re-saving the whole menu.
            'postType|wp_navigation_link',
            'postType|wp_block',
            'root|globalStyles',
            // G3 cms-framework Post + Page (plan 12 §4.4) — declared
            // unconditionally; the matching `/visual-editor/api/{posts,
            // pages}/{id}` endpoints come from the resource map and
            // resolve to null when cms-framework isn't installed.
            'postType|post',
            'postType|page',
            // G4a — `core/post-featured-image` and `core/cover` resolve
            // the saved `featured_media` id via
            // `getEntityRecord('postType', 'attachment', id)`. The
            // matching `/visual-editor/api/attachments/{id}` endpoint
            // delegates to the host's media library through
            // `apGetMedia()` and falls back to 404 when no media
            // library is installed.
            'postType|attachment',
            // #481 — singleton site-meta entity consumed by the
            // editor's `artisanpack/site-*` block previews. Backed
            // by `/visual-editor/api/site/{id}` which is a singleton
            // (the id is ignored).
            'root|__unstableBase',
        ]);
    });

    it('exposes default entities via getEntities after reset', () => {
        const entities = coreSelect().getEntities() as readonly EntityConfig[];

        expect(entities.map((entity) => `${entity.kind}|${entity.name}`)).toEqual(
            expect.arrayContaining([
                'postType|wp_template',
                'postType|wp_template_part',
                'postType|wp_navigation',
                'postType|wp_block',
                'root|globalStyles',
                'postType|post',
                'postType|page',
            ]),
        );
    });

    it('the G3 post + page entities resolve to /posts and /pages baseURLs', () => {
        const post = DEFAULT_ENTITIES.find(
            (entity) => entity.kind === 'postType' && entity.name === 'post',
        );
        const page = DEFAULT_ENTITIES.find(
            (entity) => entity.kind === 'postType' && entity.name === 'page',
        );

        expect(post).toMatchObject({
            kind: 'postType',
            name: 'post',
            baseURL: '/posts',
            key: 'id',
            label: 'Post',
            plural: 'posts',
        });

        expect(page).toMatchObject({
            kind: 'postType',
            name: 'page',
            baseURL: '/pages',
            key: 'id',
            label: 'Page',
            plural: 'pages',
        });
    });

    it('addEntities registers custom entities alongside defaults', () => {
        coreDispatch().addEntities([
            {
                kind: 'postType',
                name: 'wp_menu_location',
                baseURL: '/menu-locations',
                key: 'slug',
                label: 'Menu Location',
            },
        ]);

        const config = coreSelect().getEntityConfig('postType', 'wp_menu_location');

        expect(config).toMatchObject({
            kind: 'postType',
            name: 'wp_menu_location',
            baseURL: '/menu-locations',
            key: 'slug',
        });

        // Defaults are still present.
        expect(
            coreSelect().getEntityConfig('postType', 'wp_template'),
        ).not.toBeNull();
    });
});

describe('core-data-shim record cache', () => {
    it('returns null from getEntityRecord for unregistered entities', () => {
        expect(
            coreSelect().getEntityRecord('postType', 'post', 1),
        ).toBeNull();
    });

    it('returns [] from getEntityRecords for unregistered entities', () => {
        expect(coreSelect().getEntityRecords('postType', 'post')).toEqual([]);
    });

    it('caches records after receiveEntityRecords', () => {
        const template: EntityRecord = {
            id: 5,
            slug: 'single-post',
            title: 'Single Post',
        };

        coreDispatch().receiveEntityRecords('postType', 'wp_template', [template]);

        expect(
            coreSelect().getEntityRecord('postType', 'wp_template', 5),
        ).toEqual(template);
    });

    it('preserves cached list queries when a single record is received without invalidation (Keystone #57)', () => {
        // Seed a cached list-fetch result for the per_page=-1 query, then
        // dispatch a single-record refresh through the resolver path
        // (`invalidateQueries: false`). The list query must survive so
        // the nav-block overlay panel doesn't flip from "Edit" to the
        // prominent "Create overlay" button when the selected overlay's
        // record is resolved by composite id.
        const seeded: readonly EntityRecord[] = [
            { id: 8, slug: 'mobile-overlay', area: 'navigation-overlay' },
            { id: 9, slug: 'site-overlay', area: 'navigation-overlay' },
        ];

        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_template_part',
            seeded,
            { per_page: -1 },
            2,
            1,
        );

        // Sanity check — the list query is populated.
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toEqual(seeded);

        // Single-record refresh (resolver path) with invalidateQueries:false.
        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_template_part',
            [{ id: 8, slug: 'mobile-overlay', area: 'navigation-overlay', title: { rendered: 'Mobile' } }],
            undefined,
            undefined,
            undefined,
            false,
        );

        // The cached `per_page=-1` query must still return both records.
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toHaveLength(2);
    });

    it('still invalidates cached list queries when a save dispatches without a query (default behavior)', () => {
        // The opt-out is opt-in: save-path receives still wipe the
        // cached queries (the conservative behavior the comment above
        // RECEIVE_ENTITY_RECORDS describes) — only the resolver path
        // opts out via `invalidateQueries: false`.
        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_template_part',
            [{ id: 8, slug: 'mobile-overlay', area: 'navigation-overlay' }],
            { per_page: -1 },
            1,
            1,
        );

        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toHaveLength(1);

        // Save-path receive — no query, no opt-out → queries wiped.
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [
            { id: 8, slug: 'mobile-overlay', area: 'navigation-overlay', title: { rendered: 'Mobile' } },
        ]);

        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toEqual([]);
    });

    it('caches query→ids when a query is provided', () => {
        const templates: readonly EntityRecord[] = [
            { id: 1, slug: 'single' },
            { id: 2, slug: 'archive' },
        ];

        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_template',
            templates,
            null,
            2,
            1,
        );

        expect(
            coreSelect().getEntityRecords('postType', 'wp_template', null),
        ).toEqual(templates);
        expect(
            coreSelect().getEntityRecordsTotalItems(
                'postType',
                'wp_template',
                null,
            ),
        ).toBe(2);
        expect(
            coreSelect().getEntityRecordsTotalPages(
                'postType',
                'wp_template',
                null,
            ),
        ).toBe(1);
    });

    it('keeps stub selectors null for legacy surfaces', () => {
        expect(coreSelect().getCurrentUser()).toBeNull();
        expect(coreSelect().getMedia(42)).toBeNull();
        expect(coreSelect().getUsers()).toEqual([]);
    });

    it('returns a synthetic theme record for getCurrentTheme', () => {
        const theme = coreSelect().getCurrentTheme();

        expect(theme).toMatchObject({
            stylesheet: 'artisanpack-base',
            template: 'artisanpack-base',
            is_block_theme: true,
        });
    });

    it('returns null from getPostType to keep post-* edit components quiet', () => {
        expect(coreSelect().getPostType('post')).toBeNull();
    });

    it('returns an empty themeSupports object', () => {
        expect(coreSelect().getThemeSupports()).toEqual({});
    });

    it('exposes getNavigationFallbackId as a no-op selector', () => {
        expect(coreSelect().getNavigationFallbackId()).toBeUndefined();
    });

    it('reports resolution as not-yet-started for an unread entity tuple', () => {
        // G0 (#395) wires resolvers for `getEntityRecord` /
        // `getEntityRecords`. Before a tuple has been read, no
        // resolver has fired, so `hasFinishedResolution` is false.
        expect(
            coreSelect().hasFinishedResolution('getEntityRecord', [
                'postType',
                'wp_template',
                1,
            ]),
        ).toBe(false);
        expect(
            coreSelect().isResolving('getEntityRecord', [
                'postType',
                'wp_template',
                1,
            ]),
        ).toBe(false);
    });
});

describe('core-data-shim edits pipeline', () => {
    it('accumulates edits via editEntityRecord', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_template', [
            { id: 1, slug: 'page', title: 'Page', status: 'draft' },
        ]);

        coreDispatch().editEntityRecord('postType', 'wp_template', 1, {
            title: 'New title',
        });
        coreDispatch().editEntityRecord('postType', 'wp_template', 1, {
            status: 'publish',
        });

        expect(
            coreSelect().hasEditsForEntityRecord('postType', 'wp_template', 1),
        ).toBe(true);
        expect(
            coreSelect().getEntityRecordEdits('postType', 'wp_template', 1),
        ).toEqual({ title: 'New title', status: 'publish' });
        expect(
            coreSelect().getEditedEntityRecord('postType', 'wp_template', 1),
        ).toEqual({
            id: 1,
            slug: 'page',
            title: 'New title',
            status: 'publish',
        });
    });

    it('clearEntityRecordEdits drops the edits bag', () => {
        coreDispatch().editEntityRecord('postType', 'wp_template', 1, {
            title: 'x',
        });

        expect(
            coreSelect().hasEditsForEntityRecord('postType', 'wp_template', 1),
        ).toBe(true);

        coreDispatch().clearEntityRecordEdits('postType', 'wp_template', 1);

        expect(
            coreSelect().hasEditsForEntityRecord('postType', 'wp_template', 1),
        ).toBe(false);
    });
});

describe('core-data-shim fetch → cache', () => {
    it('fetchEntityRecord GETs the configured URL and caches the response', async () => {
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse({ id: 7, slug: 'home', title: 'Home' }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = (await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_template',
            7,
        )) as EntityRecord | null;

        expect(record).toEqual({ id: 7, slug: 'home', title: 'Home' });
        expect(calls[0]?.url).toBe('/api/templates/7');
        expect(calls[0]?.init.method).toBe('GET');

        expect(
            coreSelect().getEntityRecord('postType', 'wp_template', 7),
        ).toEqual({ id: 7, slug: 'home', title: 'Home' });
    });

    it('fetchEntityRecord does not invalidate a sibling list-query cache (Keystone #57)', async () => {
        // Seed the per_page=-1 list cache the nav-block overlay panel
        // depends on. Then resolve a single record by composite id —
        // the path the panel hits when looking up the selected
        // overlay's record. The list cache must survive that resolver
        // dispatch; before the fix it was wiped, the overlay panel
        // briefly saw an empty filtered list, and Gutenberg's "no
        // overlays exist" prominent Create button replaced the
        // dropdown.
        const seeded: readonly EntityRecord[] = [
            { id: 8, slug: 'mobile', area: 'navigation-overlay', theme: 'jmwd-default' },
            { id: 9, slug: 'site', area: 'navigation-overlay', theme: 'jmwd-default' },
        ];

        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_template_part',
            seeded,
            { per_page: -1 },
            2,
            1,
        );

        const { fetcher } = mockFetcher(async () =>
            jsonResponse({
                data: [{ id: 8, slug: 'mobile', area: 'navigation-overlay', theme: 'jmwd-default' }],
            }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        // Composite id triggers the slug+theme list-query branch of
        // fetchEntityRecord, which dispatches receiveEntityRecords with
        // no query — the exact code path the bug hit.
        await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_template_part',
            'jmwd-default//mobile',
        );

        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toHaveLength(2);
    });

    it('saveEntityRecord re-triggers the list resolver so newly-created records appear in cached lists without a refresh (Keystone #57)', async () => {
        // Mirrors the Create Overlay flow: a `per_page=-1` list is
        // already cached, the user creates a new overlay via the panel
        // (saveEntityRecord), and the dropdown re-renders. Before the
        // resolver-invalidation half of the fix, the cached query got
        // wiped but `hasFinishedResolution` stayed true, so the next
        // read returned the empty wiped cache forever — until the user
        // refreshed the page.
        const listFetcher = vi.fn(async (url: string) => {
            if (url.includes('per_page=-1')) {
                return jsonResponse({
                    data: [
                        { id: 8, slug: 'mobile', area: 'navigation-overlay', theme: 'jmwd-default' },
                    ],
                });
            }

            // Save: POST returns the created record.
            return jsonResponse({
                id: 9,
                slug: 'site',
                area: 'navigation-overlay',
                theme: 'jmwd-default',
            });
        });

        configureCoreDataShim({ apiBase: '/api', fetcher: listFetcher });

        // Prime the list cache so we can observe the post-save behavior.
        await coreDispatch().fetchEntityRecords('postType', 'wp_template_part', {
            per_page: -1,
        });

        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toHaveLength(1);

        // Save a new record (the Create Overlay code path).
        await coreDispatch().saveEntityRecord('postType', 'wp_template_part', {
            slug: 'site',
            area: 'navigation-overlay',
            theme: 'jmwd-default',
        });

        // Sanity: the new record was added to items.
        expect(
            coreSelect().getEntityRecord('postType', 'wp_template_part', 9),
        ).toMatchObject({ id: 9, slug: 'site' });

        // The resolver framework should now consider the list
        // unresolved (invalidateResolutionForStoreSelector ran during
        // the save), so the next read re-fires the resolver.
        expect(
            coreSelect().hasFinishedResolution('getEntityRecords', [
                'postType',
                'wp_template_part',
                { per_page: -1 },
            ]),
        ).toBe(false);

        // End-to-end: a fresh `resolveSelect` call must refetch the
        // list. Stub the fetcher so the refetch returns BOTH records
        // (the seeded one + the freshly saved one). Counting list
        // fetches gives us hard evidence that the resolver ran again.
        let listFetchCount = 0;

        configureCoreDataShim({
            apiBase: '/api',
            fetcher: vi.fn(async (url: string) => {
                if (url.includes('per_page=-1')) {
                    listFetchCount += 1;

                    return jsonResponse({
                        data: [
                            { id: 8, slug: 'mobile', area: 'navigation-overlay', theme: 'jmwd-default' },
                            { id: 9, slug: 'site', area: 'navigation-overlay', theme: 'jmwd-default' },
                        ],
                    });
                }

                return jsonResponse({}, 500);
            }),
        });

        await coreResolveSelect().getEntityRecords(
            'postType',
            'wp_template_part',
            { per_page: -1 },
        );

        expect(listFetchCount).toBe(1);
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part', {
                per_page: -1,
            }),
        ).toHaveLength(2);
    });

    it('fetchEntityRecord swallows network errors and returns null', async () => {
        const { fetcher } = mockFetcher(async () => jsonResponse({}, 500));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_template',
            7,
        );

        expect(record).toBeNull();
        expect(
            coreSelect().getEntityRecord('postType', 'wp_template', 7),
        ).toBeNull();
    });

    it('fetchEntityRecord synthesizes a missing-record placeholder on 404 for a numeric id (#419)', async () => {
        // Entity-backed core blocks (`core/navigation`, `core/post-*`,
        // `core/template-part`) crash through to the editor's error
        // boundary when `useEntityRecord` returns null for a ref that
        // points at a deleted / never-seeded record. The shim
        // synthesizes a `{id, _missing: true, status: 'missing'}`
        // placeholder on 404 so the block's null-check passes and
        // it can render its own missing-state UI instead.
        const { fetcher } = mockFetcher(async () => jsonResponse({}, 404));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_navigation',
            21,
        );

        expect(record).toEqual({
            id: 21,
            [MISSING_RECORD_MARKER]: true,
            status: 'missing',
        });

        // Cached for subsequent reads — including `getEditedEntityRecord`,
        // which is what block-library's `useNavigationMenu` reads to
        // flip its `isNavigationMenuMissing` flag (status !== 'publish'
        // / 'draft' → missing).
        expect(
            coreSelect().getEntityRecord('postType', 'wp_navigation', 21),
        ).toMatchObject({ id: 21, [MISSING_RECORD_MARKER]: true });
        expect(
            coreSelect().getEditedEntityRecord(
                'postType',
                'wp_navigation',
                21,
            ),
        ).toMatchObject({ status: 'missing' });
    });

    it('fetchEntityRecord falls back to null for non-404 errors (#419)', async () => {
        // 5xx / network failures are transient — the cache must stay
        // empty so the resolver remains retryable rather than poisoned
        // with a permanent missing-record sentinel.
        const { fetcher } = mockFetcher(async () => jsonResponse({}, 503));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_navigation',
            42,
        );

        expect(record).toBeNull();
        expect(
            coreSelect().getEntityRecord('postType', 'wp_navigation', 42),
        ).toBeNull();
    });

    it('fetchEntityRecord synthesizes a missing-record placeholder when a composite-id lookup returns no matches (#419)', async () => {
        // `core/template-part` looks up its referenced part by the
        // composite `<theme>//<slug>` id. The shim bridges this through
        // the index endpoint with a theme+slug filter; an empty list
        // response means the referenced part isn't on the server.
        // Same placeholder treatment as the numeric-id 404 path so the
        // block doesn't crash on a null record.
        const { fetcher } = mockFetcher(async () =>
            jsonResponse({ data: [], meta: { total: 0, last_page: 1 } }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_template_part',
            'artisanpack-base//missing-part',
        );

        expect(record).toEqual({
            id: 'artisanpack-base//missing-part',
            [MISSING_RECORD_MARKER]: true,
            status: 'missing',
        });
        expect(
            coreSelect().getEntityRecord(
                'postType',
                'wp_template_part',
                'artisanpack-base//missing-part',
            ),
        ).toMatchObject({ [MISSING_RECORD_MARKER]: true });
    });

    it('clears a stale composite-id missing placeholder when the real record later lands (#419)', async () => {
        // After a composite-id lookup synthesizes a `_missing`
        // placeholder under `items[<theme>//<slug>]`, a subsequent
        // fetch that returns the real record stores it under the
        // numeric primary key and registers a composite alias. Without
        // explicit invalidation, `selectEntityRecord`'s direct-lookup
        // path would keep returning the stale placeholder for the
        // composite id.
        let respondWithMissing = true;
        const { fetcher } = mockFetcher(async () => {
            if (respondWithMissing) {
                return jsonResponse({
                    data: [],
                    meta: { total: 0, last_page: 1 },
                });
            }

            return jsonResponse({
                data: [
                    {
                        id: 7,
                        slug: 'header',
                        theme: 'artisanpack-base',
                        title: { rendered: 'Header' },
                    },
                ],
                meta: { total: 1, last_page: 1 },
            });
        });

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const compositeId = 'artisanpack-base//header';

        const missing = await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_template_part',
            compositeId,
        );

        expect(missing).toMatchObject({ [MISSING_RECORD_MARKER]: true });

        // Now the real record lands (e.g. via a seeder or a refresh).
        respondWithMissing = false;

        await coreDispatch().fetchEntityRecord(
            'postType',
            'wp_template_part',
            compositeId,
        );

        const resolved = coreSelect().getEntityRecord(
            'postType',
            'wp_template_part',
            compositeId,
        );

        // The composite-id lookup now resolves to the real record
        // through the alias map; the stale placeholder is gone.
        expect(resolved).toMatchObject({ id: 7, slug: 'header' });
        expect(resolved).not.toHaveProperty(MISSING_RECORD_MARKER);
    });

    it('RestRequestError carries the response status (#419)', () => {
        // Callers (notably `fetchEntityRecord`) need to distinguish a
        // 404 from a 5xx so they can decide whether to synthesize a
        // missing-record placeholder or leave the cache empty for a
        // retry. The structured error class exposes `.status` for that.
        const error = new RestRequestError(404, '/api/menus/21', 'GET');

        expect(error).toBeInstanceOf(Error);
        expect(error.status).toBe(404);
        expect(error.url).toBe('/api/menus/21');
        expect(error.method).toBe('GET');
        expect(error.name).toBe('RestRequestError');
    });

    it('fetchEntityRecords accepts a Laravel { data, meta } envelope', async () => {
        const { fetcher } = mockFetcher(async () =>
            jsonResponse({
                data: [
                    { id: 1, slug: 'one' },
                    { id: 2, slug: 'two' },
                ],
                meta: { total: 12, last_page: 3 },
            }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const records = (await coreDispatch().fetchEntityRecords(
            'postType',
            'wp_template',
        )) as readonly EntityRecord[];

        expect(records).toHaveLength(2);
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template', null),
        ).toHaveLength(2);
        expect(
            coreSelect().getEntityRecordsTotalItems(
                'postType',
                'wp_template',
                null,
            ),
        ).toBe(12);
        expect(
            coreSelect().getEntityRecordsTotalPages(
                'postType',
                'wp_template',
                null,
            ),
        ).toBe(3);
    });

    it('fetchEntityRecords returns [] when the endpoint is missing', async () => {
        const { fetcher } = mockFetcher(async () => jsonResponse({}, 404));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const records = await coreDispatch().fetchEntityRecords(
            'postType',
            'wp_template',
        );

        expect(records).toEqual([]);
    });

    it('is a no-op for entity kinds that are not registered', async () => {
        const { fetcher, calls } = mockFetcher(async () => jsonResponse({}));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreDispatch().fetchEntityRecord(
            'root',
            'bogus',
            1,
        );

        expect(record).toBeNull();
        expect(calls).toHaveLength(0);
    });
});

describe('core-data-shim resolver auto-resolution', () => {
    it('resolves getEntityRecord by triggering fetchEntityRecord on first read', async () => {
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse({ id: 9, slug: 'home' }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreResolveSelect().getEntityRecord(
            'postType',
            'wp_template',
            9,
        );

        expect(record).toEqual({ id: 9, slug: 'home' });
        expect(calls).toHaveLength(1);
        expect(calls[0]?.url).toBe('/api/templates/9');
        expect(
            coreSelect().hasFinishedResolution('getEntityRecord', [
                'postType',
                'wp_template',
                9,
            ]),
        ).toBe(true);
    });

    it('resolves getEntityRecords by triggering fetchEntityRecords on first read', async () => {
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse([
                { id: 1, slug: 'header' },
                { id: 2, slug: 'footer' },
            ]),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const records = await coreResolveSelect().getEntityRecords(
            'postType',
            'wp_template_part',
            null,
        );

        expect(records).toHaveLength(2);
        expect(calls).toHaveLength(1);
        expect(calls[0]?.url).toBe('/api/template-parts');
        expect(
            coreSelect().hasFinishedResolution('getEntityRecords', [
                'postType',
                'wp_template_part',
                null,
            ]),
        ).toBe(true);
    });

    it('does not refetch a resolved tuple on subsequent reads', async () => {
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse({ id: 11, slug: 'page' }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        await coreResolveSelect().getEntityRecord(
            'postType',
            'wp_template',
            11,
        );
        await coreResolveSelect().getEntityRecord(
            'postType',
            'wp_template',
            11,
        );

        expect(calls).toHaveLength(1);
    });

    it('keeps an empty cache when the resolver hits a 404', async () => {
        const { fetcher } = mockFetcher(async () => jsonResponse({}, 404));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const records = await coreResolveSelect().getEntityRecords(
            'postType',
            'wp_navigation',
            null,
        );

        expect(records).toEqual([]);
        expect(
            coreSelect().hasFinishedResolution('getEntityRecords', [
                'postType',
                'wp_navigation',
                null,
            ]),
        ).toBe(true);
    });

    it('resolves a composite <theme>//<slug> id via the index endpoint', async () => {
        // Block-library reads `getEntityRecord(kind, 'wp_template_part',
        // '<theme>//<slug>')` for saved template-part references. Our
        // REST `show` route only accepts numeric ids, so the resolver
        // falls back to listing the index filtered by theme+slug.
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse({
                data: [
                    {
                        id: 1,
                        slug: 'footer',
                        theme: 'artisanpack-base',
                        title: { rendered: 'Footer' },
                        content: { raw: '<!-- wp:paragraph -->', blocks: [] },
                    },
                ],
                meta: { total: 1, last_page: 1 },
            }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreResolveSelect().getEntityRecord(
            'postType',
            'wp_template_part',
            'artisanpack-base//footer',
        );

        expect(record).toMatchObject({ id: 1, slug: 'footer' });
        expect(calls).toHaveLength(1);
        expect(calls[0]?.url).toBe(
            '/api/template-parts?theme=artisanpack-base&slug=footer',
        );
        // Both the numeric id and the composite key resolve to the
        // same record so subsequent reads hit the cache regardless of
        // which form the caller uses.
        expect(
            coreSelect().getEntityRecord('postType', 'wp_template_part', 1),
        ).toMatchObject({ id: 1, slug: 'footer' });
        expect(
            coreSelect().getEntityRecord(
                'postType',
                'wp_template_part',
                'artisanpack-base//footer',
            ),
        ).toMatchObject({ id: 1, slug: 'footer' });
    });

    it('resolves getEditedEntityRecord through the same fetch path', async () => {
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse({
                data: [
                    {
                        id: 2,
                        slug: 'header',
                        theme: 'artisanpack-base',
                        title: { rendered: 'Header' },
                        content: { raw: '<!-- wp:site-title /-->', blocks: [] },
                    },
                ],
                meta: { total: 1, last_page: 1 },
            }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const record = await coreResolveSelect().getEditedEntityRecord(
            'postType',
            'wp_template_part',
            'artisanpack-base//header',
        );

        expect(record).toMatchObject({ id: 2, slug: 'header' });
        expect(calls).toHaveLength(1);
        expect(
            coreSelect().hasFinishedResolution('getEditedEntityRecord', [
                'postType',
                'wp_template_part',
                'artisanpack-base//header',
            ]),
        ).toBe(true);
    });

    it('does not double-count composite-aliased records in list selectors', () => {
        // Records with theme+slug are mirrored under a `<theme>//<slug>`
        // alias for upstream lookups, but the alias must not show up as
        // a separate item in `getEntityRecords` / totals — otherwise
        // pickers would render duplicates.
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [
            {
                id: 1,
                slug: 'footer',
                theme: 'artisanpack-base',
                title: { rendered: 'Footer' },
            },
            {
                id: 2,
                slug: 'header',
                theme: 'artisanpack-base',
                title: { rendered: 'Header' },
            },
        ]);

        // Composite lookups still resolve.
        expect(
            coreSelect().getEntityRecord(
                'postType',
                'wp_template_part',
                'artisanpack-base//footer',
            ),
        ).toMatchObject({ id: 1, slug: 'footer' });

        // List + total reflect only primaries.
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template_part'),
        ).toHaveLength(2);
        expect(
            coreSelect().getEntityRecordsTotalItems(
                'postType',
                'wp_template_part',
            ),
        ).toBe(2);
    });

    it('drops composite aliases when the primary record is removed', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [
            {
                id: 1,
                slug: 'footer',
                theme: 'artisanpack-base',
                title: { rendered: 'Footer' },
            },
        ]);

        expect(
            coreSelect().getEntityRecord(
                'postType',
                'wp_template_part',
                'artisanpack-base//footer',
            ),
        ).toMatchObject({ id: 1 });

        coreDispatch().removeEntityRecord('postType', 'wp_template_part', 1);

        expect(
            coreSelect().getEntityRecord('postType', 'wp_template_part', 1),
        ).toBeNull();
        expect(
            coreSelect().getEntityRecord(
                'postType',
                'wp_template_part',
                'artisanpack-base//footer',
            ),
        ).toBeNull();
    });
});

describe('core-data-shim list-cache invalidation', () => {
    it('drops filter-query caches when a save-path receive fires', async () => {
        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_template',
            [
                { id: 1, slug: 'one' },
                { id: 2, slug: 'two' },
            ],
            { status: 'publish' },
            2,
            1,
        );

        // Filter-query slot is populated.
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template', {
                status: 'publish',
            }),
        ).toHaveLength(2);

        // A save-path receive (no query) lands.
        coreDispatch().receiveEntityRecords('postType', 'wp_template', [
            { id: 3, slug: 'three' },
        ]);

        // Filter-query slot is invalidated — next list render must
        // refetch before showing a filtered view.
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template', {
                status: 'publish',
            }),
        ).toEqual([]);

        // But `getEntityRecords(..., null)` still returns the live items
        // (1, 2, 3) because the empty-slot falls back to `items`.
        expect(
            coreSelect().getEntityRecords('postType', 'wp_template', null),
        ).toHaveLength(3);
        expect(
            coreSelect().getEntityRecordsTotalItems(
                'postType',
                'wp_template',
                null,
            ),
        ).toBe(3);
    });

    it('drops query caches and refreshes totals after delete', async () => {
        coreDispatch().receiveEntityRecords(
            'postType',
            'wp_block',
            [
                { id: 1, slug: 'one' },
                { id: 2, slug: 'two' },
            ],
            null,
            12,
            3,
        );

        expect(
            coreSelect().getEntityRecordsTotalItems('postType', 'wp_block', null),
        ).toBe(12);

        const { fetcher } = mockFetcher(
            async () => new Response(null, { status: 204 }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        await coreDispatch().deleteEntityRecord('postType', 'wp_block', 1);

        // Total is recomputed from live items — stale "12" no longer shown.
        expect(
            coreSelect().getEntityRecordsTotalItems('postType', 'wp_block', null),
        ).toBe(1);
        expect(
            coreSelect().getEntityRecords('postType', 'wp_block', null),
        ).toHaveLength(1);
    });
});

describe('core-data-shim save round-trip', () => {
    it('POSTs a new record and caches the response', async () => {
        const { fetcher, calls } = mockFetcher(async (_url, init) => {
            expect(init.method).toBe('POST');
            const body = init.body as string;

            expect(JSON.parse(body)).toEqual({ slug: 'new', title: 'New' });

            return jsonResponse({ id: 99, slug: 'new', title: 'New' });
        });

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const saved = (await coreDispatch().saveEntityRecord(
            'postType',
            'wp_template',
            { slug: 'new', title: 'New' },
        )) as EntityRecord | null;

        expect(saved).toEqual({ id: 99, slug: 'new', title: 'New' });
        expect(calls[0]?.url).toBe('/api/templates');
        expect(
            coreSelect().getEntityRecord('postType', 'wp_template', 99),
        ).toEqual({ id: 99, slug: 'new', title: 'New' });
    });

    it('saveEditedEntityRecord PUTs merged record and clears edits', async () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_template', [
            { id: 3, slug: 'page', title: 'Old' },
        ]);
        coreDispatch().editEntityRecord('postType', 'wp_template', 3, {
            title: 'Updated',
        });

        const { fetcher, calls } = mockFetcher(async (_url, init) => {
            expect(init.method).toBe('PUT');
            const body = JSON.parse(init.body as string) as EntityRecord;

            expect(body).toEqual({ id: 3, slug: 'page', title: 'Updated' });

            return jsonResponse({ id: 3, slug: 'page', title: 'Updated' });
        });

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const saved = (await coreDispatch().saveEditedEntityRecord(
            'postType',
            'wp_template',
            3,
        )) as EntityRecord | null;

        expect(saved).toEqual({ id: 3, slug: 'page', title: 'Updated' });
        expect(calls[0]?.url).toBe('/api/templates/3');
        expect(
            coreSelect().hasEditsForEntityRecord('postType', 'wp_template', 3),
        ).toBe(false);
        expect(
            coreSelect().getEntityRecord('postType', 'wp_template', 3),
        ).toEqual({ id: 3, slug: 'page', title: 'Updated' });
    });

    it('saveEditedEntityRecord PUTs even when no base record is cached', async () => {
        // Stage edits for a record that was never fetched — covers the
        // case where the UI is editing an id it knows by reference only.
        coreDispatch().editEntityRecord('postType', 'wp_template', 8, {
            title: 'Created from edits',
        });

        const { fetcher, calls } = mockFetcher(async (_url, init) => {
            expect(init.method).toBe('PUT');
            const body = JSON.parse(init.body as string) as EntityRecord;

            expect(body).toEqual({ id: 8, title: 'Created from edits' });

            return jsonResponse({ id: 8, title: 'Created from edits' });
        });

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const saved = await coreDispatch().saveEditedEntityRecord(
            'postType',
            'wp_template',
            8,
        );

        expect(saved).not.toBeNull();
        expect(calls[0]?.url).toBe('/api/templates/8');
    });

    it('retains edits when the save fails', async () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_template', [
            { id: 3, slug: 'page', title: 'Old' },
        ]);
        coreDispatch().editEntityRecord('postType', 'wp_template', 3, {
            title: 'Updated',
        });

        const { fetcher } = mockFetcher(async () => jsonResponse({}, 500));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const saved = await coreDispatch().saveEditedEntityRecord(
            'postType',
            'wp_template',
            3,
        );

        expect(saved).toBeNull();
        expect(
            coreSelect().hasEditsForEntityRecord('postType', 'wp_template', 3),
        ).toBe(true);
        expect(
            coreSelect().getLastEntitySaveError(
                'postType',
                'wp_template',
                3,
            ),
        ).not.toBeNull();
    });

    it('saveEditedEntityRecord bails out when there is no base record AND no staged edits (Keystone #48)', async () => {
        // `getEditedEntityRecord` returns `{}` for an unresolved
        // record so synchronous reads on the nav block don't crash on
        // `.status`. A pre-fix `saveEditedEntityRecord` would treat
        // that `{}` as a real edited record and PUT `{ id }` to the
        // server — a write that should never have happened. Confirm
        // the guard now bails before fetching.
        const { fetcher, calls } = mockFetcher(async () => jsonResponse({}));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const saved = await coreDispatch().saveEditedEntityRecord(
            'postType',
            'wp_template',
            999_999,
        );

        expect(saved).toBeNull();
        expect(calls).toHaveLength(0);
    });
});

describe('core-data-shim delete + evict', () => {
    it('DELETEs and evicts the cached record on success', async () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_block', [
            { id: 11, slug: 'hero', title: 'Hero' },
        ]);

        const { fetcher, calls } = mockFetcher(
            async () => new Response(null, { status: 204 }),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const ok = await coreDispatch().deleteEntityRecord(
            'postType',
            'wp_block',
            11,
        );

        expect(ok).toBe(true);
        expect(calls[0]?.url).toBe('/api/patterns/11');
        expect(calls[0]?.init.method).toBe('DELETE');
        expect(
            coreSelect().getEntityRecord('postType', 'wp_block', 11),
        ).toBeNull();
    });

    it('leaves the record in place when the DELETE fails', async () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_block', [
            { id: 11, slug: 'hero' },
        ]);

        const { fetcher } = mockFetcher(async () => jsonResponse({}, 500));

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const ok = await coreDispatch().deleteEntityRecord(
            'postType',
            'wp_block',
            11,
        );

        expect(ok).toBe(false);
        expect(
            coreSelect().getEntityRecord('postType', 'wp_block', 11),
        ).not.toBeNull();
        expect(
            coreSelect().getLastEntityDeleteError(
                'postType',
                'wp_block',
                11,
            ),
        ).not.toBeNull();
    });
});

describe('core-data-shim global styles', () => {
    it('stores and returns the base global-styles record', () => {
        const base = {
            version: 3,
            settings: { color: { palette: [] } },
        };

        coreDispatch().receiveGlobalStylesBase(base);

        expect(coreSelect().__experimentalGlobalStylesBaseStyles()).toEqual(base);
    });

    it('stores and returns the current global-styles record id', () => {
        expect(coreSelect().__experimentalGetCurrentGlobalStylesId()).toBeNull();

        coreDispatch().receiveCurrentGlobalStylesId(42);

        expect(coreSelect().__experimentalGetCurrentGlobalStylesId()).toBe(42);
    });

    it('saves user global-styles edits as PUTs through the entity pipeline', async () => {
        coreDispatch().receiveEntityRecords('root', 'globalStyles', [
            { id: 1, settings: {}, styles: {} },
        ]);
        coreDispatch().editEntityRecord('root', 'globalStyles', 1, {
            styles: { color: { background: '#fff' } },
        });

        const { fetcher, calls } = mockFetcher(async (_url, init) => {
            expect(init.method).toBe('PUT');

            return jsonResponse({
                id: 1,
                settings: {},
                styles: { color: { background: '#fff' } },
            });
        });

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const saved = (await coreDispatch().saveEditedEntityRecord(
            'root',
            'globalStyles',
            1,
        )) as EntityRecord | null;

        expect(saved).not.toBeNull();
        expect(calls[0]?.url).toBe('/api/global-styles/1');
    });
});

describe('core-data-shim hooks', () => {
    function renderHook<T>(hook: () => T): T {
        let captured!: T;
        function Probe() {
            captured = hook();
            return null;
        }
        render(<Probe />);
        return captured;
    }

    it('useEntityRecord returns an empty, resolved record', () => {
        const result = renderHook(() => useEntityRecord());
        expect(result).toMatchObject({
            record: null,
            editedRecord: null,
            hasEdits: false,
            hasResolved: true,
            isResolving: false,
        });
        expect(typeof result.edit).toBe('function');
        expect(typeof result.save).toBe('function');
    });

    it('useEntityRecords returns an empty, resolved list', () => {
        const result = renderHook(() => useEntityRecords());
        expect(result.records).toEqual([]);
        expect(result.hasResolved).toBe(true);
        expect(result.isResolving).toBe(false);
        expect(result.totalItems).toBe(0);
        expect(result.totalPages).toBe(0);
    });

    it('useEntityProp returns [undefined, noop setter, undefined] when args are missing', () => {
        const [value, setter, rawValue] = renderHook(() => useEntityProp());
        expect(value).toBeUndefined();
        expect(rawValue).toBeUndefined();
        expect(typeof setter).toBe('function');
        // Calling the noop setter must not throw or dispatch — we don't
        // know which entity/prop the caller meant to edit.
        expect(() => setter(undefined as unknown as never)).not.toThrow();
    });

    it('useEntityProp reads the edited prop (flattened raw shape) and the full prop separately', () => {
        coreDispatch().receiveEntityRecords('postType', 'post', [
            {
                id: 1,
                title: { rendered: 'Hello World', raw: 'Hello World' },
                status: 'publish',
                type: 'post',
            },
        ]);

        const [edited, setter, full] = renderHook(() =>
            useEntityProp<string | { rendered: string; raw: string }>(
                'postType',
                'post',
                'title',
                1,
            ),
        );

        expect(edited).toBe('Hello World');
        expect(full).toEqual({ rendered: 'Hello World', raw: 'Hello World' });
        expect(typeof setter).toBe('function');
    });

    it('useEntityProp setter dispatches editEntityRecord and surfaces the edit on next read', () => {
        coreDispatch().receiveEntityRecords('postType', 'post', [
            {
                id: 1,
                title: { rendered: 'Hello World', raw: 'Hello World' },
                status: 'publish',
                type: 'post',
            },
        ]);

        const captured = renderHook(() =>
            useEntityProp<string>('postType', 'post', 'title', 1),
        );

        act(() => {
            captured[1]('Updated Title');
        });

        const [edited] = renderHook(() =>
            useEntityProp<string>('postType', 'post', 'title', 1),
        );

        expect(edited).toBe('Updated Title');
        expect(
            coreSelect().getEntityRecordEdits('postType', 'post', 1),
        ).toEqual({ title: 'Updated Title' });
    });

    it('useEntityProp re-renders the same consumer when the resolver populates the cache', async () => {
        // The previous renderHook-based test mounted a fresh React tree
        // after the fetch settled. Real consumers (e.g. core/post-title's
        // Edit) mount once and rely on useSelect's subscription to surface
        // the populated value on the next render. This test exercises the
        // subscription path against a single mounted Probe.
        const { fetcher } = mockFetcher(async () =>
            jsonResponse({
                id: 1,
                slug: 'tinker-test',
                title: { rendered: 'Tinker Test', raw: 'Tinker Test' },
                status: 'publish',
                type: 'post',
            }),
        );

        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });

        let captured: [unknown, unknown, unknown] = [
            undefined,
            undefined,
            undefined,
        ];

        function Probe(): null {
            captured = useEntityProp<string>('postType', 'post', 'title', 1);
            return null;
        }

        render(<Probe />);

        expect(captured[0]).toBeUndefined();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });

        expect(captured[0]).toBe('Tinker Test');
    });

    it('useEntityProp resolves the entity through the REST resolver and surfaces the prop after fetch', async () => {
        // Mirrors how `core/post-title` reads `title` from a cms-framework
        // post in the editor canvas: nothing pre-populates the cache, so
        // the hook has to trigger the `getEntityRecord` resolver, fetch
        // the record, and re-render with the populated value.
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse({
                id: 1,
                slug: 'tinker-test',
                title: { rendered: 'Tinker Test', raw: 'Tinker Test' },
                status: 'publish',
                type: 'post',
            }),
        );

        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });

        const initial = renderHook(() =>
            useEntityProp<string>('postType', 'post', 'title', 1),
        );

        expect(initial[0]).toBeUndefined();

        // Settle the resolver thunk + receive dispatch.
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });

        const settled = renderHook(() =>
            useEntityProp<string>('postType', 'post', 'title', 1),
        );

        expect(calls.length).toBeGreaterThanOrEqual(1);
        expect(calls[0]?.url).toBe('/visual-editor/api/posts/1');
        expect(settled[0]).toBe('Tinker Test');
    });

    it('useEntityProp falls back to ambient EntityProvider id when the id arg is omitted', () => {
        coreDispatch().receiveEntityRecords('postType', 'post', [
            {
                id: 7,
                title: { rendered: 'Ambient', raw: 'Ambient' },
                status: 'publish',
                type: 'post',
            },
        ]);

        let captured: [unknown, unknown, unknown] = [
            undefined,
            undefined,
            undefined,
        ];

        function Probe(): null {
            captured = useEntityProp('postType', 'post', 'title');
            return null;
        }

        render(
            <EntityProvider kind="postType" name="post" id={7}>
                <Probe />
            </EntityProvider>,
        );

        expect(captured[0]).toBe('Ambient');
    });

    it('useEntityBlockEditor returns an empty block list and stable setters', () => {
        const [blocks, onInput, onChange] = renderHook(() =>
            useEntityBlockEditor(),
        );
        expect(blocks).toEqual([]);
        expect(typeof onInput).toBe('function');
        expect(typeof onChange).toBe('function');
    });

    it('useEntityRecord surfaces a cached record so core/block can find synced patterns', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_block', [
            {
                id: 42,
                slug: 'hero',
                title: { raw: 'Hero', rendered: 'Hero' },
                content: { raw: '', blocks: [] },
                synced: true,
                status: 'publish',
                type: 'wp_block',
            },
        ]);

        const result = renderHook(() =>
            useEntityRecord('postType', 'wp_block', 42),
        );

        expect(result.hasResolved).toBe(true);
        expect(result.record).toMatchObject({ id: 42, slug: 'hero' });
        expect(result.editedRecord).toMatchObject({ id: 42, slug: 'hero' });
    });

    it('useEntityBlockEditor returns the cached record content.blocks', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_block', [
            {
                id: 7,
                content: {
                    raw: '<!-- wp:paragraph -->',
                    blocks: [
                        { name: 'core/paragraph', clientId: 'a' },
                        { name: 'core/heading', clientId: 'b' },
                    ],
                },
                title: { raw: '', rendered: '' },
                synced: true,
                status: 'publish',
                type: 'wp_block',
            },
        ]);

        const [blocks] = renderHook(() =>
            useEntityBlockEditor('postType', 'wp_block', { id: 7 }),
        );

        expect(blocks).toHaveLength(2);
        expect(blocks[0]).toMatchObject({ name: 'core/paragraph' });
    });

    it('useEntityBlockEditor decorates server blocks with clientId / isValid', () => {
        // Block-editor's `useInnerBlocksProps` rejects blocks that
        // lack a `clientId`; the shim's REST envelope ships only
        // `{name, attributes, innerBlocks}` so the hook must decorate
        // each block before handing them off (#395).
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [
            {
                id: 1,
                slug: 'footer',
                theme: 'artisanpack-base',
                content: {
                    raw: '<!-- wp:group -->',
                    blocks: [
                        {
                            name: 'core/group',
                            attributes: { tagName: 'footer' },
                            innerBlocks: [
                                {
                                    name: 'core/paragraph',
                                    attributes: { content: 'Hi' },
                                    innerBlocks: [],
                                },
                            ],
                        },
                    ],
                },
            },
        ]);

        const [blocks] = renderHook(() =>
            useEntityBlockEditor('postType', 'wp_template_part', { id: 1 }),
        ) as readonly Array<{
            name: string;
            clientId: string;
            isValid: true;
            attributes: Record<string, unknown>;
            innerBlocks: ReadonlyArray<{ name: string; clientId: string }>;
        }>;

        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe('core/group');
        expect(blocks[0].isValid).toBe(true);
        expect(typeof blocks[0].clientId).toBe('string');
        expect(blocks[0].clientId.length).toBeGreaterThan(0);
        expect(blocks[0].attributes).toEqual({ tagName: 'footer' });
        expect(blocks[0].innerBlocks).toHaveLength(1);
        expect(blocks[0].innerBlocks[0].name).toBe('core/paragraph');
        expect(typeof blocks[0].innerBlocks[0].clientId).toBe('string');
    });

    it('useEntityBlockEditor parses a flattened string `content` payload (Keystone #48)', () => {
        // `getEditedEntityRecord` runs `flattenRawProperties` over
        // the cached record, so a server envelope of
        // `{ content: { raw, blocks } }` becomes a plain string by
        // the time the nav block reads it. For `wp_navigation` we
        // need to parse that string into the block tree the canvas
        // renders — otherwise the picker shows "is empty" even when
        // the menu has items.
        coreDispatch().receiveEntityRecords('postType', 'wp_navigation', [
            {
                id: 42,
                slug: 'primary',
                title: { raw: 'Primary', rendered: 'Primary' },
                status: 'publish',
                type: 'wp_navigation',
                // Bare string `content` — exercises the flattened
                // fallback branch (we now skip flattening for the
                // canonical wp_navigation read, but the parser still
                // has to cope when an upstream caller hands us the
                // string directly).
                content:
                    '<!-- wp:navigation-link {"label":"Home","url":"/"} /-->\n' +
                    '<!-- wp:navigation-submenu {"label":"About"} -->\n' +
                    '<!-- wp:navigation-link {"label":"Team"} /-->\n' +
                    '<!-- /wp:navigation-submenu -->',
            },
        ]);

        const [blocks] = renderHook(() =>
            useEntityBlockEditor('postType', 'wp_navigation', { id: 42 }),
        ) as readonly Array<{
            name: string;
            clientId: string;
            attributes: Record<string, unknown>;
            innerBlocks: ReadonlyArray<{ name: string; attributes: Record<string, unknown> }>;
        }>;

        expect(blocks).toHaveLength(2);
        expect(blocks[0].name).toBe('core/navigation-link');
        expect(blocks[0].attributes).toEqual({ label: 'Home', url: '/' });
        expect(typeof blocks[0].clientId).toBe('string');

        expect(blocks[1].name).toBe('core/navigation-submenu');
        expect(blocks[1].attributes).toEqual({ label: 'About' });
        expect(blocks[1].innerBlocks).toHaveLength(1);
        expect(blocks[1].innerBlocks[0].name).toBe('core/navigation-link');
        expect(blocks[1].innerBlocks[0].attributes).toEqual({ label: 'Team' });
    });

    it('flattens {raw, rendered} fields on getRawEntityRecord and getEditedEntityRecord', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_block', [
            {
                id: 88,
                slug: 'flatten',
                title: { raw: 'Flatten Me', rendered: 'Flatten Me' },
                content: {
                    raw: '<!-- raw content -->',
                    blocks: [],
                },
                synced: true,
                status: 'publish',
                type: 'wp_block',
            },
        ]);

        const raw = coreSelect().getRawEntityRecord(
            'postType',
            'wp_block',
            88,
        ) as { title?: unknown; content?: unknown };

        expect(raw.title).toBe('Flatten Me');
        // `content` is intentionally preserved as the `{ raw, blocks }`
        // object shape — Gutenberg's `core/navigation` edit reads
        // `editedRecord.content.raw` directly, and flattening it to a
        // bare string broke the nav block's load path (Keystone #48).
        expect(raw.content).toEqual({
            raw: '<!-- raw content -->',
            blocks: [],
        });

        const edited = coreSelect().getEditedEntityRecord(
            'postType',
            'wp_block',
            88,
        ) as { title?: unknown };

        expect(edited.title).toBe('Flatten Me');
    });

    it('getEditedEntityRecord returns {} instead of null when nothing is cached (Keystone #48)', () => {
        // Gutenberg's `core/navigation` block (use-navigation-menu.mjs)
        // reads `record.status === "publish"` synchronously during
        // render. A `null` return crashed the block before the async
        // fetch resolver could settle. WP core returns `{}` in this
        // case; the shim now matches.
        const record = coreSelect().getEditedEntityRecord(
            'postType',
            'wp_navigation',
            999_999,
        );

        expect(record).toEqual({});
        // Belt-and-suspenders: `.status` is safely accessible.
        expect((record as { status?: string }).status).toBeUndefined();
    });

    it('useEntityRecords surfaces the list-cache and resolves through fetchEntityRecords', async () => {
        const { fetcher, calls } = mockFetcher(async () =>
            jsonResponse([
                { id: 1, slug: 'header', title: 'Header' },
                { id: 2, slug: 'footer', title: 'Footer' },
            ]),
        );

        configureCoreDataShim({ apiBase: '/api', fetcher });

        const initial = renderHook(() =>
            useEntityRecords('postType', 'wp_template_part', null),
        );

        expect(initial.records).toEqual([]);
        expect(initial.hasResolved).toBe(false);

        // Wait for the resolver thunk + receive dispatch to settle.
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });

        const settled = renderHook(() =>
            useEntityRecords('postType', 'wp_template_part', null),
        );

        expect(calls).toHaveLength(1);
        expect(calls[0]?.url).toBe('/api/template-parts');
        expect(settled.records).toHaveLength(2);
        expect(settled.hasResolved).toBe(true);
        expect(settled.totalItems).toBe(2);
    });

    it('useEntityRecord exposes staged edits via editedRecord + hasEdits', () => {
        // `useEntityRecord` returns the canonical cached record on
        // `record` and the cached + staged-edits merge on
        // `editedRecord`. Block-library reads `editedRecord` to render
        // optimistic UI before saves land; if the hook ignored edits
        // the canvas would flicker between the typed value and the
        // server's stale copy.
        coreDispatch().receiveEntityRecords('postType', 'wp_template', [
            { id: 5, slug: 'index', title: 'Index' },
        ]);

        const initial = renderHook(() =>
            useEntityRecord('postType', 'wp_template', 5),
        ) as {
            record: { title?: string } | null;
            editedRecord: { title?: string } | null;
            hasEdits: boolean;
        };

        expect(initial.record?.title).toBe('Index');
        expect(initial.editedRecord?.title).toBe('Index');
        expect(initial.hasEdits).toBe(false);

        coreDispatch().editEntityRecord('postType', 'wp_template', 5, {
            title: 'New Title',
        });

        const edited = renderHook(() =>
            useEntityRecord('postType', 'wp_template', 5),
        ) as {
            record: { title?: string } | null;
            editedRecord: { title?: string } | null;
            hasEdits: boolean;
        };

        expect(edited.record?.title).toBe('Index');
        expect(edited.editedRecord?.title).toBe('New Title');
        expect(edited.hasEdits).toBe(true);
    });

    it('useEntityRecord starts unresolved when it has to fetch a missing record', () => {
        // The hook fires `fetchEntityRecord` on a cache miss so synced
        // `core/block` references render a spinner instead of "Block
        // has been deleted" while the round-trip is in flight. The
        // initial sync render reports `hasResolved=false`; the actual
        // fetch lifecycle is exercised in integration tests that mock
        // the network, not here.
        const result = renderHook(() =>
            useEntityRecord('postType', 'wp_block', 12345),
        );

        expect(result.hasResolved).toBe(false);
        expect(result.record).toBeNull();
    });

    it('useResourcePermissions denies everything and reports resolved', () => {
        const perms = renderHook(() => useResourcePermissions());
        expect(perms).toEqual({
            canCreate: false,
            canUpdate: false,
            canDelete: false,
            isResolving: false,
        });
    });

    it('useEntityId reads from EntityProvider context', () => {
        function Probe() {
            const id = useEntityId();
            return <span data-testid="probe">{String(id ?? 'none')}</span>;
        }

        render(
            <EntityProvider kind="postType" name="page" id={7}>
                <Probe />
            </EntityProvider>,
        );

        expect(screen.getByTestId('probe').textContent).toBe('7');
    });

    it('useEntityId returns undefined outside EntityProvider', () => {
        function Probe() {
            const id = useEntityId();
            return <span data-testid="probe">{String(id ?? 'none')}</span>;
        }

        render(<Probe />);
        expect(screen.getByTestId('probe').textContent).toBe('none');
    });
});

describe('core-data-shim bootstrap', () => {
    it('re-registers the shim store only once across imports', () => {
        expect(store).toBeDefined();
        expect(select('core')).toBeDefined();
    });
});
