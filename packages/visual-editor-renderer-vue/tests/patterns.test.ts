import { describe, expect, it } from 'vitest';

import {
    DEFAULT_MAX_PATTERN_DEPTH,
    inlinePatterns,
} from '../src/patterns';
import type { PatternRecord } from '../src/patterns';
import type { Block } from '../src/types';

function paragraph(text: string, clientId = 'p-cid'): Block {
    return {
        clientId,
        name: 'core/paragraph',
        attributes: { content: text },
        innerBlocks: [],
    };
}

function patternRef(ref: number, clientId = 'pat-cid'): Block {
    return {
        clientId,
        name: 'core/block',
        attributes: { ref },
        innerBlocks: [],
    };
}

describe('inlinePatterns', () => {
    it('inlines a referenced synced pattern', () => {
        const patterns: PatternRecord[] = [{ id: 1, blocks: [paragraph('Hero')] }];
        const tree = [patternRef(1)];

        const inlined = inlinePatterns(tree, { patterns });

        expect(inlined[0].name).toBe('core/block');
        expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('Hero');
    });

    it('marks missing references with the not-found error', () => {
        const tree = [patternRef(9999)];

        const inlined = inlinePatterns(tree, { patterns: [] });

        expect(inlined[0].attributes?._resolutionError).toBe('not-found');
        expect(inlined[0].innerBlocks).toEqual([]);
    });

    it('marks missing ref with the missing-ref error', () => {
        const tree: Block[] = [
            { clientId: 'pat', name: 'core/block', attributes: {}, innerBlocks: [] },
        ];

        const inlined = inlinePatterns(tree, { patterns: [] });

        expect(inlined[0].attributes?._resolutionError).toBe('missing-ref');
    });

    it('rejects non-numeric ref values with the missing-ref error', () => {
        const tree: Block[] = [
            { clientId: 'pat', name: 'core/block', attributes: { ref: 'not-a-number' }, innerBlocks: [] },
        ];

        const inlined = inlinePatterns(tree, { patterns: [] });

        expect(inlined[0].attributes?._resolutionError).toBe('missing-ref');
    });

    it('normalizes numeric-string refs', () => {
        const patterns: PatternRecord[] = [{ id: 7, blocks: [paragraph('Resolved')] }];
        const tree: Block[] = [
            { clientId: 'pat', name: 'core/block', attributes: { ref: '7' }, innerBlocks: [] },
        ];

        const inlined = inlinePatterns(tree, { patterns });

        expect(inlined[0].attributes?._resolutionError).toBeUndefined();
        expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('Resolved');
    });

    it('detects direct cycles (a → a)', () => {
        const patterns: PatternRecord[] = [{ id: 1, blocks: [patternRef(1, 'self-ref')] }];
        const tree = [patternRef(1, 'root')];

        const inlined = inlinePatterns(tree, { patterns });

        expect(inlined[0].innerBlocks?.[0].attributes?._resolutionError).toBe('cycle');
    });

    it('detects indirect cycles (a → b → a)', () => {
        const patterns: PatternRecord[] = [
            { id: 1, blocks: [patternRef(2, 'b-ref')] },
            { id: 2, blocks: [patternRef(1, 'a-ref')] },
        ];
        const tree = [patternRef(1, 'a-root')];

        const inlined = inlinePatterns(tree, { patterns });

        const cycleNode = inlined[0].innerBlocks?.[0].innerBlocks?.[0];

        expect(cycleNode?.attributes?._resolutionError).toBe('cycle');
    });

    it('enforces the depth limit', () => {
        const patterns: PatternRecord[] = [
            { id: 1, blocks: [patternRef(2, 'p2-ref')] },
            { id: 2, blocks: [patternRef(3, 'p3-ref')] },
            { id: 3, blocks: [patternRef(4, 'p4-ref')] },
            { id: 4, blocks: [paragraph('end')] },
        ];

        const inlined = inlinePatterns([patternRef(1, 'p1-root')], { patterns, maxDepth: 2 });

        // pattern 1 (depth 0) and pattern 2 (depth 1) resolve; pattern 3
        // (depth 2) hits the cap.
        const third = inlined[0].innerBlocks?.[0].innerBlocks?.[0];

        expect(third?.attributes?._resolutionError).toBe('depth-limit');
    });

    it('preserves non-core/block blocks', () => {
        const tree = [paragraph('only')];

        expect(inlinePatterns(tree, { patterns: [] })).toEqual(tree);
    });

    it('descends into nested innerBlocks looking for synced patterns', () => {
        const patterns: PatternRecord[] = [{ id: 5, blocks: [paragraph('Inside group')] }];
        const tree = [
            { clientId: 'g', name: 'core/group', attributes: {}, innerBlocks: [patternRef(5)] },
        ];

        const inlined = inlinePatterns(tree, { patterns });

        expect(inlined[0].innerBlocks?.[0].innerBlocks?.[0].attributes?.content).toBe('Inside group');
    });

    it('exposes a default depth limit', () => {
        expect(DEFAULT_MAX_PATTERN_DEPTH).toBe(10);
    });

    it('does not touch unsynced-style trees (block tree without core/block references)', () => {
        // Sanity check on the synced/unsynced contract: unsynced patterns
        // are inlined into the saved tree at insert time by the editor,
        // so the inliner only ever sees `core/block` references for
        // synced patterns.
        const tree = [paragraph('Inlined at insert time')];

        expect(inlinePatterns(tree, { patterns: [] })).toEqual(tree);
    });
});
