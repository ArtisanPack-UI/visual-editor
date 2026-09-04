/**
 * Tests for the `artisanpack/business-email` edit component (#761).
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('../../../vendor/i18n', () => ({
    TEXT_DOMAIN: 'artisanpack-visual-editor',
}));

const useBusinessInfoMock = vi.fn();

vi.mock('../../_shared/use-business-info', () => ({
    useBusinessInfo: (...args: unknown[]) => useBusinessInfoMock(...args),
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children?: React.ReactNode }) => <section>{children}</section>,
    ToggleControl: ({ label }: { label?: string }) => <label data-toggle={label} />,
    TextControl: ({ label }: { label?: string }) => <div data-text={label} />,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
}));

(globalThis as { React?: unknown }).React = require('react');

import BusinessEmailEdit from '../edit';

const BASE_ATTRS = { label: '', showIcon: true };

describe('BusinessEmailEdit', () => {
    it('renders the stub address when the envelope has no email', () => {
        useBusinessInfoMock.mockReturnValue({ envelope: null, loading: false, error: null });

        const { container } = render(
            <BusinessEmailEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        expect(container.textContent).toContain('hello@example.com');
    });

    it('renders a mailto: link from the fetched email address', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { email: 'hi@example.test' },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessEmailEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        const link = container.querySelector('a.ap-business-email__link');
        expect(link).not.toBeNull();
        expect(link?.getAttribute('href')).toBe('mailto:hi@example.test');
        expect(container.textContent).toContain('hi@example.test');
    });

    it('does not build a mailto: link for an invalid email address', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { email: 'not-a-valid-email' },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessEmailEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        const link = container.querySelector('a.ap-business-email__link');
        expect(link?.getAttribute('href')).not.toContain('mailto:');
    });

    it('prefers the stamped _resolvedBusinessInfo attribute over the fetched envelope', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { email: 'from-fetch@example.test' },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessEmailEdit
                attributes={{
                    ...BASE_ATTRS,
                    _resolvedBusinessInfo: { email: 'from-stamp@example.test' },
                }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.textContent).toContain('from-stamp@example.test');
        expect(container.textContent).not.toContain('from-fetch@example.test');
    });
});
