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
    useState: <T,>(initial: T): [T, (v: T) => void] => [initial, () => undefined],
    useEffect: () => undefined,
    useRef: <T,>(): { current: T | undefined } => ({ current: undefined }),
}));

vi.mock('@wordpress/components', () => ({
    SelectControl: () => null,
    Placeholder: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="placeholder">{children}</div>
    ),
    Button: () => null,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: (callback: (s: unknown) => unknown) =>
        callback(() => ({
            getBlock: () => ({ innerBlocks: [] }),
            getSettings: () => ({ supportsLayout: true }),
            getBlockVariations: () => [],
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
        const { getByTestId } = render(
            <GroupEdit
                attributes={{ tagName: 'div', backgroundColor: 'red' }}
                name="artisanpack/group"
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(getByTestId('inspector')).toBeTruthy();
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
