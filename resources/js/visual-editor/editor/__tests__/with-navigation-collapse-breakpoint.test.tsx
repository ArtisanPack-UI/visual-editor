/**
 * Navigation collapse breakpoint — inspector control + canvas preview (#977).
 */

import { fireEvent, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentType, ReactNode } from 'react';

const filters: Array<{ hook: string; namespace: string; callback: (component: unknown) => unknown }> = [];
const publishedCss: string[] = [];

vi.mock('@wordpress/hooks', () => ({
    addFilter: (hook: string, namespace: string, callback: (component: unknown) => unknown) => {
        filters.push({ hook, namespace, callback });
    },
    hasFilter: () => false,
}));

// `@wordpress/block-editor` cannot load under vitest; the slot is not
// under test, so render the fill inline.
vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: ReactNode }) => <div data-testid="inspector">{children}</div>,
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ title, children }: { title: string; children: ReactNode }) => (
        <section aria-label={title}>{children}</section>
    ),
    SelectControl: ({
        label,
        value,
        options,
        onChange,
    }: {
        label: string;
        value: string;
        options: Array<{ label: string; value: string }>;
        onChange: (next: string) => void;
    }) => (
        <label>
            {label}
            <select value={value} onChange={(event) => onChange(event.target.value)}>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    ),
}));

vi.mock('../../support/canvas-scoped-styles', () => ({
    useCanvasScopedStyle: (_channel: string, _clientId: string, css: string) => {
        if (css !== '') {
            publishedCss.push(css);
        }
    },
}));

import { BreakpointRegistry, setResponsiveRegistry, TAILWIND_V4_DEFAULTS } from '../../responsive/registry';
import {
    collapseBreakpointOptions,
    registerNavigationCollapseBreakpoint,
    withNavigationCollapseCanvas,
    withNavigationCollapseControl,
} from '../with-navigation-collapse-breakpoint';

const REGISTERED_KEY = Symbol.for('artisanpack-ui.visual-editor.navigation-collapse-breakpoint.registered');

beforeEach(() => {
    filters.length = 0;
    publishedCss.length = 0;
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
});

afterEach(() => {
    setResponsiveRegistry(null);
});

describe('collapseBreakpointOptions', () => {
    it('offers the default plus the registry mobile and tablet widths', () => {
        expect(collapseBreakpointOptions()).toEqual([
            { label: 'Default (600px)', value: '' },
            { label: 'Mobile (≤767px)', value: 'mobile' },
            { label: 'Tablet (≤1023px)', value: 'tablet' },
        ]);
    });

    it('follows a host registry and drops keys it does not define', () => {
        setResponsiveRegistry(
            new BreakpointRegistry([
                ...TAILWIND_V4_DEFAULTS.filter((bp) => bp.key !== 'tablet' && bp.key !== 'mobile'),
                { key: 'mobile', maxWidthPx: 599, previewWidthPx: 375, label: 'Mobile' },
            ])
        );

        expect(collapseBreakpointOptions()).toEqual([
            { label: 'Default (600px)', value: '' },
            { label: 'Mobile (≤599px)', value: 'mobile' },
        ]);
    });
});

describe('withNavigationCollapseControl', () => {
    const BlockEdit = (): JSX.Element => <div data-testid="edit" />;
    const Wrapped = withNavigationCollapseControl(BlockEdit as ComponentType<never>) as ComponentType<{
        name: string;
        attributes: Record<string, unknown>;
        setAttributes: (updates: Record<string, unknown>) => void;
    }>;

    it('adds the Collapse below select for a mobile-overlay navigation', () => {
        const setAttributes = vi.fn();
        const { getByLabelText } = render(
            <Wrapped name="core/navigation" attributes={{ collapseBreakpoint: 'tablet' }} setAttributes={setAttributes} />
        );

        const select = getByLabelText('Collapse below') as HTMLSelectElement;

        expect(select.value).toBe('tablet');

        fireEvent.change(select, { target: { value: 'mobile' } });
        expect(setAttributes).toHaveBeenLastCalledWith({ collapseBreakpoint: 'mobile' });

        fireEvent.change(select, { target: { value: '' } });
        expect(setAttributes).toHaveBeenLastCalledWith({ collapseBreakpoint: undefined });
    });

    it('adds nothing to other blocks or a non-mobile overlay', () => {
        const other = render(<Wrapped name="core/paragraph" attributes={{}} setAttributes={vi.fn()} />);
        expect(other.queryByTestId('inspector')).toBeNull();

        const always = render(
            <Wrapped name="core/navigation" attributes={{ overlayMenu: 'always' }} setAttributes={vi.fn()} />
        );
        expect(always.queryByTestId('inspector')).toBeNull();
    });
});

describe('withNavigationCollapseCanvas', () => {
    const BlockListBlock = ({ wrapperProps }: { wrapperProps?: Record<string, unknown> }): JSX.Element => (
        <div data-testid="block" className={(wrapperProps?.className as string | undefined) ?? ''} />
    );
    const Wrapped = withNavigationCollapseCanvas(BlockListBlock as ComponentType<never>) as ComponentType<{
        name: string;
        clientId: string;
        attributes: Record<string, unknown>;
        wrapperProps?: Record<string, unknown>;
    }>;

    it('stamps the class and publishes the tablet rules into the canvas', () => {
        const { getByTestId } = render(
            <Wrapped
                name="core/navigation"
                clientId="nav-1"
                attributes={{ collapseBreakpoint: 'tablet' }}
                wrapperProps={{ className: 'existing' }}
            />
        );

        expect(getByTestId('block').className).toBe('existing collapses-at-tablet');
        expect(publishedCss[0]).toContain('@media (max-width: 1023px) {.wp-block-navigation.collapses-at-tablet ');
        expect(publishedCss[0]).toContain('@media (min-width: 1024px) {');
    });

    it('leaves a default navigation alone', () => {
        const { getByTestId } = render(<Wrapped name="core/navigation" clientId="nav-1" attributes={{}} />);

        expect(getByTestId('block').className).toBe('');
        expect(publishedCss).toEqual([]);
    });
});

describe('registerNavigationCollapseBreakpoint', () => {
    it('installs the BlockEdit and BlockListBlock filters once', () => {
        registerNavigationCollapseBreakpoint();
        registerNavigationCollapseBreakpoint();

        expect(filters.map((filter) => filter.hook)).toEqual(['editor.BlockEdit', 'editor.BlockListBlock']);
    });
});

describe('navigationCollapseCss', () => {
    it('matches the React and Vue renderers byte for byte', async () => {
        const { navigationCollapseCss } = await import('../navigation-collapse-breakpoint');
        const react = await import('../../../../../packages/visual-editor-renderer-react/src/support/navigationCollapseCss');
        const vue = await import('../../../../../packages/visual-editor-renderer-vue/src/support/navigationCollapseCss');

        expect(navigationCollapseCss('tablet', 1023)).toBe(react.navigationCollapseCss('tablet', 1023));
        expect(navigationCollapseCss('mobile', 767)).toBe(vue.navigationCollapseCss('mobile', 767));
    });
});
