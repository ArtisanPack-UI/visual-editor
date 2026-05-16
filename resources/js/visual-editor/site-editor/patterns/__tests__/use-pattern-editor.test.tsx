import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PatternRecord } from '../api-client';
import { usePatternEditor } from '../use-pattern-editor';

const FETCH_MOCK = vi.fn();
const UPDATE_MOCK = vi.fn();

vi.mock('@wordpress/blocks', () => ({
    parse: vi.fn(() => []),
    serialize: vi.fn(() => '<!-- wp:paragraph -->'),
    // `applySchemaDefaults` (Keystone #49) calls this to fill missing
    // default-valued attributes. Stub returns `undefined` so the hook
    // leaves attributes as-is when the registry has no entry.
    getBlockType: vi.fn(() => undefined),
}));

vi.mock('../api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../api-client')>('../api-client');

    return {
        ...actual,
        fetchPattern: (...args: unknown[]) => FETCH_MOCK(...args),
        updatePattern: (...args: unknown[]) => UPDATE_MOCK(...args),
    };
});

const API_CONFIG = { apiBase: '/visual-editor/api' };

function record(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 1,
        slug: 'sample',
        title: { rendered: 'Sample pattern' },
        content: { raw: '', blocks: [] },
        synced: true,
        categories: ['featured'],
        status: 'publish',
        type: 'wp_block',
        ...overrides,
    };
}

beforeEach(() => {
    FETCH_MOCK.mockReset();
    UPDATE_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('usePatternEditor', () => {
    it('loads the pattern and surfaces fields', async () => {
        FETCH_MOCK.mockResolvedValue(record());

        const { result } = renderHook(() =>
            usePatternEditor({ apiConfig: API_CONFIG, entityId: '1' })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        expect(result.current.fields.title).toBe('Sample pattern');
        expect(result.current.fields.slug).toBe('sample');
        expect(result.current.fields.categories).toEqual(['featured']);
    });

    it('PUTs the pattern with the current fields and stripped synced flag', async () => {
        const original = record();
        FETCH_MOCK.mockResolvedValue(original);
        UPDATE_MOCK.mockResolvedValue({ ...original, title: { rendered: 'Renamed' } });

        const { result } = renderHook(() =>
            usePatternEditor({ apiConfig: API_CONFIG, entityId: '1' })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        act(() => {
            result.current.setFields({ title: 'Renamed' });
        });

        await act(async () => {
            await result.current.save({ synced: false });
        });

        expect(UPDATE_MOCK).toHaveBeenCalledWith(
            API_CONFIG,
            '1',
            expect.objectContaining({
                title: 'Renamed',
            })
        );

        const payload = UPDATE_MOCK.mock.calls[0][2];
        expect(payload).not.toHaveProperty('synced');
    });

    it('marks the editor dirty after a field change', async () => {
        FETCH_MOCK.mockResolvedValue(record());

        const { result } = renderHook(() =>
            usePatternEditor({ apiConfig: API_CONFIG, entityId: '1' })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));
        expect(result.current.isDirty).toBe(false);

        act(() => {
            result.current.setFields({ title: 'Edited' });
        });

        expect(result.current.isDirty).toBe(true);
    });
});
