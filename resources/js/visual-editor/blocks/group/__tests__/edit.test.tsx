/**
 * Tests for the `artisanpack/group` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/element', () => ({
    // Honor React's lazy initializer semantics — `useActiveBreakpointValue`
    // calls `useState(() => ...)` and the mock has to execute the function
    // or the state ends up holding the initializer itself.
    useState: <T,>(initial: T | (() => T)): [T, (v: T) => void] => [
        typeof initial === 'function' ? (initial as () => T)() : initial,
        () => undefined,
    ],
    useEffect: () => undefined,
    useMemo: <T,>(fn: () => T): T => fn(),
    useRef: <T,>(): { current: T | undefined } => ({ current: undefined }),
}));

vi.mock('@wordpress/components', () => ({
    SelectControl: () => null,
    Placeholder: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="placeholder">{children}</div>
    ),
    Button: () => null,
    PanelBody: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="panel-body">{children}</div>
    ),
    ToggleControl: () => null,
    __experimentalToggleGroupControl: ({ children }: { children?: React.ReactNode }) => (
        <div>{children}</div>
    ),
    __experimentalToggleGroupControlOption: () => null,
    __experimentalNumberControl: () => null,
    TextControl: () => null,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: (callback: (s: unknown) => unknown) =>
        callback(() => ({
            getBlock: () => ({ innerBlocks: [] }),
            getSettings: () => ({ supportsLayout: true }),
            getBlockVariations: () => [],
            getBlockRootClientId: () => null,
            getBlockName: () => null,
            getBlockAttributes: () => ({}),
        })),
    useDispatch: () => ({ selectBlock: () => undefined }),
}));

vi.mock('@wordpress/blocks', () => ({
    store: 'blocks-store',
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    InnerBlocks: { ButtonBlockAppender: () => null },
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({
        ...props,
        children: null,
    }),
    store: 'block-editor-store',
}));

(globalThis as { React?: unknown }).React = require('react');

import GroupEdit from '../edit';

describe('GroupEdit', () => {
    it('renders a div wrapper by default', () => {
        const { container } = render(
            <GroupEdit
                attributes={{ tagName: 'div', backgroundColor: 'red' }}
                name="artisanpack/group"
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(container.querySelector('div[class*="wp-block-group"]')).toBeTruthy();
    });

    it('mounts inspector controls', () => {
        const { getAllByTestId } = render(
            <GroupEdit
                attributes={{ tagName: 'div', backgroundColor: 'red' }}
                name="artisanpack/group"
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        // One inspector slot for tagName (advanced) + one for the
        // #595 flex layout panels — both are valid `<InspectorControls>`
        // mounts in the mock.
        expect(getAllByTestId('inspector').length).toBeGreaterThanOrEqual(1);
    });

    it('renders the configured tagName when layout support is enabled', () => {
        const { container } = render(
            <GroupEdit
                attributes={{
                    tagName: 'section',
                    backgroundColor: 'red',
                    layout: { type: 'flex' },
                }}
                name="artisanpack/group"
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(container.querySelector('section')).toBeTruthy();
    });
});
