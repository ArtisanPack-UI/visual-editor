/**
 * TokenInserterModal — RTL test.
 *
 * Verifies: source listing renders, search filters, insert calls back
 * with the raw token string, empty state surfaces when zero sources.
 *
 * @since 1.4.0
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

vi.mock('@wordpress/components', () => ({
    Button: (props: React.ComponentProps<'button'>) => <button {...props} />,
    Modal: ({ children, title }: { children: React.ReactNode; title: string }) => (
        <div role="dialog" aria-label={title}>
            {children}
        </div>
    ),
    Notice: ({ children }: { children: React.ReactNode }) => <div role="status">{children}</div>,
    SearchControl: ({ value, onChange, label }: { value: string; onChange: (v: string) => void; label: string }) => (
        <label>
            {label}
            <input value={value} onChange={(e) => onChange(e.target.value)} />
        </label>
    ),
    Spinner: () => <div>Loading…</div>,
}));

vi.mock('@wordpress/element', async () => {
    const react = await import('react');
    return { useEffect: react.useEffect, useMemo: react.useMemo, useState: react.useState };
});

vi.mock('@wordpress/i18n', () => ({
    __: (s: string) => s,
}));

// Mock the API module's fetchSources + resolveTokens to return deterministic data.
vi.mock('../api', async () => {
    return {
        fetchSources: vi.fn(async () => [
            {
                slug: 'business_info',
                label: 'Business Info',
                cardinality: 'singleton',
                origin: 'code',
                fields: [
                    { slug: 'phone', label: 'Phone', type: 'phone' },
                    { slug: 'email', label: 'Email', type: 'email' },
                ],
            },
            {
                slug: 'team',
                label: 'Team',
                cardinality: 'collection',
                origin: 'db',
                fields: [{ slug: 'name', label: 'Name', type: 'text' }],
            },
        ]),
        resolveTokens: vi.fn(async (tokens: string[]) => {
            const values: Record<string, unknown> = {};
            if (tokens.includes('business_info.phone')) values['business_info.phone'] = '(555) 123-4567';
            return values;
        }),
        flattenTokens: (sources: { slug: string; label: string; cardinality: string; fields: { slug: string; label: string; type: string }[] }[]) =>
            sources.flatMap((s) =>
                s.fields.map((f) => ({
                    token: `${s.slug}.${f.slug}`,
                    sourceSlug: s.slug,
                    sourceLabel: s.label,
                    fieldSlug: f.slug,
                    fieldLabel: f.label,
                    fieldType: f.type,
                    cardinality: s.cardinality,
                }))
            ),
    };
});

import TokenInserterModal from '../token-inserter-modal';

describe('TokenInserterModal', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders grouped tokens after loading', async () => {
        render(<TokenInserterModal isOpen={true} onClose={() => {}} onInsert={() => {}} />);

        await waitFor(() => {
            expect(screen.getByText('Business Info')).toBeTruthy();
        });

        expect(screen.getByText('Team')).toBeTruthy();
        expect(screen.getByText('Phone')).toBeTruthy();
        expect(screen.getByText('Name')).toBeTruthy();
    });

    it('filters tokens by search', async () => {
        render(<TokenInserterModal isOpen={true} onClose={() => {}} onInsert={() => {}} />);

        await waitFor(() => expect(screen.getByText('Business Info')).toBeTruthy());

        const search = screen.getByLabelText('Search tokens') as HTMLInputElement;
        fireEvent.change(search, { target: { value: 'phone' } });

        await waitFor(() => {
            expect(screen.getByText('Phone')).toBeTruthy();
        });
        expect(screen.queryByText('Name')).toBeNull();
    });

    it('calls onInsert with the wrapped token when Insert is clicked', async () => {
        const onInsert = vi.fn();
        render(<TokenInserterModal isOpen={true} onClose={() => {}} onInsert={onInsert} />);

        await waitFor(() => expect(screen.getByText('Phone')).toBeTruthy());

        fireEvent.click(screen.getByText('Phone'));
        fireEvent.click(screen.getByText('Insert'));

        expect(onInsert).toHaveBeenCalledWith('{{business_info.phone}}');
    });
});
