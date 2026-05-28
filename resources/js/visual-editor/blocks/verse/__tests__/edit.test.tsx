/**
 * Tests for the `artisanpack/verse` edit component.
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
                aria-label="verse-rich-text"
                data-placeholder={props.placeholder}
                onClick={() => props.onChange?.('Roses are red')}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

import VerseEdit from '../edit';

describe('VerseEdit', () => {
    it('renders a pre RichText with the poetry placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <VerseEdit attributes={{ content: '' }} setAttributes={setAttributes} />
        );
        const textbox = screen.getByRole('textbox', { name: 'verse-rich-text' });
        expect(textbox.getAttribute('data-placeholder')).toBe('Write poetry…');
    });

    it('forwards onChange to setAttributes', () => {
        const setAttributes = vi.fn();
        render(
            <VerseEdit attributes={{ content: '' }} setAttributes={setAttributes} />
        );
        fireEvent.click(screen.getByRole('textbox', { name: 'verse-rich-text' }));
        expect(setAttributes).toHaveBeenCalledWith({ content: 'Roses are red' });
    });
});
