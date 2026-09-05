/**
 * Tests for the `artisanpack/business-phone` edit component (#761).
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

import BusinessPhoneEdit from '../edit';

const BASE_ATTRS = { label: '', showIcon: true };

describe('BusinessPhoneEdit', () => {
    it('renders the stub number when the envelope has no phone', () => {
        useBusinessInfoMock.mockReturnValue({ envelope: null, loading: false, error: null });

        const { container } = render(
            <BusinessPhoneEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        expect(container.textContent).toContain('(555) 123-4567');
    });

    it('renders a tel: link from the fetched phone number', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { phone: '+1 555-000-1111' },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessPhoneEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        const link = container.querySelector('a.ap-business-phone__link');
        expect(link).not.toBeNull();
        expect(link?.getAttribute('href')).toBe('tel:+15550001111');
        expect(container.textContent).toContain('+1 555-000-1111');
    });

    it('prefers the stamped _resolvedBusinessInfo attribute over the fetched envelope', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { phone: '+1 000-000-0000' },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessPhoneEdit
                attributes={{
                    ...BASE_ATTRS,
                    _resolvedBusinessInfo: { phone: '+1 555-1234' },
                }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.textContent).toContain('+1 555-1234');
        expect(container.textContent).not.toContain('+1 000-000-0000');
    });

    it('honours a custom label attribute while keeping the tel: target unchanged', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { phone: '+1 555-9999' },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessPhoneEdit
                attributes={{ label: 'Call the office', showIcon: false }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.textContent).toContain('Call the office');
        const link = container.querySelector('a.ap-business-phone__link');
        expect(link?.getAttribute('href')).toBe('tel:+15559999');
    });
});
