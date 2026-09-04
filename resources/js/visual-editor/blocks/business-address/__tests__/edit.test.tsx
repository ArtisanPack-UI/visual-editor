/**
 * Tests for the `artisanpack/business-address` edit component (#761).
 *
 * The edit renders the address + map iframe from the resolved
 * `businessInfo` envelope, falling back to a placeholder when no data
 * is available. The tests mock the shared `useBusinessInfo` hook so
 * the fetch never runs.
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
    SelectControl: ({ label }: { label?: string }) => <div data-select={label} />,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
}));

(globalThis as { React?: unknown }).React = require('react');

import BusinessAddressEdit from '../edit';

const BASE_ATTRS = {
    mapProvider: 'osm' as const,
    showMap: true,
    zoom: 15,
};

describe('BusinessAddressEdit', () => {
    it('renders the placeholder when the envelope has no address content', () => {
        useBusinessInfoMock.mockReturnValue({ envelope: null, loading: false, error: null });

        const { container } = render(
            <BusinessAddressEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        expect(container.textContent).toContain('Business address (preview)');
        expect(container.querySelector('iframe')).toBeNull();
    });

    it('renders the fetched address and map iframe when the envelope is populated', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: {
                address: {
                    street: '123 Main St',
                    city: 'Springfield',
                    region: 'IL',
                    postal_code: '62701',
                    country: 'USA',
                },
                mapEmbedUrl: 'https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4',
            },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessAddressEdit attributes={BASE_ATTRS} setAttributes={vi.fn()} />
        );

        expect(container.textContent).toContain('123 Main St');
        expect(container.textContent).toContain('Springfield, IL 62701');
        expect(container.textContent).toContain('USA');

        const iframe = container.querySelector('iframe');
        expect(iframe).not.toBeNull();
        expect(iframe?.getAttribute('src')).toContain('openstreetmap.org');
    });

    it('prefers the stamped _resolvedBusinessInfo attribute over the fetched envelope', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: { address: { street: 'From fetch' } },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessAddressEdit
                attributes={{
                    ...BASE_ATTRS,
                    _resolvedBusinessInfo: {
                        address: { street: 'From stamp' },
                        mapEmbedUrl: null,
                    },
                }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.textContent).toContain('From stamp');
        expect(container.textContent).not.toContain('From fetch');
    });

    it('omits the iframe when showMap is false', () => {
        useBusinessInfoMock.mockReturnValue({
            envelope: {
                address: { street: '123 Main St' },
                mapEmbedUrl: 'https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4',
            },
            loading: false,
            error: null,
        });

        const { container } = render(
            <BusinessAddressEdit
                attributes={{ ...BASE_ATTRS, showMap: false }}
                setAttributes={vi.fn()}
            />
        );

        expect(container.querySelector('iframe')).toBeNull();
    });
});
