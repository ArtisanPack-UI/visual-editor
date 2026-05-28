/**
 * Tests for the `artisanpack/table` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return { useState: React.useState };
});

vi.mock('@wordpress/components', () => ({
    Button: ({
        children,
        type,
        onClick,
    }: {
        children: React.ReactNode;
        type?: 'button' | 'submit';
        onClick?: () => void;
    }) => (
        <button type={type} onClick={onClick}>
            {children}
        </button>
    ),
    PanelBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    Placeholder: ({
        children,
        label,
    }: { children: React.ReactNode; label: string }) => (
        <div data-testid="placeholder" aria-label={label}>
            {children}
        </div>
    ),
    TextControl: ({
        label,
        value,
        onChange,
    }: { label: string; value: string; onChange: (v: string) => void }) => (
        <label>
            {label}
            <input
                aria-label={label}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
        </label>
    ),
    ToggleControl: ({
        label,
        checked,
        onChange,
    }: { label: string; checked: boolean; onChange: () => void }) => (
        <label>
            {label}
            <input
                type="checkbox"
                aria-label={label}
                checked={checked}
                onChange={onChange}
            />
        </label>
    ),
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    BlockControls: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    InspectorControls: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    RichText: function RichText(props: {
        value?: string;
        onChange?: (v: string) => void;
        placeholder?: string;
        tagName?: string;
        'aria-label'?: string;
    }) {
        return (
            <div
                role="textbox"
                aria-label={props['aria-label'] ?? 'rich-text'}
                data-tag={props.tagName}
                onClick={() => props.onChange?.('changed')}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

import TableEdit from '../edit';

describe('TableEdit', () => {
    it('renders the placeholder for an empty table', () => {
        const setAttributes = vi.fn();
        render(
            <TableEdit
                attributes={{ hasFixedLayout: true, head: [], body: [], foot: [] }}
                setAttributes={setAttributes}
            />
        );
        expect(screen.getByTestId('placeholder')).toBeTruthy();
    });

    it('creates a table when the Create button is clicked', () => {
        const setAttributes = vi.fn();
        render(
            <TableEdit
                attributes={{ hasFixedLayout: true, head: [], body: [], foot: [] }}
                setAttributes={setAttributes}
            />
        );
        fireEvent.click(screen.getByText('Create table'));
        expect(setAttributes).toHaveBeenCalled();
        const arg = setAttributes.mock.calls[0][0] as {
            body: Array<{ cells: unknown[] }>;
        };
        expect(Array.isArray(arg.body)).toBe(true);
        expect(arg.body.length).toBeGreaterThan(0);
    });

    it('renders a populated table with body cells', () => {
        const setAttributes = vi.fn();
        render(
            <TableEdit
                attributes={{
                    hasFixedLayout: true,
                    head: [],
                    body: [{ cells: [{ content: 'A', tag: 'td' }] }],
                    foot: [],
                }}
                setAttributes={setAttributes}
            />
        );
        const cell = screen.getByRole('textbox', { name: 'Cell' });
        expect(cell).toBeTruthy();
    });

    it('keeps a single figure element across the placeholder → populated transition', () => {
        const { container, rerender } = render(
            <TableEdit
                attributes={{ hasFixedLayout: true, head: [], body: [], foot: [] }}
                setAttributes={vi.fn()}
            />
        );
        const emptyFigure = container.querySelector('figure');
        expect(emptyFigure).not.toBeNull();

        rerender(
            <TableEdit
                attributes={{
                    hasFixedLayout: true,
                    head: [],
                    body: [{ cells: [{ content: '', tag: 'td' }] }],
                    foot: [],
                }}
                setAttributes={vi.fn()}
            />
        );
        const populatedFigure = container.querySelector('figure');
        expect(populatedFigure).toBe(emptyFigure);
    });
});
