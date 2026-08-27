/**
 * ArtisanPackLinkControl — RTL test (#662).
 *
 * Verifies: the Link tab renders the stock control; the Dynamic Content
 * tab lists only link-eligible fields; picking a field calls `onChange`
 * with a scheme-appropriate raw-token href.
 *
 * @since 1.7.0
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

vi.mock('@wordpress/block-editor', () => ({
    __experimentalLinkControl: ({
        value,
        onChange,
    }: {
        value?: { url?: string };
        onChange: (next: { url?: string }) => void;
    }) => (
        <div data-testid="stock-link-control">
            <input
                aria-label="URL"
                value={value?.url ?? ''}
                onChange={(e) => onChange({ ...(value ?? {}), url: e.target.value })}
            />
        </div>
    ),
}));

vi.mock('@wordpress/components', async () => {
    const react = await import('react');
    return {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        TabPanel: ({ tabs, children }: any) => {
            const [active, setActive] = react.useState(tabs[0].name);
            const activeTab =
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                tabs.find((t: any) => t.name === active) ?? tabs[0];
            return (
                <div>
                    {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                    {tabs.map((t: any) => (
                        <button key={t.name} onClick={() => setActive(t.name)}>
                            {t.title}
                        </button>
                    ))}
                    {children(activeTab)}
                </div>
            );
        },
        SearchControl: ({
            value,
            onChange,
            label,
        }: {
            value: string;
            onChange: (v: string) => void;
            label: string;
        }) => (
            <label>
                {label}
                <input value={value} onChange={(e) => onChange(e.target.value)} />
            </label>
        ),
        Spinner: () => <div>Loading…</div>,
        Notice: ({ children }: { children: React.ReactNode }) => (
            <div role="status">{children}</div>
        ),
    };
});

vi.mock('@wordpress/element', async () => {
    const react = await import('react');
    return { useEffect: react.useEffect, useMemo: react.useMemo, useState: react.useState };
});

vi.mock('@wordpress/i18n', () => ({ __: (s: string) => s }));

vi.mock('../api', () => ({
    fetchSources: vi.fn(async () => [
        {
            slug: 'business_info',
            label: 'Business Info',
            cardinality: 'singleton',
            origin: 'code',
            fields: [
                { slug: 'email', label: 'Email', type: 'email' },
                { slug: 'phone', label: 'Phone', type: 'phone' },
                { slug: 'website', label: 'Website', type: 'url' },
                { slug: 'logo', label: 'Logo', type: 'image' },
            ],
        },
    ]),
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    flattenTokens: (sources: any[]) =>
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        sources.flatMap((s: any) =>
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            s.fields.map((f: any) => ({
                token: `${s.slug}.${f.slug}`,
                sourceSlug: s.slug,
                sourceLabel: s.label,
                fieldSlug: f.slug,
                fieldLabel: f.label,
                fieldType: f.type,
                cardinality: s.cardinality,
            }))
        ),
}));

import {
    ArtisanPackLinkControl,
    buildDynamicContentHref,
    schemeForFieldType,
} from '../link-control';

describe('buildDynamicContentHref', () => {
    it('prefixes email with mailto: and phone with tel:', () => {
        expect(schemeForFieldType('email')).toBe('mailto');
        expect(schemeForFieldType('phone')).toBe('tel');
        expect(schemeForFieldType('url')).toBeUndefined();

        expect(buildDynamicContentHref('email', 'business_info.email')).toBe(
            'mailto:{{business_info.email}}'
        );
        expect(buildDynamicContentHref('phone', 'business_info.phone')).toBe(
            'tel:{{business_info.phone}}'
        );
        expect(buildDynamicContentHref('url', 'business_info.website')).toBe(
            '{{business_info.website}}'
        );
    });
});

describe('ArtisanPackLinkControl', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the stock link control on the Link tab', () => {
        render(<ArtisanPackLinkControl value={{ url: 'https://x.test' }} onChange={() => {}} />);
        expect(screen.getByTestId('stock-link-control')).toBeTruthy();
    });

    it('lists only link-eligible Dynamic Content fields on the Dynamic Content tab', async () => {
        render(<ArtisanPackLinkControl value={{}} onChange={() => {}} />);

        fireEvent.click(screen.getByText('Dynamic Content'));

        await waitFor(() => expect(screen.getByText('Email')).toBeTruthy());
        expect(screen.getByText('Phone')).toBeTruthy();
        expect(screen.getByText('Website')).toBeTruthy();
        // `image` is not a link-eligible field type.
        expect(screen.queryByText('Logo')).toBeNull();
    });

    it('calls onChange with a scheme-appropriate href when a field is picked', async () => {
        const onChange = vi.fn();
        render(<ArtisanPackLinkControl value={{ opensInNewTab: true }} onChange={onChange} />);

        fireEvent.click(screen.getByText('Dynamic Content'));
        await waitFor(() => expect(screen.getByText('Email')).toBeTruthy());

        fireEvent.click(screen.getByText('Email'));

        expect(onChange).toHaveBeenCalledWith({
            opensInNewTab: true,
            url: 'mailto:{{business_info.email}}',
        });
    });
});
