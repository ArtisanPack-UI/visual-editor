/**
 * Entity metadata flush in `usePersistence` (#992): custom entries save
 * through the `entry` endpoint, posts/pages through the G3 adapter, and
 * a saved title stays on the canvas once its edits are cleared.
 */

import { act, renderHook, waitFor } from '@testing-library/react';
import { dispatch, select } from '@wordpress/data';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    __resetCoreDataShimConfig,
    configureCoreDataShim,
} from '../../vendor/core-data-shim';
import { seedCurrentEntryRecord } from '../current-entry';
import { usePersistence, type EntityIdentity } from '../use-persistence';

type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;
type CoreSelect = Record<string, (...args: unknown[]) => unknown>;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;
const coreSelect = (): CoreSelect => select('core') as CoreSelect;

interface RecordedCall {
    url: string;
    method: string;
    body: unknown;
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'content-type': 'application/json' },
    });
}

function mockFetch(
    onPut: (call: RecordedCall) => Response | Promise<Response> = () => jsonResponse({})
): RecordedCall[] {
    const calls: RecordedCall[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const url = typeof input === 'string' || input instanceof URL ? String(input) : input.url;
            const method = (init?.method ?? 'GET').toUpperCase();
            const call: RecordedCall = {
                url,
                method,
                body: typeof init?.body === 'string' ? JSON.parse(init.body) : null,
            };

            calls.push(call);

            if (method === 'GET') {
                return jsonResponse({ id: 42, resource: 'packages', blocks: [], updated_at: null });
            }

            return onPut(call);
        })
    );

    return calls;
}

async function resetStore(): Promise<void> {
    configureCoreDataShim({
        apiBase: '/_drain_',
        fetcher: async () => new Response(null, { status: 503 }),
    });

    await new Promise((resolve) => setTimeout(resolve, 50));

    coreDispatch().reset();
    coreDispatch().invalidateResolutionForStore();
}

beforeEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
    configureCoreDataShim({
        apiBase: '/visual-editor/api',
        fetcher: async () => new Response(null, { status: 404 }),
    });
});

afterEach(async () => {
    vi.unstubAllGlobals();
    await resetStore();
    __resetCoreDataShimConfig();
});

async function renderReady(resource: string, entity: EntityIdentity) {
    const hook = renderHook(() =>
        usePersistence({
            apiBase: '/visual-editor/api',
            resource,
            id: '42',
            debounceMs: 10,
            entity,
        })
    );

    await waitFor(() => {
        expect(hook.result.current.loadStatus).toBe('ready');
    });

    return hook;
}

function seed(entity: EntityIdentity, values: { title: string; excerpt: string }): void {
    seedCurrentEntryRecord({ kind: 'postType', name: entity.name, id: entity.id }, values);
}

function editedTitle(name: string): unknown {
    return (coreSelect().getEditedEntityRecord('postType', name, 42) as Record<string, unknown>).title;
}

