/**
 * Smoke tests for the `artisanpack/column` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (format: string, ...args: unknown[]) =>
        args.reduce<string>(
            (acc, arg, i) =>
                acc.replace(new RegExp(`%${i + 1}\\$[sd]`), String(arg)),
            format
        ),
}));

vi.mock('@wordpress/components', () => ({
    __experimentalUseCustomUnits: () => [],
    __experimentalUnitControl: () => null,
    __experimentalToolsPanel: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalToolsPanelItem: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
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

vi.mock('@wordpress/element', () => ({
    // Honor React's lazy initializer semantics — if `initial` is a function,
    // call it to derive the initial state (matches `useActiveBreakpointValue`'s
    // `useState(() => ...)` usage in the controls under test).
    useState: <T,>(initial: T | (() => T)): [T, (v: T) => void] => [
        typeof initial === 'function' ? (initial as () => T)() : initial,
        () => undefined,
    ],
    useEffect: () => undefined,
    useMemo: <T,>(fn: () => T): T => fn(),
    useRef: <T,>(): { current: T | undefined } => ({ current: undefined }),
}));

vi.mock('@wordpress/data', () => ({
    useSelect: (cb: (s: unknown) => unknown) =>
        cb(() => ({
            getBlockOrder: () => [],
            getBlockRootClientId: () => 'root',
            getBlockName: () => null,
            getBlockAttributes: () => ({}),
        })),
    useDispatch: () => ({ updateBlockAttributes: () => undefined }),
}));

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: { ButtonBlockAppender: () => null },
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    BlockVerticalAlignmentToolbar: () => null,
    useBlockProps: (props?: Record<string, unknown>) => ({
        ...props,
        'aria-label': 'Block: Column',
    }),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({ ...props }),
    useSettings: () => [['%', 'px']],
    store: {},
}));

(globalThis as { React?: unknown }).React = require('react');

import ColumnEdit from '../edit';

describe('ColumnEdit', () => {
    it('mounts a div wrapper with the wp-block-column class', () => {
        const { container } = render(
            <ColumnEdit
                attributes={{ width: '50%' }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(
            container.querySelector('div[class*="wp-block-column"]')
        ).toBeTruthy();
    });

    it('mounts inspector controls', () => {
        const { getByTestId } = render(
            <ColumnEdit
                attributes={{ width: '50%' }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(getByTestId('inspector')).toBeTruthy();
    });
});
