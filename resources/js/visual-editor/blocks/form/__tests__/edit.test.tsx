/**
 * Tests for the `artisanpack/form` edit component's layout option (#916).
 *
 * Covers the inspector's Layout toggle and the canvas preview: stacked
 * keeps the column layout with the button below the fields, inline puts
 * the fields and the button on one wrapping row.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (text: string) => text,
}));

vi.mock('@wordpress/components', () => ({
    Button: ({ children, onClick }: { children?: React.ReactNode; onClick?: () => void }) => (
        <button type="button" onClick={onClick}>
            {children}
        </button>
    ),
    Placeholder: ({ instructions, children }: { instructions?: string; children?: React.ReactNode }) => (
        <div data-testid="placeholder">
            {instructions}
            {children}
        </div>
    ),
    PanelBody: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    SelectControl: () => null,
    Spinner: () => null,
    __experimentalToggleGroupControl: ({
        label,
        value,
        onChange,
        children,
    }: {
        label: string;
        value: string;
        onChange: (value: string) => void;
        children?: React.ReactNode;
    }) => (
        <div role="radiogroup" aria-label={label} data-value={value}>
            <button type="button" onClick={() => onChange('stacked')}>
                stacked
            </button>
            <button type="button" onClick={() => onChange('inline')}>
                inline
            </button>
            {children}
        </div>
    ),
    __experimentalToggleGroupControlOption: () => null,
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
}));

(globalThis as { React?: unknown }).React = require('react');

import FormEdit from '../edit';

const FORM_DETAIL = {
    data: {
        id: 3,
        name: 'Newsletter',
        slug: 'newsletter',
        submit_button_text: 'Subscribe',
        fields: [
            {
                id: 11,
                name: 'email',
                label: 'Email',
                type: 'email',
                placeholder: null,
                help_text: null,
                is_required: true,
                default_value: null,
            },
        ],
    },
};

function jsonResponse(body: unknown): Response {
    return { ok: true, status: 200, json: async () => body } as Response;
}

beforeEach(() => {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) =>
            String(url).includes('/render')
                ? jsonResponse(FORM_DETAIL)
                : jsonResponse({ data: [{ id: 3, name: 'Newsletter', slug: 'newsletter', is_active: true }] }),
        ),
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('FormEdit layout', () => {
    it('keeps the stacked column preview by default', async () => {
        render(<FormEdit attributes={{ formId: 3 }} setAttributes={() => undefined} />);

        const button = await screen.findByText('Subscribe');
        const row = button.parentElement as HTMLElement;

        expect(row.style.flexDirection).toBe('column');
        expect(row.getAttribute('data-layout')).toBeNull();
        expect(button.style.alignSelf).toBe('flex-start');
        expect(button.style.marginTop).toBe('0.5rem');
        expect(screen.getByRole('radiogroup', { name: 'Layout' }).getAttribute('data-value')).toBe('stacked');
    });

    it('puts the fields and button on one wrapping row when inline', async () => {
        render(<FormEdit attributes={{ formId: 3, layout: 'inline' }} setAttributes={() => undefined} />);

        const button = await screen.findByText('Subscribe');
        const row = button.parentElement as HTMLElement;
        const field = screen.getByLabelText(/Email/).parentElement as HTMLElement;

        expect(row.getAttribute('data-layout')).toBe('inline');
        expect(row.style.flexDirection).toBe('row');
        expect(row.style.flexWrap).toBe('wrap');
        expect(field.style.flex).toBe('1 1 12rem');
        expect(field.style.minWidth).toBe('min(12rem, 100%)');
        expect(button.style.alignSelf).toBe('flex-end');
        expect(button.style.flex).toBe('0 0 auto');
    });

    it('writes the chosen layout through setAttributes', async () => {
        const setAttributes = vi.fn();
        render(<FormEdit attributes={{ formId: 3 }} setAttributes={setAttributes} />);

        await waitFor(() => expect(screen.getByText('Subscribe')).toBeTruthy());
        fireEvent.click(screen.getByText('inline'));

        expect(setAttributes).toHaveBeenCalledWith({ layout: 'inline' });
    });
});
