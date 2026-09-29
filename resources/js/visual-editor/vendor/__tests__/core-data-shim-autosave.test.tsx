/**
 * Regression tests for the 1.12 pre-release review of the core-data
 * shim's block-editor round trip:
 *
 * - server content changes surface through `useEntityBlockEditor`
 *   even when the tree shape is unchanged (stale authoritative tree);
 * - the save round trip keeps the hook's array reference + clientIds
 *   (#808), including across structural changes;
 * - only allowlisted entities autosave, and staged edits on the rest
 *   don't mask fresh records;
 * - autosave failures raise a `core/notices` error with a Retry action,
 *   and pending debounced saves flush on page unload.
 */

import '@wordpress/notices';

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render } from '@testing-library/react';
import { dispatch, select } from '@wordpress/data';

import {
    __cancelPendingEntityRecordSaves,
    __resetCoreDataShimConfig,
    configureCoreDataShim,
    flushPendingEntityRecordSaves,
    useEntityBlockEditor,
    type EntityRecord,
} from '../core-data-shim';

type AnyStore = Record<string, (...args: unknown[]) => unknown>;

const coreSelect = (): AnyStore => select('core') as AnyStore;
const coreDispatch = (): AnyStore => dispatch('core') as AnyStore;

interface Block {
    name: string;
    clientId: string;
    isValid?: boolean;
    attributes: Record<string, unknown>;
    innerBlocks: Block[];
}

interface Notice {
    id: string;
    status: string;
    content: string;
    actions: Array<{ label: string; onClick: () => void }>;
}

function getNotices(): Notice[] {
    return (select('core/notices') as unknown as { getNotices: () => Notice[] }).getNotices();
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function mockFetcher(
    impl: (url: string, init: RequestInit) => Promise<Response>,
): { fetcher: typeof fetch; calls: Array<{ url: string; init: RequestInit }> } {
    const calls: Array<{ url: string; init: RequestInit }> = [];
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : String(input);
        calls.push({ url, init: init ?? {} });

        return impl(url, init ?? {});
    }) as unknown as typeof fetch;

    return { fetcher, calls };
}

const puts = (calls: Array<{ init: RequestInit }>) =>
    calls.filter((call) => call.init.method === 'PUT');

function templatePart(text: string): EntityRecord {
    return {
        id: 9,
        slug: 'header',
        theme: 'ap',
        type: 'wp_template_part',
        status: 'publish',
        title: { raw: 'Header', rendered: 'Header' },
        content: {
            raw: '',
            blocks: [
                {
                    name: 'artisanpack/paragraph',
                    attributes: { content: text },
                    innerBlocks: [],
                },
            ],
        },
    };
}

function menu(id: number, blocks: unknown[]): EntityRecord {
    return {
        id,
        slug: 'primary',
        title: { raw: 'Primary', rendered: 'Primary' },
        status: 'publish',
        type: 'wp_navigation',
        content: { raw: '', blocks },
    };
}

function link(label: string, clientId: string): Block {
    return {
        name: 'core/navigation-link',
        clientId,
        isValid: true,
        attributes: { label, url: `/${label.toLowerCase()}` },
        innerBlocks: [],
    };
}

/** Server echo of a block: no clientId / isValid, keys reordered. */
function echo(block: Block): unknown {
    return {
        innerBlocks: block.innerBlocks.map(echo),
        attributes: Object.fromEntries(Object.entries(block.attributes).reverse()),
        name: block.name,
    };
}

function mountBlockEditor(
    kind: string,
    name: string,
    id: number | string,
): {
    read: () => readonly Block[];
    set: (blocks: readonly unknown[]) => void;
    unmount: () => void;
} {
    const state: { blocks: readonly Block[]; setter: (b: readonly unknown[]) => void } = {
        blocks: [],
        setter: () => undefined,
    };

    function Probe() {
        const [blocks, onInput] = useEntityBlockEditor(kind, name, { id });
        state.blocks = blocks as readonly Block[];
        state.setter = onInput;
        return null;
    }

    const { unmount } = render(<Probe />);

    return {
        read: () => state.blocks,
        set: (blocks) => act(() => state.setter(blocks)),
        unmount,
    };
}

async function resetStore(): Promise<void> {
    configureCoreDataShim({
        apiBase: '/_drain_',
        fetcher: async () => new Response(null, { status: 503 }),
    });
    await new Promise((resolve) => setTimeout(resolve, 20));
    coreDispatch().reset();
    coreDispatch().invalidateResolutionForStore();
    for (const notice of getNotices()) {
        (dispatch('core/notices') as AnyStore).removeNotice(notice.id);
    }
}

beforeEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
});

afterEach(async () => {
    __cancelPendingEntityRecordSaves();
    vi.useRealTimers();
    await resetStore();
    __resetCoreDataShimConfig();
});

