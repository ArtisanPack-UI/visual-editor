import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Stub `@wordpress/blocks` so the hook's `parse` / `serialize` calls run
// without loading the real package. The tests exercise the dirty +
// save-status flow, not the underlying block-tree parsing; `parse`
// returns the input content's parsed `blocks` array directly, and
// `serialize` returns an empty string (exercises both branches of
// `hydrateBlocks`).
vi.mock('@wordpress/blocks', () => ({
    parse: (raw: string): unknown[] =>
        raw === '' ? [] : [{ name: 'core/paragraph', clientId: 'stub' }],
    serialize: (): string => '',
}));

import { useEntityEditor } from '../use-entity-editor';

const FETCH_MOCK = vi.fn();
const UPDATE_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual = await vi.importActual<typeof import('../api-client')>(
        '../api-client'
    );

    return {
        ...actual,
        fetchEntity: (...args: unknown[]) => FETCH_MOCK(...args),
        updateEntity: (...args: unknown[]) => UPDATE_MOCK(...args),
    };
});

const API_CONFIG = { apiBase: '/visual-editor/api' };

beforeEach(() => {
    FETCH_MOCK.mockReset();
    UPDATE_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function makeTemplate(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        id: 1,
        slug: 'single',
        title: { rendered: 'Single' },
        description: '',
        content: { raw: '', blocks: [{ name: 'core/paragraph', attributes: {} }] },
        status: 'publish',
        theme: 'default',
        type: 'wp_template',
        source: 'custom',
        origin: null,
        ...overrides,
    };
}

describe('useEntityEditor', () => {
    it('loads the entity and hydrates the block list', async () => {
        FETCH_MOCK.mockResolvedValue(makeTemplate());

        const { result } = renderHook(() =>
            useEntityEditor({
                apiConfig: API_CONFIG,
                kind: 'template',
                entityId: '1',
            })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        expect(result.current.entity?.slug).toBe('single');
        expect(result.current.blocks).toHaveLength(1);
        expect(result.current.isDirty).toBe(false);
    });

    it('flags the editor dirty when blocks change', async () => {
        FETCH_MOCK.mockResolvedValue(makeTemplate());

        const { result } = renderHook(() =>
            useEntityEditor({
                apiConfig: API_CONFIG,
                kind: 'template',
                entityId: '1',
            })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        act(() => {
            result.current.setBlocks([
                { name: 'core/paragraph', attributes: { content: 'New text' } },
            ]);
        });

        expect(result.current.isDirty).toBe(true);
    });

    it('resets dirty state after a successful save', async () => {
        FETCH_MOCK.mockResolvedValue(makeTemplate());
        UPDATE_MOCK.mockImplementation(async (_config, _kind, _id, payload) =>
            makeTemplate({ content: payload.content })
        );

        const { result } = renderHook(() =>
            useEntityEditor({
                apiConfig: API_CONFIG,
                kind: 'template',
                entityId: '1',
            })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        act(() => {
            result.current.setBlocks([
                { name: 'core/heading', attributes: { content: 'Hi' } },
            ]);
        });

        expect(result.current.isDirty).toBe(true);

        await act(async () => {
            await result.current.save();
        });

        expect(UPDATE_MOCK).toHaveBeenCalledTimes(1);
        expect(result.current.saveStatus).toBe('saved');
        expect(result.current.isDirty).toBe(false);
    });

    it('surfaces validation errors on 422', async () => {
        FETCH_MOCK.mockResolvedValue(makeTemplate());

        const { SiteEditorApiError } = await vi.importActual<
            typeof import('../api-client')
        >('../api-client');

        UPDATE_MOCK.mockRejectedValueOnce(
            new SiteEditorApiError('Invalid.', 422, {
                message: 'Invalid.',
                errors: { slug: ['Slug required.'] },
            })
        );

        const { result } = renderHook(() =>
            useEntityEditor({
                apiConfig: API_CONFIG,
                kind: 'template',
                entityId: '1',
            })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        await act(async () => {
            await result.current.save();
        });

        expect(result.current.saveStatus).toBe('error');
        expect(result.current.validationErrors?.slug?.[0]).toBe('Slug required.');
    });

    it('switches to idle state when entityId becomes null', async () => {
        FETCH_MOCK.mockResolvedValue(makeTemplate());

        const { result, rerender } = renderHook(
            ({ id }: { id: string | null }) =>
                useEntityEditor({
                    apiConfig: API_CONFIG,
                    kind: 'template',
                    entityId: id,
                }),
            { initialProps: { id: '1' } as { id: string | null } }
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        rerender({ id: null });

        expect(result.current.loadStatus).toBe('idle');
        expect(result.current.entity).toBeNull();
        expect(result.current.blocks).toHaveLength(0);
    });
});
