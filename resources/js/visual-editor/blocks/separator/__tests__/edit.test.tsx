/**
 * Tests for the `artisanpack/separator` edit component.
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

vi.mock('@wordpress/compose', () => ({
    usePrevious: () => undefined,
}));

vi.mock('@wordpress/components', () => ({
    HorizontalRule: ((props: Record<string, unknown>) => (
        <hr {...props} />
    )) as unknown as React.FC<Record<string, unknown>>,
    SelectControl: () => null,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
    __experimentalUseColorProps: () => ({ className: '', style: {} }),
}));

(globalThis as { React?: unknown }).React = require('react');

import SeparatorEdit from '../edit';

describe('SeparatorEdit', () => {
    it('renders an hr wrapper by default', () => {
        const { container } = render(
            <SeparatorEdit
                attributes={{ opacity: 'alpha-channel', tagName: 'hr' }}
                setAttributes={vi.fn()}
            />
        );
        expect(container.querySelector('hr')).toBeTruthy();
    });

    it('renders a div wrapper when tagName is div', () => {
        const { container } = render(
            <SeparatorEdit
                attributes={{ opacity: 'alpha-channel', tagName: 'div' }}
                setAttributes={vi.fn()}
            />
        );
        expect(container.querySelector('div[class*="wp-block-separator"]')).toBeTruthy();
    });

    it('mounts inspector controls', () => {
        const { getByTestId } = render(
            <SeparatorEdit
                attributes={{ opacity: 'alpha-channel', tagName: 'hr' }}
                setAttributes={vi.fn()}
            />
        );
        expect(getByTestId('inspector')).toBeTruthy();
    });
});
