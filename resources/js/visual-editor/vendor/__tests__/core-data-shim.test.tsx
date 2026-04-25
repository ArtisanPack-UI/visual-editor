import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { dispatch, select } from '@wordpress/data';

import {
    DEFAULT_ENTITIES,
    EntityProvider,
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

function resetStore(): void {
    coreDispatch().reset();
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

beforeEach(() => {
    resetStore();
    __resetCoreDataShimConfig();
});

afterEach(() => {
    resetStore();
    __resetCoreDataShimConfig();
});

describe('core-data-shim entity registry', () => {
    it('registers the five V1 entities by default', () => {
        const names = DEFAULT_ENTITIES.map((entity) => `${entity.kind}|${entity.name}`);

        expect(names).toEqual([
            'postType|wp_template',
            'postType|wp_template_part',
            'postType|wp_navigation',
            'postType|wp_block',
            'root|globalStyles',
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
            ]),
        );
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

    it('reports resolution as finished and not in-flight by default', () => {
        expect(
            coreSelect().hasFinishedResolution('getEntityRecord', [
                'postType',
                'wp_template',
                1,
            ]),
        ).toBe(true);
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

    it('useEntityProp returns [undefined, setter, undefined]', () => {
        const [value, setter, rawValue] = renderHook(() => useEntityProp());
        expect(value).toBeUndefined();
        expect(rawValue).toBeUndefined();
        expect(typeof setter).toBe('function');
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
        expect(raw.content).toBe('<!-- raw content -->');

        const edited = coreSelect().getEditedEntityRecord(
            'postType',
            'wp_block',
            88,
        ) as { title?: unknown };

        expect(edited.title).toBe('Flatten Me');
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
