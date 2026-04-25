import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentType } from 'react';

const filters: Array<{
    hook: string;
    namespace: string;
    callback: (component: unknown) => unknown;
}> = [];

vi.mock('@wordpress/hooks', () => ({
    addFilter: (
        hook: string,
        namespace: string,
        callback: (component: unknown) => unknown
    ) => {
        filters.push({ hook, namespace, callback });
    },
}));

// Mirror the production sentinel so we can clear it between tests.
// `registerSyncedPatternIndicator` stores its dedup flag on
// `globalThis` keyed by `Symbol.for(...)` so it survives bundle
// reloads — perfect in production, but it would also leak between
// test cases here unless we explicitly reset it.
const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.synced-pattern-indicator.registered'
);

beforeEach(() => {
    filters.length = 0;
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
    vi.resetModules();
});

afterEach(() => {
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
    vi.resetModules();
});

async function loadAndRegister(): Promise<{
    callback: (component: unknown) => unknown;
}> {
    const mod = await import('../synced-pattern-indicator');
    mod.registerSyncedPatternIndicator();

    const filter = filters[0];

    if (filter === undefined) {
        throw new Error('addFilter was not called');
    }

    return { callback: filter.callback };
}

describe('registerSyncedPatternIndicator', () => {
    it('registers the editor.BlockListBlock filter exactly once', async () => {
        const mod = await import('../synced-pattern-indicator');

        mod.registerSyncedPatternIndicator();
        mod.registerSyncedPatternIndicator();
        mod.registerSyncedPatternIndicator();

        expect(filters).toHaveLength(1);
        expect(filters[0]?.hook).toBe('editor.BlockListBlock');
    });

    it('wraps core/block with a synced badge', async () => {
        const { callback } = await loadAndRegister();
        const wrap = callback as (
            component: ComponentType<{ name?: string }>
        ) => ComponentType<{ name?: string }>;

        const Wrapped = wrap(({ name }) => (
            <div data-testid="inner-block">{name}</div>
        ));

        render(<Wrapped name="core/block" />);

        expect(
            screen.getByTestId('ap-synced-pattern-indicator')
        ).toBeInTheDocument();
        expect(screen.getByTestId('inner-block')).toHaveTextContent(
            'core/block'
        );
    });

    it('does not wrap non-core/block blocks', async () => {
        const { callback } = await loadAndRegister();
        const wrap = callback as (
            component: ComponentType<{ name?: string }>
        ) => ComponentType<{ name?: string }>;

        const Wrapped = wrap(({ name }) => (
            <div data-testid="inner-block">{name}</div>
        ));

        render(<Wrapped name="core/paragraph" />);

        expect(
            screen.queryByTestId('ap-synced-pattern-indicator')
        ).toBeNull();
    });
});
