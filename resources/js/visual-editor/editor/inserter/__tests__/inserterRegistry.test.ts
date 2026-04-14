import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    clearInserterRegistry,
    filterInserterBlocks,
    getInserterBlocks,
    loadInserterBlocks,
    registerBuiltinInserterBlocks,
    subscribeInserterBlocks,
    type InserterBlock,
} from '../index';

beforeEach(() => {
    clearInserterRegistry();
});

afterEach(() => {
    clearInserterRegistry();
});

describe('inserter registry', () => {
    it('returns a stable snapshot reference until mutated', () => {
        registerBuiltinInserterBlocks();
        const a = getInserterBlocks();
        const b = getInserterBlocks();

        expect(a).toBe(b);
    });

    it('produces a new snapshot when a block is registered', () => {
        registerBuiltinInserterBlocks();
        const first = getInserterBlocks();

        registerBuiltinInserterBlocks();

        const second = getInserterBlocks();
        expect(second).not.toBe(first);
    });

    it('notifies subscribers when the registry changes', () => {
        const listener = vi.fn();
        const unsubscribe = subscribeInserterBlocks(listener);

        registerBuiltinInserterBlocks();

        expect(listener).toHaveBeenCalled();

        unsubscribe();
    });

    it('seeds paragraph and heading via registerBuiltinInserterBlocks', () => {
        registerBuiltinInserterBlocks();
        const blocks = getInserterBlocks();

        expect(blocks.map((block) => block.name)).toContain('ve/paragraph');
        expect(blocks.map((block) => block.name)).toContain('ve/heading');
    });
});

describe('filterInserterBlocks', () => {
    const blocks: InserterBlock[] = [
        { name: 've/paragraph', title: 'Paragraph', keywords: ['text'] },
        { name: 've/heading', title: 'Heading', description: 'Sections', keywords: ['h1', 'h2'] },
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
    it('registers the built-ins even without an API base', async () => {
        await loadInserterBlocks();
        const names = getInserterBlocks().map((block) => block.name);
        expect(names).toContain('ve/paragraph');
        expect(names).toContain('ve/heading');
    });

    it('merges API-returned blocks into the registry', async () => {
        const fetchImpl = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                blocks: [
                    { name: 've/custom', title: 'Custom', description: 'A custom block' },
                ],
            }),
        }) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        const names = getInserterBlocks().map((block) => block.name);
        expect(names).toContain('ve/custom');
        expect(names).toContain('ve/paragraph');
    });

    it('falls back to built-ins when the API request fails', async () => {
        const fetchImpl = vi
            .fn()
            .mockRejectedValue(new Error('network')) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        const names = getInserterBlocks().map((block) => block.name);
        expect(names).toContain('ve/paragraph');
        expect(names).toContain('ve/heading');
    });

    it('ignores non-ok responses', async () => {
        const fetchImpl = vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ blocks: [] }),
        }) as unknown as typeof fetch;

        await loadInserterBlocks({ apiBase: 'https://example.test/api', fetchImpl });

        expect(fetchImpl).toHaveBeenCalledOnce();
    });
});