describe('usePersistence entity flush (#992)', () => {
    it('saves a custom entry title and excerpt through the entry endpoint', async () => {
        const calls = mockFetch();
        const entity: EntityIdentity = { kind: 'postType', name: 'package', id: 42, endpoint: 'entry' };

        seed(entity, { title: 'Old title', excerpt: 'Old excerpt' });

        const { result } = await renderReady('packages', entity);

        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 42, {
                title: 'New title',
                excerpt: 'New excerpt',
            });
            result.current.queueMetadataForSave();
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        const puts = calls.filter((call) => call.method === 'PUT');

        expect(puts).toHaveLength(1);
        expect(puts[0].url).toBe('/visual-editor/api/packages/42/entry');
        expect(puts[0].body).toEqual({ title: 'New title', excerpt: 'New excerpt' });
    });

    it('keeps the saved value on the canvas once the edits are cleared', async () => {
        mockFetch();
        const entity: EntityIdentity = { kind: 'postType', name: 'package', id: 42, endpoint: 'entry' };

        seed(entity, { title: 'Old title', excerpt: '' });

        const { result } = await renderReady('packages', entity);

        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 42, { title: 'New title' });
            result.current.queueMetadataForSave();
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        expect(coreSelect().hasEditsForEntityRecord('postType', 'package', 42)).toBe(false);
        expect(editedTitle('package')).toBe('New title');
        expect(
            (coreSelect().getEntityRecord('postType', 'package', 42) as Record<string, unknown>).title
        ).toEqual({ raw: 'New title', rendered: 'New title' });
    });

    it('keeps an edit made while the save was in flight for the next save', async () => {
        let releaseFirst: () => void = () => undefined;
        let putCount = 0;
        const calls = mockFetch(async () => {
            putCount += 1;

            if (putCount === 1) {
                await new Promise<void>((resolve) => {
                    releaseFirst = resolve;
                });
            }

            return jsonResponse({});
        });
        const entity: EntityIdentity = { kind: 'postType', name: 'package', id: 42, endpoint: 'entry' };

        seed(entity, { title: 'Old', excerpt: '' });

        const { result } = await renderReady('packages', entity);

        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 42, { title: 'First' });
            result.current.queueMetadataForSave();
        });

        await waitFor(() => {
            expect(putCount).toBe(1);
        });

        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 42, { title: 'Second' });
        });

        await act(async () => {
            releaseFirst();
        });

        await waitFor(() => {
            expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(2);
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        const puts = calls.filter((call) => call.method === 'PUT');

        expect(puts[1].body).toEqual({ title: 'Second' });
        expect(editedTitle('package')).toBe('Second');
    });

    it('does not retry a rejected metadata save in a loop', async () => {
        const calls = mockFetch(() => jsonResponse({ message: 'Invalid.' }, 422));
        const entity: EntityIdentity = { kind: 'postType', name: 'package', id: 42, endpoint: 'entry' };

        seed(entity, { title: 'Old', excerpt: '' });

        const { result } = await renderReady('packages', entity);

        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 42, { title: 'x'.repeat(300) });
            result.current.queueMetadataForSave();
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('error');
        });

        await new Promise((resolve) => setTimeout(resolve, 100));

        expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(1);
        expect(coreSelect().hasEditsForEntityRecord('postType', 'package', 42)).toBe(true);
    });

    it('keeps cached list queries when a save lands', async () => {
        mockFetch();
        const entity: EntityIdentity = { kind: 'postType', name: 'post', id: 42 };

        seed(entity, { title: 'Old post', excerpt: '' });
        coreDispatch().receiveEntityRecords(
            'postType',
            'post',
            [{ id: 42, title: { raw: 'Old post', rendered: 'Old post' } }, { id: 7, title: { raw: 'Other', rendered: 'Other' } }],
            { per_page: 10 }
        );

        const { result } = await renderReady('posts', entity);

        act(() => {
            coreDispatch().editEntityRecord('postType', 'post', 42, { title: 'New post' });
            result.current.queueMetadataForSave();
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        const listed = coreSelect().getEntityRecords('postType', 'post', { per_page: 10 }) as
            | Array<Record<string, unknown>>
            | null;

        expect(listed?.map((record) => record.id)).toEqual([42, 7]);
    });

    it('keeps posts on the G3 entity endpoint', async () => {
        const calls = mockFetch();
        const entity: EntityIdentity = { kind: 'postType', name: 'post', id: 42 };

        seed(entity, { title: 'Old post', excerpt: '' });

        const { result } = await renderReady('posts', entity);

        act(() => {
            coreDispatch().editEntityRecord('postType', 'post', 42, { title: 'New post' });
            result.current.queueMetadataForSave();
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        const puts = calls.filter((call) => call.method === 'PUT');

        expect(puts).toHaveLength(1);
        expect(puts[0].url).toBe('/visual-editor/api/posts/42');
        expect(editedTitle('post')).toBe('New post');
    });
});
