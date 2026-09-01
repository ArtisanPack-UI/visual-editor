/**
 * Tests for runtime discovery of server-rendered third-party blocks (#766):
 * definition → module synthesis, the `apServerRender` filter, the
 * `/visual-editor/api/blocks` fetch, and end-to-end registration.
 *
 * `server-block-edit` and `custom-blocks` are mocked so the unit run stays
 * off the `@wordpress/*` editor libraries — the orchestration is what matters
 * here, not the rendered edit component.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

const { registerCustomBlocks } = vi.hoisted(() => ({
    registerCustomBlocks: vi.fn(
        (modules: ReadonlyArray<{ metadata: { name: string } }>) =>
            modules.map((module) => module.metadata.name)
    ),
}));

vi.mock('../server-block-edit', () => ({
    createServerBlockEdit: (name: string) => {
        const Edit = (): null => null;
        Edit.displayName = `ServerBlockEdit(${name})`;
        return Edit;
    },
}));

vi.mock('../custom-blocks', () => ({
    registerCustomBlocks,
}));

import {
    buildServerBlockModule,
    fetchServerBlockDefinitions,
    registerServerRenderedBlocks,
    synthesizeServerBlockModules,
    type ServerBlockDefinition,
} from '../server-blocks';

beforeEach(() => {
    registerCustomBlocks.mockClear();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('buildServerBlockModule', () => {
    it('forwards metadata, strips internal keys, defaults apiVersion, and nulls save', () => {
        const module = buildServerBlockModule({
            name: 'acme/manage-booking',
            title: 'Manage Booking',
            category: 'widgets',
            apServerRender: true,
            attributes: { token: { type: 'string' } },
        });

        expect(module).not.toBeNull();
        expect(module?.metadata.name).toBe('acme/manage-booking');
        expect((module?.metadata as Record<string, unknown>).title).toBe('Manage Booking');
        expect((module?.metadata as Record<string, unknown>).apiVersion).toBe(3);
        // internal flag never leaks into the block settings
        expect('apServerRender' in (module?.metadata ?? {})).toBe(false);
        expect(typeof module?.edit).toBe('function');
        expect((module?.save as () => null)()).toBeNull();
    });

    it('keeps an author-provided apiVersion', () => {
        const module = buildServerBlockModule({ name: 'acme/x', apiVersion: 2 });
        expect((module?.metadata as Record<string, unknown>).apiVersion).toBe(2);
    });

    it('returns null when the definition has no usable name', () => {
        expect(buildServerBlockModule({} as ServerBlockDefinition)).toBeNull();
        expect(buildServerBlockModule({ name: '' })).toBeNull();
    });
});

describe('synthesizeServerBlockModules', () => {
    it('keeps only apServerRender definitions with a valid name', () => {
        const modules = synthesizeServerBlockModules([
            { name: 'acme/a', apServerRender: true },
            { name: 'acme/b' }, // not flagged — a static/client block
            { name: 'acme/c', apServerRender: false },
            { apServerRender: true }, // no name
        ]);

        expect(modules.map((module) => module.metadata.name)).toEqual(['acme/a']);
    });
});

describe('fetchServerBlockDefinitions', () => {
    it('returns the blocks array from a 200 response', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                new Response(JSON.stringify({ blocks: [{ name: 'acme/a', apServerRender: true }] }), {
                    status: 200,
                })
            )
        );

        await expect(fetchServerBlockDefinitions()).resolves.toEqual([
            { name: 'acme/a', apServerRender: true },
        ]);
    });

    it('resolves to an empty list on a non-OK response', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response('', { status: 401 })));
        await expect(fetchServerBlockDefinitions()).resolves.toEqual([]);
    });

    it('resolves to an empty list when fetch throws', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        vi.stubGlobal('fetch', vi.fn(async () => {
            throw new Error('network down');
        }));
        await expect(fetchServerBlockDefinitions()).resolves.toEqual([]);
    });

    it('targets the blocks endpoint under the given api base', async () => {
        const fetchMock = vi.fn(async () =>
            new Response(JSON.stringify({ blocks: [] }), { status: 200 })
        );
        vi.stubGlobal('fetch', fetchMock);

        await fetchServerBlockDefinitions('/custom/api/');

        expect(fetchMock).toHaveBeenCalledWith(
            '/custom/api/blocks',
            expect.objectContaining({ method: 'GET', credentials: 'same-origin' })
        );
    });
});

describe('registerServerRenderedBlocks', () => {
    it('registers only the synthesized server modules and returns their names', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                new Response(
                    JSON.stringify({
                        blocks: [
                            { name: 'acme/a', apServerRender: true },
                            { name: 'artisanpack/paragraph' }, // client block — skipped
                        ],
                    }),
                    { status: 200 }
                )
            )
        );

        const names = await registerServerRenderedBlocks();

        expect(names).toEqual(['acme/a']);
        expect(registerCustomBlocks).toHaveBeenCalledTimes(1);
        const passed = registerCustomBlocks.mock.calls[0][0] as ReadonlyArray<{
            metadata: { name: string };
        }>;
        expect(passed.map((module) => module.metadata.name)).toEqual(['acme/a']);
    });

    it('does not call registerCustomBlocks when there are no server blocks', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response(JSON.stringify({ blocks: [] }), { status: 200 }))
        );

        await expect(registerServerRenderedBlocks()).resolves.toEqual([]);
        expect(registerCustomBlocks).not.toHaveBeenCalled();
    });
});
