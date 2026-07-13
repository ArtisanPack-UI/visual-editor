/**
 * Tests for the `editor.BlockEdit` HOC that mounts the
 * `ap.visual-editor.background-controls` filter (#649).
 *
 * The HOC gates on whether the block type declares a background-related
 * support. These tests exercise the AC "at least one test demonstrating
 * an external filter adds a control to a supported block and does not
 * add it to an unsupported block", plus the priority contract.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

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

// The HOC only uses `InspectorControls` as a pass-through wrapper.
// Rendering a plain `<div>` is enough for the DOM assertions below and
// keeps the block-editor stylesheets / slot-fill machinery out of the
// test environment.
vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children?: ReactNode }) => (
        <div data-testid="inspector-controls">{children}</div>
    ),
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({
        title,
        children,
    }: {
        title?: string;
        initialOpen?: boolean;
        children?: ReactNode;
    }) => (
        <div data-testid="panel-body" data-title={title}>
            {children}
        </div>
    ),
}));

// `@wordpress/compose`'s real `createHigherOrderComponent` reaches into
// the `element` package for `Symbol.for( 'react.element' )` lookups
// that aren't needed here. A minimal HOC wrapper is enough.
vi.mock('@wordpress/compose', () => ({
    createHigherOrderComponent:
        <TProps,>(fn: (Comp: React.ComponentType<TProps>) => React.ComponentType<TProps>) =>
        (Comp: React.ComponentType<TProps>) =>
            fn(Comp),
}));

const registeredBlockSupports = new Map<string, Record<string, unknown>>();

vi.mock('@wordpress/blocks', () => ({
    getBlockType: (
        name: string
    ): { supports?: Record<string, unknown> } | undefined => {
        const supports = registeredBlockSupports.get(name);
        if (!supports) {
            return undefined;
        }
        return { supports };
    },
}));

function registerFilter(callback: FilterCallback): void {
    filters.set('ap.visual-editor.background-controls', [
        ...(filters.get('ap.visual-editor.background-controls') ?? []),
        callback,
    ]);
}

beforeEach(() => {
    filters.clear();
    registeredBlockSupports.clear();
    vi.resetModules();
});

async function loadHOC(): Promise<
    typeof import('../with-background-controls')
> {
    return await import('../with-background-controls');
}

const InnerEdit = (props: { name: string }): JSX.Element => (
    <div data-testid="inner-edit" data-name={props.name}>
        inner
    </div>
);

describe('withBackgroundControls', () => {
    it('adds a filter-registered control to a block that supports background', async () => {
        registeredBlockSupports.set('artisanpack/group', { background: true });

        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'liquid-glass',
                    label: 'Liquid Glass',
                    render: () => (
                        <span data-testid="glass-panel">glass</span>
                    ),
                },
            ];
        });

        const { withBackgroundControls } = await loadHOC();
        const Wrapped = withBackgroundControls(InnerEdit);

        render(
            <Wrapped
                name="artisanpack/group"
                clientId="c-1"
                attributes={{}}
                setAttributes={() => undefined}
            />
        );

        expect(screen.getByTestId('inner-edit')).toBeInTheDocument();
        expect(screen.getByTestId('inspector-controls')).toBeInTheDocument();
        expect(screen.getByTestId('glass-panel')).toBeInTheDocument();
        expect(
            screen.getByTestId('panel-body').getAttribute('data-title')
        ).toBe('Liquid Glass');
    });

    it('does not render the inspector wrapper on a block that does not support background', async () => {
        registeredBlockSupports.set('artisanpack/separator', {
            color: { background: false },
        });

        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'liquid-glass',
                    label: 'Liquid Glass',
                    render: () => (
                        <span data-testid="glass-panel">glass</span>
                    ),
                },
            ];
        });

        const { withBackgroundControls } = await loadHOC();
        const Wrapped = withBackgroundControls(InnerEdit);

        render(
            <Wrapped
                name="artisanpack/separator"
                clientId="c-2"
                attributes={{}}
                setAttributes={() => undefined}
            />
        );

        expect(screen.getByTestId('inner-edit')).toBeInTheDocument();
        expect(screen.queryByTestId('inspector-controls')).toBeNull();
        expect(screen.queryByTestId('glass-panel')).toBeNull();
    });

    it('renders nothing extra when no filter callback returns controls', async () => {
        registeredBlockSupports.set('artisanpack/group', { background: true });

        const { withBackgroundControls } = await loadHOC();
        const Wrapped = withBackgroundControls(InnerEdit);

        render(
            <Wrapped
                name="artisanpack/group"
                clientId="c-3"
                attributes={{}}
                setAttributes={() => undefined}
            />
        );

        expect(screen.getByTestId('inner-edit')).toBeInTheDocument();
        expect(screen.queryByTestId('inspector-controls')).toBeNull();
    });

    it('passes the block context to filter callbacks', async () => {
        registeredBlockSupports.set('artisanpack/group', { background: true });

        let received: unknown = null;
        const setAttributes = (): void => undefined;
        const attributes = { style: { background: { color: '#fff' } } };

        registerFilter((value, context) => {
            received = context;
            return value;
        });

        const { withBackgroundControls } = await loadHOC();
        const Wrapped = withBackgroundControls(InnerEdit);

        render(
            <Wrapped
                name="artisanpack/group"
                clientId="c-4"
                attributes={attributes}
                setAttributes={setAttributes}
            />
        );

        expect(received).toEqual({
            attributes,
            setAttributes,
            clientId: 'c-4',
            blockName: 'artisanpack/group',
            blockSupports: { background: true },
        });
    });

    it('renders controls in priority order (lower first)', async () => {
        registeredBlockSupports.set('artisanpack/group', { background: true });

        registerFilter((value) => {
            const list = value as unknown[];
            return [
                ...list,
                {
                    id: 'late',
                    label: 'Late',
                    priority: 30,
                    render: () => <span data-testid="control-late">late</span>,
                },
                {
                    id: 'early',
                    label: 'Early',
                    priority: 5,
                    render: () => (
                        <span data-testid="control-early">early</span>
                    ),
                },
            ];
        });

        const { withBackgroundControls } = await loadHOC();
        const Wrapped = withBackgroundControls(InnerEdit);

        render(
            <Wrapped
                name="artisanpack/group"
                clientId="c-5"
                attributes={{}}
                setAttributes={() => undefined}
            />
        );

        const panels = screen.getAllByTestId('panel-body');
        expect(panels.map((p) => p.getAttribute('data-title'))).toEqual([
            'Early',
            'Late',
        ]);
    });
});

describe('blockSupportsBackground', () => {
    it('accepts `supports.background: true`', async () => {
        const { blockSupportsBackground } = await loadHOC();
        expect(blockSupportsBackground({ background: true })).toBe(true);
    });

    it('accepts `supports.background` as an object', async () => {
        const { blockSupportsBackground } = await loadHOC();
        expect(
            blockSupportsBackground({ background: { backgroundImage: true } })
        ).toBe(true);
    });

    it('accepts `supports.color: true`', async () => {
        const { blockSupportsBackground } = await loadHOC();
        expect(blockSupportsBackground({ color: true })).toBe(true);
    });

    it('accepts `supports.color` as an object without an explicit `background: false`', async () => {
        const { blockSupportsBackground } = await loadHOC();
        expect(blockSupportsBackground({ color: {} })).toBe(true);
        expect(
            blockSupportsBackground({ color: { text: true } })
        ).toBe(true);
    });

    it('rejects `supports.color.background: false`', async () => {
        const { blockSupportsBackground } = await loadHOC();
        expect(
            blockSupportsBackground({ color: { background: false } })
        ).toBe(false);
    });

    it('rejects an empty supports object', async () => {
        const { blockSupportsBackground } = await loadHOC();
        expect(blockSupportsBackground({})).toBe(false);
    });
});