describe('useEntityBlockEditor — server content changes', () => {
    it('surfaces attribute-only server changes for a template part', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [templatePart('OLD')], undefined, undefined, undefined, false);

        const editor = mountBlockEditor('postType', 'wp_template_part', 'ap//header');
        const before = editor.read();
        expect(before[0].attributes.content).toBe('OLD');

        act(() => {
            coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [templatePart('NEW')], undefined, undefined, undefined, false);
        });

        const after = editor.read();
        expect(after[0].attributes.content).toBe('NEW');
        // Same block at the same position keeps its clientId.
        expect(after[0].clientId).toBe(before[0].clientId);
        editor.unmount();
    });

    it('keeps the array reference when a re-received record is unchanged', () => {
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [templatePart('SAME')], undefined, undefined, undefined, false);

        const editor = mountBlockEditor('postType', 'wp_template_part', 'ap//header');
        const before = editor.read();

        act(() => {
            coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [templatePart('SAME')], undefined, undefined, undefined, false);
        });

        expect(editor.read()).toBe(before);
        editor.unmount();
    });
});

describe('useEntityBlockEditor — wp_navigation save round trip (#808)', () => {
    async function setUpMenu(respond: (sent: Block[]) => unknown[]) {
        vi.useFakeTimers();
        const { fetcher, calls } = mockFetcher(async (url, init) => {
            if (url.endsWith('/menus/42') && init.method === 'PUT') {
                const body = JSON.parse(String(init.body)) as { content: { blocks: Block[] } };

                return jsonResponse(menu(42, respond(body.content.blocks)));
            }

            return jsonResponse(null, 404);
        });
        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });
        coreDispatch().receiveEntityRecords('postType', 'wp_navigation', [menu(42, [])]);

        return { calls, editor: mountBlockEditor('postType', 'wp_navigation', 42) };
    }

    async function settle(): Promise<void> {
        await act(async () => {
            await vi.advanceTimersByTimeAsync(600);
            await vi.runOnlyPendingTimersAsync();
        });
    }

    it('returns the setter array (same reference + clientIds) after setter → PUT → response', async () => {
        const { calls, editor } = await setUpMenu((sent) => sent.map(echo as (b: Block) => unknown));
        const blocks = [link('Home', 'c-home'), link('About', 'c-about')];

        editor.set(blocks);
        expect(editor.read()).toBe(blocks);

        await settle();

        expect(puts(calls)).toHaveLength(1);
        expect(coreSelect().getEntityRecordEdits('postType', 'wp_navigation', 42) ?? {}).toEqual({});
        expect(editor.read()).toBe(blocks);
        expect(editor.read().map((b) => b.clientId)).toEqual(['c-home', 'c-about']);
        editor.unmount();
    });

    it('reuses clientIds and references across an insert and a removal', async () => {
        const home = link('Home', 'c-home');
        const about = link('About', 'c-about');
        let mutate: (sent: Block[]) => unknown[] = (sent) => sent.map(echo as (b: Block) => unknown);
        const { editor } = await setUpMenu((sent) => mutate(sent));

        editor.set([home, about]);
        await settle();

        // Server inserts a block (e.g. normalization adds a link).
        mutate = (sent) => [...sent.map(echo as (b: Block) => unknown), { name: 'core/navigation-link', attributes: { label: 'New' }, innerBlocks: [] }];
        editor.set([home, about]);
        await settle();

        const inserted = editor.read();
        expect(inserted).toHaveLength(3);
        expect(inserted[0]).toBe(home);
        expect(inserted[1]).toBe(about);
        expect(inserted[2].clientId).toEqual(expect.any(String));
        expect([home.clientId, about.clientId]).not.toContain(inserted[2].clientId);

        // Server drops the trailing block.
        mutate = (sent) => sent.slice(0, 1).map(echo as (b: Block) => unknown);
        editor.set(inserted);
        await settle();

        const removed = editor.read();
        expect(removed).toHaveLength(1);
        expect(removed[0]).toBe(home);
        editor.unmount();
    });
});

