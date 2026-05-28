/**
 * Tests for the `artisanpack/code` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: vi.fn(),
    getDefaultBlockName: () => 'core/paragraph',
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: function RichText(props: {
        value?: string;
        onChange?: (value: string) => void;
        placeholder?: string;
    }) {
        return (
            <div
                role="textbox"
                aria-label="code-rich-text"
                data-placeholder={props.placeholder}
                onClick={() => props.onChange?.('console.log(1)')}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

import CodeEdit from '../edit';

describe('CodeEdit', () => {
    it('renders a code RichText with the write-code placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <CodeEdit attributes={{ content: '' }} setAttributes={setAttributes} />
        );
        const textbox = screen.getByRole('textbox', { name: 'code-rich-text' });
        expect(textbox.getAttribute('data-placeholder')).toBe('Write code…');
    });

    it('calls setAttributes with code content on change', () => {
        const setAttributes = vi.fn();
        render(
            <CodeEdit attributes={{ content: '' }} setAttributes={setAttributes} />
        );
        fireEvent.click(screen.getByRole('textbox', { name: 'code-rich-text' }));
        expect(setAttributes).toHaveBeenCalledWith({ content: 'console.log(1)' });
    });
});
