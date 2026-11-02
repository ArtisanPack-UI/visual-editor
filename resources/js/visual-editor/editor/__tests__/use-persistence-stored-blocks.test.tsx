/**
 * Stored block trees through the real block-editor store (#1055).
 *
 * The store keys every block by `clientId`, so a tree without them resets
 * it to empty with no error. `use-persistence.test.tsx` can't see that:
 * it stops at the hook's `blocks`, which looked fine before the fix.
 */

import { act, cleanup, render, waitFor } from '@testing-library/react';
import { BlockEditorProvider, store as blockEditorStore } from '@wordpress/block-editor';
import { getBlockType, registerBlockType, type BlockInstance } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';

import { usePersistence } from '../use-persistence';

const CONFIG = {
    apiBase: '/visual-editor/api',
    resource: 'pages',
    id: '7',
    debounceMs: 10,
};

const GROUP = 'test/stored-group';
const TEXT = 'test/stored-text';

const STORED_TREE = [
    {
        name: GROUP,
        attributes: [],
        innerBlocks: [
            { name: TEXT, attributes: { content: 'First' }, innerBlocks: [], innerHTML: '<p>First</p>' },
        ],
    },
    { name: TEXT, attributes: { content: 'Second' }, innerBlocks: [] },
];

interface StoreView {
    blocks: BlockInstance[];
    updateBlockAttributes: (clientId: string, attributes: Record<string, unknown>) => void;
}

let storeView: StoreView | undefined;

function StoreProbe(): null {
    const blocks = useSelect(
        (select) => select(blockEditorStore).getBlocks() as BlockInstance[],
        []
    );
    const { updateBlockAttributes } = useDispatch(blockEditorStore);

    storeView = { blocks, updateBlockAttributes } as StoreView;

    return null;
}

function Editor(): JSX.Element | null {
    const persistence = usePersistence(CONFIG);

    if (persistence.loadStatus !== 'ready') {
        return null;
    }

    return (
        <>
            <output data-testid="save-status">{persistence.saveStatus}</output>
            <BlockEditorProvider
                value={persistence.blocks}
                onInput={persistence.queueBlocksForSave}
                onChange={persistence.onBlocksChange}
            >
                <StoreProbe />
            </BlockEditorProvider>
        </>
    );
}

function mockContentApi(tree: unknown[] = STORED_TREE): RequestInit[] {
    const saves: RequestInit[] = [];

    vi.stubGlobal('fetch', vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
        if ((init?.method ?? 'GET').toUpperCase() !== 'GET') {
            saves.push(init as RequestInit);
        }

        return new Response(
            JSON.stringify({
                id: 7,
                resource: 'pages',
                blocks: tree,
                updated_at: '2026-11-02T10:00:00Z',
            }),
            { status: 200, headers: { 'content-type': 'application/json' } }
        );
    }));

    return saves;
}

describe('stored blocks without a clientId (#1055)', () => {
    beforeAll(() => {
        for (const name of [GROUP, TEXT]) {
            if (!getBlockType(name)) {
                registerBlockType(name, {
                    apiVersion: 3,
                    title: name,
                    category: 'text',
                    attributes: {
                        content: { type: 'string' },
                        size: { type: 'string', default: 'medium' },
                    },
                    edit: () => null,
                    save: () => null,
                });
            }
        }
    });

    afterEach(() => {
        cleanup();
        storeView = undefined;
        vi.unstubAllGlobals();
    });

    it('loads the whole tree into the store without saving', async () => {
        const saves = mockContentApi();
        const { getByTestId } = render(<Editor />);

        await waitFor(() => {
            expect(storeView?.blocks).toHaveLength(2);
        });

        const { blocks } = storeView as StoreView;

        expect(blocks[0].name).toBe(GROUP);
        expect(blocks[0].innerBlocks[0].attributes).toEqual({ content: 'First', size: 'medium' });
        expect(blocks[1].attributes).toEqual({ content: 'Second', size: 'medium' });

        await new Promise((resolve) => setTimeout(resolve, CONFIG.debounceMs * 3));

        expect(getByTestId('save-status').textContent).toBe('saved');
        expect(saves).toEqual([]);
    });

    it('keeps the rest of the tree when the first edit saves', async () => {
        const saves = mockContentApi();

        render(<Editor />);

        await waitFor(() => {
            expect(storeView?.blocks).toHaveLength(2);
        });

        act(() => {
            const { blocks, updateBlockAttributes } = storeView as StoreView;

            updateBlockAttributes(blocks[1].clientId, { content: 'Edited' });
        });

        await waitFor(() => {
            expect(saves).toHaveLength(1);
        });

        const saved = JSON.parse(saves[0].body as string) as { blocks: BlockInstance[] };

        expect(saved.blocks).toHaveLength(2);
        expect(saved.blocks[0].innerBlocks[0].attributes.content).toBe('First');
        expect(saved.blocks[1].attributes.content).toBe('Edited');
    });

    it('leaves defaulted attributes a saved block cleared alone', async () => {
        mockContentApi([
            {
                clientId: 'saved',
                isValid: true,
                name: TEXT,
                attributes: { content: 'Saved' },
                innerBlocks: [],
            },
        ]);

        render(<Editor />);

        await waitFor(() => {
            expect(storeView?.blocks).toHaveLength(1);
        });

        expect((storeView as StoreView).blocks[0].attributes).toEqual({ content: 'Saved' });
    });
});
