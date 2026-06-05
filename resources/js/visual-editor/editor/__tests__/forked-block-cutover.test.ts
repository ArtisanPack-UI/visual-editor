/**
 * Tests for the forked-block cutover (I1–I4): forked core blocks are hidden
 * from the inserter while everything else passes through untouched.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';

const addFilter = vi.fn();
let filterRegistered = false;

vi.mock('@wordpress/hooks', () => ({
    addFilter: (...args: unknown[]) => {
        filterRegistered = true;
        return addFilter(...args);
    },
    hasFilter: () => filterRegistered,
}));

import {
    FORKED_CORE_BLOCKS,
    suppressForkedBlockInserter,
    registerForkedBlockCutoverFilter,
} from '../forked-block-cutover';

beforeEach(() => {
    addFilter.mockClear();
    filterRegistered = false;
});

describe('FORKED_CORE_BLOCKS', () => {
    it('covers the I1–I4 forked clusters', () => {
        // A representative from each cluster.
        expect(FORKED_CORE_BLOCKS).toContain('core/heading'); // I1 content
        expect(FORKED_CORE_BLOCKS).toContain('core/image'); // I2 media
        expect(FORKED_CORE_BLOCKS).toContain('core/group'); // I3 layout
        expect(FORKED_CORE_BLOCKS).toContain('core/search'); // I4 widgets
        expect(FORKED_CORE_BLOCKS).toContain('core/latest-posts'); // I4 widgets
    });

    it('excludes core/paragraph (handled by the dedicated paragraph cutover)', () => {
        expect(FORKED_CORE_BLOCKS).not.toContain('core/paragraph');
    });

    it('does not list core/row or core/stack — they are core/group variations', () => {
        expect(FORKED_CORE_BLOCKS).not.toContain('core/row');
        expect(FORKED_CORE_BLOCKS).not.toContain('core/stack');
    });
});

describe('suppressForkedBlockInserter', () => {
    it('stamps supports.inserter=false on a forked core block', () => {
        const result = suppressForkedBlockInserter(
            { title: 'Search', supports: { anchor: true } },
            'core/search'
        );
        expect((result.supports as Record<string, unknown>).inserter).toBe(false);
        // Existing supports are preserved.
        expect((result.supports as Record<string, unknown>).anchor).toBe(true);
    });

    it('adds a supports object when the block declares none', () => {
        const result = suppressForkedBlockInserter({ title: 'List Item' }, 'core/list-item');
        expect((result.supports as Record<string, unknown>).inserter).toBe(false);
    });

    it('passes non-forked core blocks through unchanged', () => {
        // `core/navigation-link` is a parent-locked child of the navigation
        // block and is intentionally not forked (see FORKED_CORE_BLOCKS).
        const settings = { title: 'Navigation Link', supports: { anchor: true } };
        expect(suppressForkedBlockInserter(settings, 'core/navigation-link')).toBe(settings);
    });

    it('passes the artisanpack forks themselves through unchanged', () => {
        const settings = { title: 'Search', supports: {} };
        expect(suppressForkedBlockInserter(settings, 'artisanpack/search')).toBe(settings);
    });
});

describe('registerForkedBlockCutoverFilter', () => {
    it('registers the blocks.registerBlockType filter once', () => {
        registerForkedBlockCutoverFilter();
        expect(addFilter).toHaveBeenCalledTimes(1);
        expect(addFilter).toHaveBeenCalledWith(
            'blocks.registerBlockType',
            'artisanpack-ui/visual-editor/forked-block-cutover',
            expect.any(Function)
        );
    });

    it('is idempotent — a second call does not re-register', () => {
        registerForkedBlockCutoverFilter();
        registerForkedBlockCutoverFilter();
        expect(addFilter).toHaveBeenCalledTimes(1);
    });
});
