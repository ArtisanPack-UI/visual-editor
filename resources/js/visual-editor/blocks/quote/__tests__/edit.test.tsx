/**
 * Tests for the `artisanpack/quote` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: (
        props?: Record<string, unknown>,
        _config?: Record<string, unknown>
    ) => ({ ...props, children: <div data-testid="inner-blocks" /> }),
    AlignmentControl: ({
        value,
        onChange,
    }: { value?: string; onChange: (v?: string) => void }) => (
        <button onClick={() => onChange(value === 'right' ? undefined : 'right')}>
            align:{value ?? 'none'}
        </button>
    ),
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
    RichText: function RichText(props: {
        value?: string;
        className?: string;
        onChange?: (value: string) => void;
        placeholder?: string;
    }) {
        return (
            <div
                role="textbox"
                aria-label="citation-rich-text"
                className={props.className}
                data-placeholder={props.placeholder}
                onClick={() => props.onChange?.('Author')}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

import QuoteEdit from '../edit';

describe('QuoteEdit', () => {
    it('renders a citation RichText with placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <QuoteEdit
                attributes={{}}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        const textbox = screen.getByRole('textbox', { name: 'citation-rich-text' });
        expect(textbox.getAttribute('data-placeholder')).toBe('Add citation');
    });

    it('calls setAttributes when citation RichText fires onChange', () => {
        const setAttributes = vi.fn();
        render(
            <QuoteEdit
                attributes={{}}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        fireEvent.click(screen.getByRole('textbox', { name: 'citation-rich-text' }));
        expect(setAttributes).toHaveBeenCalledWith({ citation: 'Author' });
    });

    it('alignment control updates textAlign', () => {
        const setAttributes = vi.fn();
        render(
            <QuoteEdit
                attributes={{}}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        fireEvent.click(screen.getByText('align:none'));
        expect(setAttributes).toHaveBeenCalledWith({ textAlign: 'right' });
    });
});
