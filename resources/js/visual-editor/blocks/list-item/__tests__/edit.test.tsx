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
        onMerge?: (forward?: boolean) => void;
        placeholder?: string;
    }) {
        return (
            <div
                role="textbox"
                aria-label="list-item-rich-text"
                data-placeholder={props.placeholder}
                onClick={() => props.onChange?.('changed')}
                onKeyDown={() => props.onMerge?.(false)}
                dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
            />
        );
    },
}));

const useMergeSpy = vi.hoisted(() => vi.fn());

vi.mock('../hooks/use-merge', () => ({ default: useMergeSpy }));

import ListItemEdit from '../edit';

describe('ListItemEdit', () => {
    it('routes RichText merges through useMerge', () => {
        const onMerge = vi.fn();
        const mergeBlocks = vi.fn();
        useMergeSpy.mockReturnValueOnce(onMerge);

        render(
            <ListItemEdit
                attributes={{ content: 'One' }}
                setAttributes={vi.fn()}
                clientId="item-1"
                mergeBlocks={mergeBlocks}
            />
        );

        expect(useMergeSpy).toHaveBeenCalledWith('item-1', mergeBlocks);

        fireEvent.keyDown(
            screen.getByRole('textbox', { name: 'list-item-rich-text' })
        );

        expect(onMerge).toHaveBeenCalledWith(false);
    });

    it('renders a RichText with the default List placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <ListItemEdit
                attributes={{ content: '' }}
                setAttributes={setAttributes}
                clientId="item-1"
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
                clientId="item-1"
            />
        );
        fireEvent.click(
            screen.getByRole('textbox', { name: 'list-item-rich-text' })
        );
        expect(setAttributes).toHaveBeenCalledWith({ content: 'changed' });
    });
});
