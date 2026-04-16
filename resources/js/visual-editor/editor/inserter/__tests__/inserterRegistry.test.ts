import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clearRegistry } from '../../registry';
import { registerCoreBlocks, PARAGRAPH_BLOCK_NAME, HEADING_BLOCK_NAME } from '../../blocks';
import {
    clearInserterRegistry,
    filterInserterBlocks,
    getInserterBlocks,
    loadInserterBlocks,
    registerBlockFactory,
    subscribeInserterBlocks,
    type InserterBlock,
} from '../index';

beforeEach(() => {
    clearRegistry();
    clearInserterRegistry();
});

afterEach(() => {
    clearRegistry();
    clearInserterRegistry();
});

describe('inserter registry', () => {
    it('returns blocks registered via registerCoreBlocks', () => {
        registerCoreBlocks();
        const blocks = getInserterBlocks();
        const names = blocks.map((b) => b.name);

        expect(names).toContain(PARAGRAPH_BLOCK_NAME);
        expect(names).toContain(HEADING_BLOCK_NAME);
    });

    it('notifies subscribers when the registry changes', () => {
        const listener = vi.fn();
        const unsubscribe = subscribeInserterBlocks(listener);

        registerCoreBlocks();

        expect(listener).toHaveBeenCalled();

        unsubscribe();
    });

    it('seeds paragraph and heading via registerCoreBlocks', () => {
        registerCoreBlocks();
        const blocks = getInserterBlocks();

        expect(blocks.map((block) => block.name)).toContain(PARAGRAPH_BLOCK_NAME);
        expect(blocks.map((block) => block.name)).toContain(HEADING_BLOCK_NAME);
    });
});

describe('filterInserterBlocks', () => {
    const blocks: InserterBlock[] = [
        { name: PARAGRAPH_BLOCK_NAME, title: 'Paragraph', keywords: ['text'] },
        { name: HEADING_BLOCK_NAME, title: 'Heading', description: 'Sections', keywords: ['h1', 'h2'] },
    ];

    it('returns a copy of the list for an empty query', () => {
        const result = filterInserterBlocks(blocks, '');
        expect(result).toEqual(blocks);
        expect(result).not.toBe(blocks);
    });

    it('matches on the title', () => {
        expect(filterInserterBlocks(blocks, 'head')).toEqual([blocks[1]]);
    });

    it('matches on keywords', () => {
        expect(filterInserterBlocks(blocks, 'text')).toEqual([blocks[0]]);
    });

    it('matches on description', () => {
        expect(filterInserterBlocks(blocks, 'sections')).toEqual([blocks[1]]);
    });

    it('is case-insensitive', () => {
        expect(filterInserterBlocks(blocks, 'PARAGRAPH')).toEqual([blocks[0]]);
    });

    it('returns an empty list when nothing matches', () => {
        expect(filterInserterBlocks(blocks, 'zzz')).toEqual([]);
    });
});

describe('loadInserterBlocks', () => {
    it('includes core blocks even without an API base', async () => {
        registerCoreBlocks();
        await loadInserterBlocks();
        const names = getInserterBlocks().map((block) => block.name);
        expect(names).toContain(PARAGRAPH_BLOCK_NAME);
        expect(names).toContain(HEADING_BLOCK_NAME);
    });

    it('merges API-returned blocks when a client factory is registered', async () => {
        registerCoreBlocks();

        registerBlockFactory('custom/block', () => ({
            name: 'custom/block',
            attributes: {},
            innerBlocks: [],
        }));

        const fetchImpl = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                blocks: [
                    { name: 'custom/block', title: 'Custom', description: 'A custom block' },
                ],
            }),
        }) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        const names = getInserterBlocks().map((block) => block.name);
        expect(names).toContain('custom/block');
        expect(names).toContain(PARAGRAPH_BLOCK_NAME);
    });

    it('skips API-returned blocks that have no client factory', async () => {
        registerCoreBlocks();

        const fetchImpl = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                blocks: [
                    { name: 'unknown/block', title: 'Unregistered' },
                ],
            }),
        }) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        const names = getInserterBlocks().map((block) => block.name);
        expect(names).not.toContain('unknown/block');
    });

    it('ignores malformed payloads with non-string description or keyword entries', async () => {
        registerCoreBlocks();

        registerBlockFactory('custom/block', () => ({
            name: 'custom/block',
            attributes: {},
            innerBlocks: [],
        }));

        const fetchImpl = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                blocks: [
                    {
                        name: 'custom/block',
                        title: 'Custom',
                        description: 42,
                        keywords: ['valid', 7, null, 'more'],
                    },
                ],
            }),
        }) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        const custom = getInserterBlocks().find((block) => block.name === 'custom/block');
        expect(custom).toBeDefined();
        expect(custom!.description).toBeUndefined();
        expect(custom!.keywords).toEqual(['valid', 'more']);
    });

    it('falls back to core blocks when the API request fails', async () => {
        registerCoreBlocks();

        const fetchImpl = vi
            .fn()
            .mockRejectedValue(new Error('network')) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        const names = getInserterBlocks().map((block) => block.name);
        expect(names).toContain(PARAGRAPH_BLOCK_NAME);
        expect(names).toContain(HEADING_BLOCK_NAME);
    });

    it('ignores non-ok responses', async () => {
        registerCoreBlocks();

        const fetchImpl = vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ blocks: [] }),
        }) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        expect(fetchImpl).toHaveBeenCalledOnce();
    });
});
