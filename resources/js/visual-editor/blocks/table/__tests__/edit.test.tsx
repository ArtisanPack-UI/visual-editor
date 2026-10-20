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
    return { useState: React.useState, useEffect: React.useEffect };
});

vi.mock('@wordpress/icons', () => ({
    table: 'table',
    tableColumnAfter: 'tableColumnAfter',
    tableColumnBefore: 'tableColumnBefore',
    tableColumnDelete: 'tableColumnDelete',
    tableRowAfter: 'tableRowAfter',
    tableRowBefore: 'tableRowBefore',
    tableRowDelete: 'tableRowDelete',
}));

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
    ToolbarDropdownMenu: ({
        label,
        controls,
    }: {
        label: string;
        controls: Array<{
            title: string;
            isDisabled?: boolean;
            onClick: () => void;
        }>;
    }) => (
        <div role="menu" aria-label={label}>
            {controls.map((control) => (
                <button
                    key={control.title}
                    type="button"
                    disabled={control.isDisabled}
                    onClick={control.onClick}
                >
                    {control.title}
                </button>
            ))}
        </div>
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
        onFocus?: () => void;
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
                onFocus={props.onFocus}
                tabIndex={0}
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

    describe('Edit table toolbar', () => {
        type Attrs = React.ComponentProps<typeof TableEdit>['attributes'];

        const cell = (content: string, tag = 'td') => ({ content, tag });

        const twoByTwo = (): Attrs => ({
            hasFixedLayout: true,
            head: [{ cells: [cell('H1', 'th'), cell('H2', 'th')] }],
            body: [
                { cells: [cell('A1'), cell('A2')] },
                { cells: [cell('B1'), cell('B2')] },
            ],
            foot: [],
        });

        const CONTROL_LABELS = [
            'Insert row before',
            'Insert row after',
            'Delete row',
            'Insert column before',
            'Insert column after',
            'Delete column',
        ];

        const renderTable = (attributes: Attrs, isSelected = true) => {
            const setAttributes = vi.fn();
            const utils = render(
                <TableEdit
                    attributes={attributes}
                    setAttributes={setAttributes}
                    isSelected={isSelected}
                />
            );
            return { ...utils, setAttributes };
        };

        const focusCell = (index: number) => {
            fireEvent.focus(screen.getAllByRole('textbox', { name: 'Cell' })[index]);
        };

        const control = (label: string) =>
            screen.getByRole('button', { name: label }) as HTMLButtonElement;

        it('renders the Edit table menu with every control disabled until a cell is focused', () => {
            renderTable(twoByTwo());
            expect(screen.getByRole('menu', { name: 'Edit table' })).toBeTruthy();
            CONTROL_LABELS.forEach((label) => expect(control(label).disabled).toBe(true));

            focusCell(0);
            CONTROL_LABELS.forEach((label) => expect(control(label).disabled).toBe(false));
        });

        it('does not render the menu for an empty table', () => {
            renderTable({ hasFixedLayout: true, head: [], body: [], foot: [] });
            expect(screen.queryByRole('menu', { name: 'Edit table' })).toBeNull();
        });

        it('inserts a row before and after the focused body row', () => {
            const { setAttributes } = renderTable(twoByTwo());
            // Cells render head → body; index 2 is body row 0, column 0.
            focusCell(2);

            fireEvent.click(control('Insert row before'));
            const before = setAttributes.mock.calls[0][0];
            expect(Object.keys(before)).toEqual(['body']);
            expect(before.body.map((r: { cells: { content: string }[] }) => r.cells[0].content)).toEqual(['', 'A1', 'B1']);

            focusCell(2);
            fireEvent.click(control('Insert row after'));
            const after = setAttributes.mock.calls[1][0];
            expect(after.body.map((r: { cells: { content: string }[] }) => r.cells[0].content)).toEqual(['A1', '', 'B1']);
        });

        it('deletes the focused row and disables the controls again', () => {
            const { setAttributes } = renderTable(twoByTwo());
            focusCell(4); // body row 1, column 0

            fireEvent.click(control('Delete row'));
            expect(setAttributes).toHaveBeenCalledWith({
                body: [{ cells: [cell('A1'), cell('A2')] }],
            });
            expect(control('Delete row').disabled).toBe(true);
        });

        it('inserts columns across head and body around the focused column', () => {
            const { setAttributes } = renderTable(twoByTwo());
            focusCell(3); // body row 0, column 1

            fireEvent.click(control('Insert column after'));
            const next = setAttributes.mock.calls[0][0];
            expect(next.head[0].cells.map((c: { content: string }) => c.content)).toEqual(['H1', 'H2', '']);
            expect(next.head[0].cells[2].tag).toBe('th');
            expect(next.body[1].cells.map((c: { content: string }) => c.content)).toEqual(['B1', 'B2', '']);

            focusCell(3);
            fireEvent.click(control('Insert column before'));
            const before = setAttributes.mock.calls[1][0];
            expect(before.body[0].cells.map((c: { content: string }) => c.content)).toEqual(['A1', '', 'A2']);
        });

        it('deletes the focused column from every section', () => {
            const { setAttributes } = renderTable(twoByTwo());
            focusCell(0); // head row 0, column 0

            fireEvent.click(control('Delete column'));
            expect(setAttributes).toHaveBeenCalledWith({
                head: [{ cells: [cell('H2', 'th')] }],
                body: [{ cells: [cell('A2')] }, { cells: [cell('B2')] }],
            });
        });

        it('returns to the placeholder, in the same figure, after the last column is deleted', () => {
            const attributes: Attrs = {
                hasFixedLayout: true,
                head: [],
                body: [{ cells: [cell('A1')] }],
                foot: [],
            };
            const { setAttributes, container, rerender } = renderTable(attributes);
            const figure = container.querySelector('figure');
            focusCell(0);
            fireEvent.click(control('Delete column'));

            const next = { ...attributes, ...setAttributes.mock.calls[0][0] };
            expect(next.body).toEqual([]);

            rerender(
                <TableEdit attributes={next} setAttributes={setAttributes} isSelected />
            );
            expect(screen.getByTestId('placeholder')).toBeTruthy();
            expect(container.querySelector('figure')).toBe(figure);
        });

        it('clears the focused cell when the block is deselected', () => {
            const attributes = twoByTwo();
            const setAttributes = vi.fn();
            const { rerender } = render(
                <TableEdit attributes={attributes} setAttributes={setAttributes} isSelected />
            );
            focusCell(0);
            expect(control('Delete row').disabled).toBe(false);

            rerender(
                <TableEdit attributes={attributes} setAttributes={setAttributes} isSelected={false} />
            );
            expect(control('Delete row').disabled).toBe(true);
        });
    });
});
