/**
 * Tests for the `artisanpack/post-date` edit component's Date format control.
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (format: string, ...args: unknown[]) => format.replace('%s', String(args[0])),
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    SelectControl: ({ value, onChange }: { value: string; onChange: (value: string) => void }) => (
        <input data-testid="format-select" value={value} onChange={(event) => onChange(event.target.value)} />
    ),
    TextControl: ({ value }: { value: string }) => <input data-testid="custom-format" value={value} readOnly />,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('../../_shared/entity-placeholder-edit', () => ({
    createEntityPlaceholderEdit: () => () => null,
}));

(globalThis as { React?: unknown }).React = require('react');

import PostDateEdit from '../edit';

describe('PostDateEdit date format control', () => {
    it('keeps Custom selected after choosing it over a preset format', () => {
        const setAttributes = vi.fn();
        const { getByTestId, rerender } = render(<PostDateEdit attributes={{ format: 'M j' }} setAttributes={setAttributes} />);

        fireEvent.change(getByTestId('format-select'), { target: { value: '__custom' } });
        rerender(<PostDateEdit attributes={{ format: 'M j' }} setAttributes={setAttributes} />);

        expect((getByTestId('format-select') as HTMLInputElement).value).toBe('__custom');
        expect(getByTestId('custom-format')).toBeTruthy();
    });

    it('follows a format restored from outside the control (undo / redo)', () => {
        const setAttributes = vi.fn();
        const { getByTestId, queryByTestId, rerender } = render(<PostDateEdit attributes={{ format: 'M j' }} setAttributes={setAttributes} />);

        // Undo restores a custom format: the custom field appears.
        rerender(<PostDateEdit attributes={{ format: 'D, j M' }} setAttributes={setAttributes} />);
        expect((getByTestId('format-select') as HTMLInputElement).value).toBe('__custom');
        expect(getByTestId('custom-format')).toBeTruthy();

        // Choose Custom, then redo restores a preset: Custom is dropped.
        fireEvent.change(getByTestId('format-select'), { target: { value: '__custom' } });
        rerender(<PostDateEdit attributes={{ format: 'Y-m-d' }} setAttributes={setAttributes} />);
        expect((getByTestId('format-select') as HTMLInputElement).value).toBe('Y-m-d');
        expect(queryByTestId('custom-format')).toBeNull();
    });
});
