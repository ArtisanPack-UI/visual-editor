/**
 * Tests for the `artisanpack/list` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    SelectControl: ({
        value,
        onChange,
    }: { value?: string; onChange: (v: string) => void }) => (
        <select
            aria-label="list-style"
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="decimal">decimal</option>
            <option value="upper-alpha">upper-alpha</option>
        </select>
    ),
    TextControl: ({
        label,
        value,
        onChange,
    }: { label: string; value?: string; onChange: (v: string) => void }) => (
        <input
            aria-label={label}
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
        />
    ),
    ToggleControl: ({
        label,
        checked,
        onChange,
    }: { label: string; checked: boolean; onChange: (v: boolean) => void }) => (
        <label>
            {label}
            <input
                type="checkbox"
                aria-label={label}
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
        </label>
    ),
    ToolbarButton: ({
        title,
        isActive,
        onClick,
    }: { title: string; isActive?: boolean; onClick: () => void }) => (
        <button
            aria-pressed={isActive}
            aria-label={title}
            onClick={onClick}
        >
            {title}
        </button>
    ),
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({
        ...props,
        children: <div data-testid="inner-blocks" />,
    }),
    BlockControls: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
}));

import ListEdit from '../edit';

describe('ListEdit', () => {
    it('renders the UL toolbar button as active when ordered is false', () => {
        const setAttributes = vi.fn();
        render(
            <ListEdit attributes={{ ordered: false }} setAttributes={setAttributes} />
        );
        expect(screen.getByLabelText('Unordered').getAttribute('aria-pressed')).toBe(
            'true'
        );
    });

    it('switches to ordered when the Ordered toolbar button is clicked', () => {
        const setAttributes = vi.fn();
        render(
            <ListEdit attributes={{ ordered: false }} setAttributes={setAttributes} />
        );
        fireEvent.click(screen.getByLabelText('Ordered'));
        expect(setAttributes).toHaveBeenCalledWith({ ordered: true });
    });

    it('shows ordered-list settings when ordered=true', () => {
        const setAttributes = vi.fn();
        render(
            <ListEdit attributes={{ ordered: true }} setAttributes={setAttributes} />
        );
        expect(screen.getByLabelText('list-style')).toBeTruthy();
    });
});
