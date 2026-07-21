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
    filters.set('ap.visualEditor.backgroundControls', [
        ...(filters.get('ap.visualEditor.backgroundControls') ?? []),
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

    it('dedupes by id, last-registered wins, then sorts by priority', async () => {
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

    it('respects registration order over priority for duplicate ids', async () => {
        // Regression: dedupe must run BEFORE sort so a later-registered
        // override wins even when the earlier registration has a lower
        // priority. Otherwise "last-wins mirroring @wordpress/hooks"
        // becomes "highest-priority-wins".
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'glass',
                    label: 'Package A',
                    priority: 5,
                    render: () => null,
                },
                {
                    id: 'glass',
                    label: 'Package B (override)',
                    priority: 20,
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        const result = getFilteredBackgroundControls(CONTEXT);
        expect(result).toHaveLength(1);
        expect(result[0]?.label).toBe('Package B (override)');
    });

    it('respects registration order over priority in the opposite direction', async () => {
        // Symmetric to the previous test — the docstring promises
        // last-wins regardless of relative priorities.
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'glass',
                    label: 'Package A',
                    priority: 20,
                    render: () => null,
                },
                {
                    id: 'glass',
                    label: 'Package B (override)',
                    priority: 5,
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        const result = getFilteredBackgroundControls(CONTEXT);
        expect(result).toHaveLength(1);
        expect(result[0]?.label).toBe('Package B (override)');
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
                { id: '', label: 'Empty id', render: () => null },
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

    it('drops entries whose priority is not a finite number', async () => {
        // `NaN` / non-numeric priorities produce engine-dependent sort
        // order because the comparator returns `NaN` for every pair
        // touching the offender. Reject them at validation time.
        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'nan',
                    label: 'NaN priority',
                    priority: Number.NaN,
                    render: () => null,
                },
                {
                    id: 'string',
                    label: 'String priority',
                    priority: 'high' as unknown as number,
                    render: () => null,
                },
                {
                    id: 'infinity',
                    label: 'Infinity priority',
                    priority: Number.POSITIVE_INFINITY,
                    render: () => null,
                },
                {
                    id: 'valid-omitted',
                    label: 'Valid (no priority)',
                    render: () => null,
                },
                {
                    id: 'valid-explicit',
                    label: 'Valid (finite)',
                    priority: 15,
                    render: () => null,
                },
            ];
        });

        const { getFilteredBackgroundControls } = await loadModule();

        expect(
            getFilteredBackgroundControls(CONTEXT).map((c) => c.id)
        ).toEqual(['valid-omitted', 'valid-explicit']);
    });

    it('swallows a thrown filter callback and logs to console.error', async () => {
        const errorSpy = vi
            .spyOn(console, 'error')
            .mockImplementation(() => undefined);

        registerFilter(() => {
            throw new Error('boom');
        });

        const { getFilteredBackgroundControls } = await loadModule();

        expect(getFilteredBackgroundControls(CONTEXT)).toEqual([]);
        expect(errorSpy).toHaveBeenCalledOnce();

        errorSpy.mockRestore();
    });

    it('falls back to an empty list when a callback returns a non-array', async () => {
        registerFilter(() => 'not-an-array');

        const { getFilteredBackgroundControls } = await loadModule();

        expect(getFilteredBackgroundControls(CONTEXT)).toEqual([]);
    });

    it('exposes the filter name as a constant', async () => {
        const { BACKGROUND_CONTROLS_FILTER } = await loadModule();

        expect(BACKGROUND_CONTROLS_FILTER).toBe(
            'ap.visualEditor.backgroundControls'
        );
    });
});
