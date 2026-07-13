/**
 * Tests for the `ap.visual-editor.background-controls` filter helper
 * (#649).
 *
 * Focuses on the pure list-shaping contract: applies the filter with
 * the given context, drops malformed descriptors, sorts by `priority`
 * (default 10, lower first), dedupes by `id` (last-wins).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

type FilterCallback = (value: unknown, ...args: unknown[]) => unknown;

const filters = new Map<string, FilterCallback[]>();

vi.mock('@wordpress/hooks', () => ({
    addFilter: (hook: string, _id: string, callback: FilterCallback): void => {
        if (!filters.has(hook)) {
            filters.set(hook, []);
        }
        filters.get(hook)!.push(callback);
    },
    applyFilters: (hook: string, value: unknown, ...args: unknown[]): unknown => {
        const callbacks = filters.get(hook) ?? [];
        return callbacks.reduce(
            (acc, callback) => callback(acc, ...args),
            value
        );
    },
}));

beforeEach(() => {
    filters.clear();
    vi.resetModules();
});

async function loadModule(): Promise<
    typeof import('../background-controls')
> {
    return await import('../background-controls');
}

function registerFilter(callback: FilterCallback): void {
    filters.set('ap.visual-editor.background-controls', [
        ...(filters.get('ap.visual-editor.background-controls') ?? []),
        callback,
    ]);
}

const CONTEXT = {
    attributes: {},
    setAttributes: () => undefined,
    clientId: 'client-1',
    blockName: 'artisanpack/group',
    blockSupports: { background: true } as Record<string, unknown>,
};

describe('getFilteredBackgroundControls', () => {
    it('returns an empty list when no callbacks are registered', async () => {
        const { getFilteredBackgroundControls } = await loadModule();

        expect(getFilteredBackgroundControls(CONTEXT)).toEqual([]);
    });

    it('passes the context to the filter callback verbatim', async () => {
        let received: unknown = null;

        registerFilter((value, context) => {
            received = context;
            return value;
        });

        const { getFilteredBackgroundControls } = await loadModule();

        getFilteredBackgroundControls(CONTEXT);

        expect(received).toBe(CONTEXT);
    });

    it('returns controls contributed by the filter', async () => {
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'liquid-glass',
                    label: 'Liquid Glass',
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        const controls = getFilteredBackgroundControls(CONTEXT);
        expect(controls.map((c) => c.id)).toEqual(['liquid-glass']);
    });

    it('sorts by priority (lower first, default 10)', async () => {
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                { id: 'late', label: 'Late', priority: 30, render: () => null },
                { id: 'default', label: 'Default', render: () => null },
                {
                    id: 'early',
                    label: 'Early',
                    priority: 5,
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        expect(
            getFilteredBackgroundControls(CONTEXT).map((c) => c.id)
        ).toEqual(['early', 'default', 'late']);
    });

    it('is stable for controls with equal priority', async () => {
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                { id: 'a', label: 'A', priority: 10, render: () => null },
                { id: 'b', label: 'B', priority: 10, render: () => null },
                { id: 'c', label: 'C', priority: 10, render: () => null },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        expect(
            getFilteredBackgroundControls(CONTEXT).map((c) => c.id)
        ).toEqual(['a', 'b', 'c']);
    });

    it('dedupes by id, last wins, and keeps the later position', async () => {
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'dup',
                    label: 'First',
                    priority: 10,
                    render: () => null,
                },
                {
                    id: 'other',
                    label: 'Other',
                    priority: 15,
                    render: () => null,
                },
                {
                    id: 'dup',
                    label: 'Second',
                    priority: 20,
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        const result = getFilteredBackgroundControls(CONTEXT);
        expect(result.map((c) => c.id)).toEqual(['other', 'dup']);
        expect(result.find((c) => c.id === 'dup')?.label).toBe('Second');
    });

    it('drops malformed entries', async () => {
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                null,
                undefined,
                42,
                'string',
                { id: 'no-label', render: () => null },
                { id: 'no-render', label: 'No render' },
                { label: 'No id', render: () => null },
                {
                    id: 'valid',
                    label: 'Valid',
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        expect(
            getFilteredBackgroundControls(CONTEXT).map((c) => c.id)
        ).toEqual(['valid']);
    });

    it('falls back to an empty list when a callback returns a non-array', async () => {
        registerFilter(() => 'not-an-array');

        const { getFilteredBackgroundControls } = await loadModule();

        expect(getFilteredBackgroundControls(CONTEXT)).toEqual([]);
    });

    it('exposes the filter name as a constant', async () => {
        const { BACKGROUND_CONTROLS_FILTER } = await loadModule();

        expect(BACKGROUND_CONTROLS_FILTER).toBe(
            'ap.visual-editor.background-controls'
        );
    });
});
