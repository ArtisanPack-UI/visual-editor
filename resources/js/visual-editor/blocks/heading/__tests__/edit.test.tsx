/**
 * Tests for the `artisanpack/heading` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return {
        useEffect: React.useEffect,
        Platform: { isNative: false },
    };
});

vi.mock('@wordpress/data', () => ({
    useSelect: () => ({ canGenerateAnchors: false }),
    useDispatch: () => ({
        __unstableMarkNextChangeAsNotPersistent: vi.fn(),
    }),
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({
            ...props,
            'data-testid': 'block-props',
        }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(
        function RichText(props: {
            value?: string;
            className?: string;
            onChange?: (value: string) => void;
            placeholder?: string;
            tagName?: string;
        }) {
            return (
                <div
                    role="textbox"
                    aria-label="heading-rich-text"
                    className={props.className}
                    data-tag={props.tagName}
                    data-placeholder={props.placeholder}
                    onClick={() => props.onChange?.('changed')}
                    dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                />
            );
        },
        { isEmpty: (v?: string) => !v || v.length === 0 }
    ),
    store: { name: 'core/block-editor' },
}));

import HeadingEdit from '../edit';

describe('HeadingEdit', () => {
    it('renders a RichText with the correct heading tagName', () => {
        const setAttributes = vi.fn();
        render(
            <HeadingEdit
                attributes={{ content: 'hi', level: 3 }}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        const textbox = screen.getByRole('textbox', { name: 'heading-rich-text' });
        expect(textbox.getAttribute('data-tag')).toBe('h3');
    });

    it('uses the default placeholder when none is provided', () => {
        const setAttributes = vi.fn();
        render(
            <HeadingEdit
                attributes={{ content: '', level: 2 }}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        const textbox = screen.getByRole('textbox', { name: 'heading-rich-text' });
        expect(textbox.getAttribute('data-placeholder')).toBe('Heading');
    });

    it('calls setAttributes when RichText fires onChange', () => {
        const setAttributes = vi.fn();
        render(
            <HeadingEdit
                attributes={{ content: 'hi', level: 2 }}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        fireEvent.click(screen.getByRole('textbox', { name: 'heading-rich-text' }));
        expect(setAttributes).toHaveBeenCalledWith({ content: 'changed' });
    });

    it('clamps an out-of-range level (0) up to h1', () => {
        const setAttributes = vi.fn();
        render(
            <HeadingEdit
                attributes={{ content: 'hi', level: 0 }}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        const textbox = screen.getByRole('textbox', { name: 'heading-rich-text' });
        expect(textbox.getAttribute('data-tag')).toBe('h1');
    });

    it('clamps an out-of-range level (7) down to h6', () => {
        const setAttributes = vi.fn();
        render(
            <HeadingEdit
                attributes={{ content: 'hi', level: 7 }}
                setAttributes={setAttributes}
                clientId="abc"
            />
        );
        const textbox = screen.getByRole('textbox', { name: 'heading-rich-text' });
        expect(textbox.getAttribute('data-tag')).toBe('h6');
    });
});
