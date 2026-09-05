/**
 * Tests for the `artisanpack/business-hours` edit component (#761).
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
    RangeControl: ({ label }: { label?: string }) => <div data-range={label} />,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
}));

(globalThis as { React?: unknown }).React = require('react');

import BusinessHoursEdit from '../edit';

const BASE_ATTRS = {
    showSpecialHours: true,
    specialHoursWindowDays: 30,
};

describe('BusinessHoursEdit', () => {
    it('renders the stub weekly table when the envelope is empty', () => {
        useBusinessInfoMock.mockReturnValue({ envelope: null, loading: false, error: null });

        const { container } = render(
            <BusinessHoursEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        expect(container.textContent).toContain('Business hours (preview)');
        expect(container.textContent).toContain('Monday');
    });

    it('renders resolved hours from the envelope', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: {
                hours: {
                    monday: { open: '09:00', close: '17:00' },
                    sunday: { closed: true },
                },
                specialHours: [
                    { date: '2026-12-25', closed: true, label: 'Christmas' },
                ],
            },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessHoursEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        expect(container.textContent).toContain('Monday');
        expect(container.textContent).toContain('09:00 – 17:00');
        expect(container.textContent).toContain('Sunday');
        expect(container.textContent).toContain('Closed');
        expect(container.textContent).toContain('Christmas');
    });

    it('hides special-hours when the toggle is off', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: {
                hours: { monday: { open: '09:00', close: '17:00' } },
                specialHours: [
                    { date: '2026-12-25', closed: true, label: 'Christmas' },
                ],
            },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessHoursEdit
                attributes={{ ...BASE_ATTRS, showSpecialHours: false }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.textContent).not.toContain('Christmas');
    });

    it('prefers the stamped _resolvedBusinessInfo attribute over the fetched envelope', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { hours: { monday: { open: '00:00', close: '01:00' } } },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessHoursEdit
                attributes={{
                    ...BASE_ATTRS,
                    _resolvedBusinessInfo: {
                        hours: { tuesday: { open: '10:00', close: '14:00' } },
                        specialHours: [],
                    },
                }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.textContent).toContain('Tuesday');
        expect(container.textContent).toContain('10:00 – 14:00');
        expect(container.textContent).not.toContain('00:00');
    });
});
