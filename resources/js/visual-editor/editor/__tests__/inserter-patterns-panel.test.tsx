import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const LIST_MOCK = vi.fn();

vi.mock('../../site-editor/patterns/api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../../site-editor/patterns/api-client')>(
            '../../site-editor/patterns/api-client'
        );

    return {
        ...actual,
        listPatterns: (...args: unknown[]) => LIST_MOCK(...args),
    };
});

const insertedBlocks: Array<{
    block: { name: string; attributes?: Record<string, unknown> };
    rootClientId?: string;
    index?: number;
}> = [];
const insertedTrees: Array<{
    blocks: Array<{ name: string }>;
    rootClientId?: string;
    index?: number;
}> = [];

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes: Record<string, unknown>) => ({
        name,
        attributes,
        innerBlocks: [],
    }),
    createBlocksFromInnerBlocksTemplate: (template: Array<unknown>) =>
        template.map((entry) => ({
            name: (entry as [string])[0],
            attributes: {},
            innerBlocks: [],
        })),
    parse: () => [],
}));

const primedRecords: Array<{
    kind: string;
    name: string;
    records: readonly Record<string, unknown>[];
}> = [];

// Stable references — returning a fresh object literal from `useDispatch`
// would re-create downstream `useCallback` identities every render, which
// in turn would re-fire the panel's load effect and trap it in a
// loading→re-render loop. Production `useDispatch` returns a stable
// reference, so we mirror that here.
const blockEditorDispatch = {
    insertBlock: (
        block: { name: string; attributes?: Record<string, unknown> },
        index?: number,
        rootClientId?: string
    ): void => {
        insertedBlocks.push({ block, index, rootClientId });
    },
    insertBlocks: (
        blocks: Array<{ name: string }>,
        index?: number,
        rootClientId?: string
    ): void => {
        insertedTrees.push({ blocks, index, rootClientId });
    },
};

const coreDispatch = {
    receiveEntityRecords: (
        kind: string,
        name: string,
        records: readonly Record<string, unknown>[]
    ): void => {
        primedRecords.push({ kind, name, records });
    },
};

const emptyDispatch = {};

vi.mock('@wordpress/data', () => ({
    useDispatch: (storeName?: string) => {
        if (storeName === 'core/block-editor') {
            return blockEditorDispatch;
        }

        if (storeName === 'core') {
            return coreDispatch;
        }

        return emptyDispatch;
    },
    useSelect: () => null,
}));



import { InserterPatternsPanel } from '../inserter-patterns-panel';

