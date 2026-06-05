/**
 * Tests for the `artisanpack/details` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/element', () => ({
    useState: <T,>(initial: T): [T, (v: T) => void] => [initial, () => undefined],
    useEffect: () => undefined,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: () => false,
}));

vi.mock('@wordpress/components', () => ({
    TextControl: () => null,
    ToggleControl: () => null,
    __experimentalToolsPanel: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="tools-panel">{children}</div>
    ),
    __experimentalToolsPanelItem: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="tools-panel-item">{children}</div>
    ),
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (
        props?: Record<string, unknown>,
        _opts?: Record<string, unknown>
    ) => ({ ...props, children: null }),
    RichText: () => null,
    store: { name: 'core/block-editor' },
}));

(globalThis as { React?: unknown }).React = require('react');

import DetailsEdit from '../edit';

describe('DetailsEdit', () => {
    it('renders a <details> wrapper', () => {
        const { container } = render(
            <DetailsEdit
                attributes={{ showContent: false }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(container.querySelector('details')).toBeTruthy();
    });

    it('renders a <summary> inside the details', () => {
        const { container } = render(
            <DetailsEdit
                attributes={{ showContent: false }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(container.querySelector('details summary')).toBeTruthy();
    });

    it('adds the wp-block-details class to the wrapper', () => {
        const { container } = render(
            <DetailsEdit
                attributes={{ showContent: false }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(
            container.querySelector('details[class*="wp-block-details"]')
        ).toBeTruthy();
    });

    it('mounts inspector controls', () => {
        const { getAllByTestId } = render(
            <DetailsEdit
                attributes={{ showContent: false }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(getAllByTestId('inspector').length).toBeGreaterThan(0);
    });
});
