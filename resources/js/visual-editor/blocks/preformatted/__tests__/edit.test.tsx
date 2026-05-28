/**
 * Tests for the `artisanpack/preformatted` edit component.
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
        onChange?: (v: string) => void;
        placeholder?: string;
    }) {
        return (
            <div
                role="textbox"
                aria-label="preformatted-rich-text"
                data-placeholder={props.placeholder}
                onClick={() => props.onChange?.('multi\nline')}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

import PreformattedEdit from '../edit';

describe('PreformattedEdit', () => {
    it('renders a pre RichText with the right placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <PreformattedEdit
                attributes={{ content: '' }}
                setAttributes={setAttributes}
            />
        );
        const textbox = screen.getByRole('textbox', {
            name: 'preformatted-rich-text',
        });
        expect(textbox.getAttribute('data-placeholder')).toBe(
            'Write preformatted text…'
        );
    });

    it('forwards changes to setAttributes', () => {
        const setAttributes = vi.fn();
        render(
            <PreformattedEdit
                attributes={{ content: '' }}
                setAttributes={setAttributes}
            />
        );
        fireEvent.click(
            screen.getByRole('textbox', { name: 'preformatted-rich-text' })
        );
        expect(setAttributes).toHaveBeenCalledWith({ content: 'multi\nline' });
    });
});