beforeEach(() => {
    LIST_MOCK.mockReset();
    insertedBlocks.length = 0;
    insertedTrees.length = 0;
    primedRecords.length = 0;
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('<InserterPatternsPanel />', () => {
    it('lists synced and unsynced groups separately', async () => {
        LIST_MOCK.mockImplementation((_config, params: { synced: boolean }) => {
            if (params.synced) {
                return Promise.resolve({
                    data: [
                        {
                            id: 1,
                            slug: 'hero',
                            title: { rendered: 'Hero' },
                            content: { raw: '', blocks: [] },
                            synced: true,
                            categories: [],
                            status: 'publish',
                            type: 'wp_block',
                        },
                    ],
                    meta: { total: 1, per_page: 100, current_page: 1, last_page: 1 },
                });
            }

            return Promise.resolve({
                data: [
                    {
                        id: 2,
                        slug: 'cta',
                        title: { rendered: 'Call to action' },
                        content: { raw: '', blocks: [] },
                        synced: false,
                        categories: [],
                        status: 'publish',
                        type: 'wp_block',
                    },
                ],
                meta: { total: 1, per_page: 100, current_page: 1, last_page: 1 },
            });
        });

        render(<InserterPatternsPanel apiBase="/visual-editor/api" />);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-inserter-patterns-list-synced')
            ).toBeInTheDocument()
        );

        expect(
            screen.getByTestId('ap-inserter-patterns-list-unsynced')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-inserter-patterns-row-synced-1')
        ).toHaveTextContent('Hero');
        expect(
            screen.getByTestId('ap-inserter-patterns-row-unsynced-2')
        ).toHaveTextContent('Call to action');
    });

    it('inserts a core/block reference when a synced pattern is picked', async () => {
        LIST_MOCK.mockImplementation((_config, params: { synced: boolean }) =>
            Promise.resolve({
                data: params.synced
                    ? [
                          {
                              id: 7,
                              slug: 'banner',
                              title: { rendered: 'Banner' },
                              content: { raw: '', blocks: [] },
                              synced: true,
                              categories: [],
                              status: 'publish',
                              type: 'wp_block',
                          },
                      ]
                    : [],
                meta: { total: 1, per_page: 100, current_page: 1, last_page: 1 },
            })
        );

        const user = userEvent.setup();

        render(<InserterPatternsPanel apiBase="/visual-editor/api" />);

        const row = await screen.findByTestId(
            'ap-inserter-patterns-row-synced-7'
        );

        await user.click(row);

        expect(insertedBlocks).toHaveLength(1);
        expect(insertedBlocks[0]?.block.name).toBe('core/block');
        expect(insertedBlocks[0]?.block.attributes).toEqual({ ref: 7 });
    });

    it('primes the core-data shim cache with synced patterns on load and on insert', async () => {
        LIST_MOCK.mockImplementation((_config, params: { synced: boolean }) =>
            Promise.resolve({
                data: params.synced
                    ? [
                          {
                              id: 91,
                              slug: 'sync-1',
                              title: { rendered: 'Sync 1' },
                              content: { raw: '', blocks: [] },
                              synced: true,
                              categories: [],
                              status: 'publish',
                              type: 'wp_block',
                          },
                      ]
                    : [],
                meta: { total: 1, per_page: 100, current_page: 1, last_page: 1 },
            })
        );

        const user = userEvent.setup();

        render(<InserterPatternsPanel apiBase="/visual-editor/api" />);

        const row = await screen.findByTestId(
            'ap-inserter-patterns-row-synced-91'
        );

        // First priming happens at load.
        expect(
            primedRecords.some(
                (entry) =>
                    entry.kind === 'postType' &&
                    entry.name === 'wp_block' &&
                    entry.records.some((r) => r.id === 91)
            )
        ).toBe(true);

        primedRecords.length = 0;

        await user.click(row);

        // Second priming happens at insert time.
        expect(primedRecords).toHaveLength(1);
        expect(primedRecords[0]?.records[0]).toMatchObject({ id: 91 });
    });

    it('inserts a copy of the block tree when an unsynced pattern is picked', async () => {
        LIST_MOCK.mockImplementation((_config, params: { synced: boolean }) =>
            Promise.resolve({
                data: params.synced
                    ? []
                    : [
                          {
                              id: 11,
                              slug: 'plain',
                              title: { rendered: 'Plain' },
                              content: {
                                  raw: '',
                                  blocks: [
                                      { name: 'core/heading', attributes: {} },
                                      {
                                          name: 'core/paragraph',
                                          attributes: {},
                                      },
                                  ],
                              },
                              synced: false,
                              categories: [],
                              status: 'publish',
                              type: 'wp_block',
                          },
                      ],
                meta: { total: 1, per_page: 100, current_page: 1, last_page: 1 },
            })
        );

        const user = userEvent.setup();

        render(<InserterPatternsPanel apiBase="/visual-editor/api" />);

        const row = await screen.findByTestId(
            'ap-inserter-patterns-row-unsynced-11'
        );

        await user.click(row);

        expect(insertedTrees).toHaveLength(1);
        expect(insertedTrees[0]?.blocks).toHaveLength(2);
        expect(insertedTrees[0]?.blocks[0]?.name).toBe('core/heading');
    });
});