describe('useEntityBlockEditor — autosave scope', () => {
    it('does not autosave template parts and drops their staged edits once a fresh record lands', async () => {
        vi.useFakeTimers();
        // The resolver's own fetch re-delivers the same content; that
        // must not count as a fresh record.
        const { fetcher, calls } = mockFetcher(async (url) =>
            url.includes('/template-parts?')
                ? jsonResponse([templatePart('SAVED')])
                : jsonResponse(null, 404),
        );
        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });
        coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [templatePart('SAVED')], undefined, undefined, undefined, false);

        const editor = mountBlockEditor('postType', 'wp_template_part', 'ap//header');
        const clientId = editor.read()[0].clientId;

        editor.set([
            { name: 'artisanpack/paragraph', clientId, isValid: true, attributes: { content: 'typed inline' }, innerBlocks: [] },
        ]);
        expect(editor.read()[0].attributes.content).toBe('typed inline');

        await act(async () => {
            await vi.advanceTimersByTimeAsync(600);
            await vi.runOnlyPendingTimersAsync();
        });

        expect(puts(calls)).toHaveLength(0);
        expect(calls.some((call) => call.url.includes('/template-parts?'))).toBe(true);
        expect(editor.read()[0].attributes.content).toBe('typed inline');
        expect(coreSelect().getEntityRecordEdits('postType', 'wp_template_part', 'ap//header')).not.toBeNull();

        // Template Parts editor saves the part and primes the shim.
        act(() => {
            coreDispatch().receiveEntityRecords('postType', 'wp_template_part', [templatePart('FROM EDITOR')], undefined, undefined, undefined, false);
        });

        expect(editor.read()[0].attributes.content).toBe('FROM EDITOR');
        expect(editor.read()[0].clientId).toBe(clientId);
        expect(coreSelect().getEntityRecordEdits('postType', 'wp_template_part', 'ap//header') ?? {}).toEqual({});
        editor.unmount();
    });
});

describe('useEntityBlockEditor — autosave failures + unload', () => {
    it('raises an error notice with a working Retry action when the autosave fails', async () => {
        vi.useFakeTimers();
        let status = 422;
        const { fetcher, calls } = mockFetcher(async (url, init) =>
            url.endsWith('/menus/42') && init.method === 'PUT'
                ? status === 200
                    ? jsonResponse(menu(42, []))
                    : jsonResponse({ message: 'Invalid' }, status)
                : jsonResponse(null, 404),
        );
        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });
        coreDispatch().receiveEntityRecords('postType', 'wp_navigation', [menu(42, [])]);

        const editor = mountBlockEditor('postType', 'wp_navigation', 42);
        editor.set([link('Home', 'c-home')]);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(600);
            await vi.runOnlyPendingTimersAsync();
        });

        expect(puts(calls)).toHaveLength(1);
        const [notice] = getNotices();
        expect(notice).toBeDefined();
        expect(notice.status).toBe('error');
        expect(notice.content).toBe('Your latest changes could not be saved.');
        expect(notice.actions.map((action) => action.label)).toEqual(['Retry']);
        // Edits stay staged for the retry.
        expect(coreSelect().getEntityRecordEdits('postType', 'wp_navigation', 42)).not.toBeNull();

        status = 200;
        await act(async () => {
            notice.actions[0].onClick();
            await vi.runOnlyPendingTimersAsync();
        });

        expect(puts(calls)).toHaveLength(2);
        expect(getNotices()).toHaveLength(0);
        expect(coreSelect().getEntityRecordEdits('postType', 'wp_navigation', 42) ?? {}).toEqual({});
        editor.unmount();
    });

    it('flushes pending debounced saves on pagehide with keepalive', async () => {
        vi.useFakeTimers();
        const { fetcher, calls } = mockFetcher(async (url, init) =>
            url.endsWith('/menus/42') && init.method === 'PUT'
                ? jsonResponse(menu(42, []))
                : jsonResponse(null, 404),
        );
        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });
        coreDispatch().receiveEntityRecords('postType', 'wp_navigation', [menu(42, [])]);

        const editor = mountBlockEditor('postType', 'wp_navigation', 42);
        editor.set([link('Home', 'c-home')]);

        try {
            await act(async () => {
                window.dispatchEvent(new Event('pagehide'));
                // Let the save chain reach the fetcher without advancing
                // past the debounce window.
                await vi.advanceTimersByTimeAsync(0);
            });

            expect(puts(calls)).toHaveLength(1);
            expect(puts(calls)[0].init.keepalive).toBe(true);
        } finally {
            window.dispatchEvent(new Event('pageshow'));
        }

        // Nothing left to fire once the debounce window would have elapsed.
        await act(async () => {
            await vi.advanceTimersByTimeAsync(600);
        });
        expect(puts(calls)).toHaveLength(1);
        editor.unmount();
    });

    it('flushPendingEntityRecordSaves fires pending saves rather than dropping them', async () => {
        vi.useFakeTimers();
        const { fetcher, calls } = mockFetcher(async (url, init) =>
            url.endsWith('/menus/42') && init.method === 'PUT'
                ? jsonResponse(menu(42, []))
                : jsonResponse(null, 404),
        );
        configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });
        coreDispatch().receiveEntityRecords('postType', 'wp_navigation', [menu(42, [])]);

        const editor = mountBlockEditor('postType', 'wp_navigation', 42);
        editor.set([link('Home', 'c-home')]);

        await act(async () => {
            flushPendingEntityRecordSaves();
            await vi.advanceTimersByTimeAsync(0);
        });

        expect(puts(calls)).toHaveLength(1);
        expect(puts(calls)[0].init.keepalive).toBeUndefined();
        editor.unmount();
    });
});
