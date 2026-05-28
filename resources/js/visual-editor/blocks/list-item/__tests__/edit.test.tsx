/**
 * Tests for the `artisanpack/list-item` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({
        ...props,
        children: <div data-testid="inner-blocks" />,
    }),
    RichText: function RichText(props: {
        value?: string;
        onChange?: (v: string) => void;
        placeholder?: string;
    }) {
        return (
            <div
                role="textbox"
                aria-label="list-item-rich-text"
                data-placeholder={props.placeholder}
                onClick={() => props.onChange?.('changed')}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

import ListItemEdit from '../edit';

describe('ListItemEdit', () => {
    it('renders a RichText with the default List placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <ListItemEdit
                attributes={{ content: '' }}
                setAttributes={setAttributes}
            />
        );
        const textbox = screen.getByRole('textbox', { name: 'list-item-rich-text' });
        expect(textbox.getAttribute('data-placeholder')).toBe('List');
    });

    it('forwards onChange to setAttributes', () => {
        const setAttributes = vi.fn();
        render(
            <ListItemEdit
                attributes={{ content: '' }}
                setAttributes={setAttributes}
            />
        );
        fireEvent.click(
            screen.getByRole('textbox', { name: 'list-item-rich-text' })
        );
        expect(setAttributes).toHaveBeenCalledWith({ content: 'changed' });
    });
});
