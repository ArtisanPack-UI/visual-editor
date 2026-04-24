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

    it('merges pending patch into the exposed entity so inputs reflect typed-in values', async () => {
        FETCH_MOCK.mockResolvedValue(makeTemplate({ description: 'Old desc' }));

        const { result } = renderHook(() =>
            useEntityEditor({
                apiConfig: API_CONFIG,
                kind: 'template',
                entityId: '1',
            })
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));

        act(() => {
            result.current.patch({ title: 'Edited', description: 'New desc' });
        });

        expect(result.current.entity?.title.rendered).toBe('Edited');
        expect(
            (result.current.entity as unknown as { description: string } | null)
                ?.description
        ).toBe('New desc');
        expect(result.current.isDirty).toBe(true);
    });

    it('ignores a save response once the user navigates to a different entity', async () => {
        FETCH_MOCK.mockImplementation(async (_config, _kind, id) =>
            makeTemplate({ id: Number(id), slug: `slug-${id}` })
        );

        let releaseUpdate: (value: Record<string, unknown>) => void = () => {};
        UPDATE_MOCK.mockImplementationOnce(
            () =>
                new Promise<Record<string, unknown>>((resolve) => {
                    releaseUpdate = resolve;
                })
        );

        const { result, rerender } = renderHook(
            ({ id }: { id: string }) =>
                useEntityEditor({
                    apiConfig: API_CONFIG,
                    kind: 'template',
                    entityId: id,
                }),
            { initialProps: { id: '1' } }
        );

        await waitFor(() => expect(result.current.loadStatus).toBe('ready'));
        expect(result.current.entity?.slug).toBe('slug-1');

        let savePromise: Promise<unknown> = Promise.resolve();
        act(() => {
            savePromise = result.current.save();
        });

        expect(result.current.saveStatus).toBe('saving');

        rerender({ id: '2' });

        await waitFor(() => expect(result.current.entity?.slug).toBe('slug-2'));

        await act(async () => {
            releaseUpdate(makeTemplate({ id: 1, slug: 'slug-1-stale' }));
            await savePromise;
        });

        // The in-flight save resolved with entity 1's shape, but the user
        // is now on entity 2 — hydrating would overwrite the visible
        // record with the wrong one. Editor should still show entity 2.
        expect(result.current.entity?.slug).toBe('slug-2');
        expect(result.current.saveStatus).not.toBe('saved');
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
