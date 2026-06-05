/**
 * Tests for the `artisanpack/button` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/components', () => ({
    SelectControl: () => null,
    TextControl: () => null,
    ToolbarButton: () => null,
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    RichText: (props: Record<string, unknown>) => (
        <span data-testid="rich-text" data-value={String(props.value ?? '')} />
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    __experimentalUseBorderProps: () => ({ className: '', style: {} }),
    __experimentalUseColorProps: () => ({ className: '', style: {} }),
    __experimentalGetSpacingClassesAndStyles: () => ({ style: {} }),
    __experimentalGetShadowClassesAndStyles: () => ({ style: {} }),
    __experimentalGetElementClassName: () => undefined,
    getTypographyClassesAndStyles: () => ({ className: '', style: {} }),
}));

(globalThis as { React?: unknown }).React = require('react');

import ButtonEdit from '../edit';

describe('ButtonEdit', () => {
    it('renders a div with wp-block-button by default', () => {
        const { container } = render(
            <ButtonEdit attributes={{}} setAttributes={vi.fn()} />
        );
        expect(
            container.querySelector('div[class*="wp-block-button"]')
        ).toBeTruthy();
    });

    it('renders the rich-text editor for button text', () => {
        const { getByTestId } = render(
            <ButtonEdit
                attributes={{ text: 'Hello' }}
                setAttributes={vi.fn()}
            />
        );
        expect(getByTestId('rich-text').getAttribute('data-value')).toBe(
            'Hello'
        );
    });

    it('mounts inspector controls', () => {
        const { getByTestId } = render(
            <ButtonEdit attributes={{}} setAttributes={vi.fn()} />
        );
        expect(getByTestId('inspector')).toBeTruthy();
    });

    it('mounts block controls when tagName is the default <a>', () => {
        const { getByTestId } = render(
            <ButtonEdit
                attributes={{ tagName: 'a' }}
                setAttributes={vi.fn()}
            />
        );
        expect(getByTestId('block-controls')).toBeTruthy();
    });

    it('does not mount the link block-control when tagName is button', () => {
        const { queryByTestId } = render(
            <ButtonEdit
                attributes={{ tagName: 'button' }}
                setAttributes={vi.fn()}
            />
        );
        expect(queryByTestId('block-controls')).toBeNull();
    });
});
