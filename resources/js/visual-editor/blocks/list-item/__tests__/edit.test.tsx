/**
 * Tests for the `artisanpack/list-item` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import type { ReactNode, Ref } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

const selectState = vi.hoisted(() => ({
    index: 0,
    grandparentName: 'artisanpack/list' as string | null,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: (mapSelect: (select: () => unknown) => unknown) =>
        mapSelect(() => ({
            getBlockIndex: () => selectState.index,
            getBlockRootClientId: (id: string | null) => (id ? `${id}-parent` : null),
            getBlockName: () => selectState.grandparentName,
        })),
}));

vi.mock('@wordpress/components', () => ({
    ToolbarButton: (props: {
        title: string;
        disabled?: boolean;
        onClick: () => void;
    }) => (
        <button type="button" disabled={props.disabled} onClick={props.onClick}>
            {props.title}
        </button>
    ),
}));

vi.mock('@wordpress/block-editor', async () => {
    const { forwardRef } = await import('react');

    return {
        useBlockProps: Object.assign(
            (props?: Record<string, unknown>) => ({ ...props }),
            { save: (props?: Record<string, unknown>) => ({ ...props }) }
        ),
        useInnerBlocksProps: (props?: Record<string, unknown>) => ({
            ...props,
            children: <div data-testid="inner-blocks" />,
        }),
        store: 'core/block-editor',
        BlockControls: ({ children }: { children: ReactNode }) => <div>{children}</div>,
        RichText: forwardRef(function RichText(props: {
            value?: string;
            onChange?: (v: string) => void;
            onMerge?: (forward?: boolean) => void;
            placeholder?: string;
        }, ref: Ref<HTMLDivElement>) {
            return (
                <div
                    ref={ref}
                    role="textbox"
                    aria-label="list-item-rich-text"
                    data-placeholder={props.placeholder}
                    onClick={() => props.onChange?.('changed')}
                    onKeyDown={() => props.onMerge?.(false)}
                    dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                />
            );
        }),
    };
});

const useMergeSpy = vi.hoisted(() => vi.fn());
const enterRef = vi.hoisted(() => vi.fn());
const spaceRef = vi.hoisted(() => vi.fn());
const useEnterSpy = vi.hoisted(() => vi.fn(() => enterRef));
const useSpaceSpy = vi.hoisted(() => vi.fn(() => spaceRef));
const indentSpy = vi.hoisted(() => vi.fn());
const outdentSpy = vi.hoisted(() => vi.fn());

vi.mock('../hooks/use-merge', () => ({ default: useMergeSpy }));
vi.mock('../hooks/use-enter', () => ({ default: useEnterSpy }));
vi.mock('../hooks/use-space', () => ({ default: useSpaceSpy }));
vi.mock('../hooks/use-indent-list-item', () => ({ default: () => indentSpy }));
vi.mock('../hooks/use-outdent-list-item', () => ({ default: () => outdentSpy }));

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

    it('attaches the Enter and Space/Tab hooks to the RichText (#1101)', () => {
        render(
            <ListItemEdit
                attributes={{ content: '' }}
                setAttributes={vi.fn()}
                clientId="item-1"
            />
        );

        const textbox = screen.getByRole('textbox', { name: 'list-item-rich-text' });

        expect(useEnterSpy).toHaveBeenLastCalledWith({ content: '', clientId: 'item-1' });
        expect(useSpaceSpy).toHaveBeenLastCalledWith('item-1');
        expect(enterRef).toHaveBeenCalledWith(textbox);
        expect(spaceRef).toHaveBeenCalledWith(textbox);
    });

    it('disables Indent on the first item and Outdent on a top-level item', () => {
        selectState.index = 0;
        selectState.grandparentName = null;

        render(
            <ListItemEdit
                attributes={{ content: 'One' }}
                setAttributes={vi.fn()}
                clientId="item-1"
            />
        );

        expect(screen.getByRole('button', { name: 'Indent' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Outdent' })).toBeDisabled();
    });

    it('indents and outdents from the toolbar when allowed', () => {
        selectState.index = 1;
        selectState.grandparentName = 'artisanpack/list-item';

        render(
            <ListItemEdit
                attributes={{ content: 'Two' }}
                setAttributes={vi.fn()}
                clientId="item-2"
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Indent' }));
        fireEvent.click(screen.getByRole('button', { name: 'Outdent' }));

        expect(indentSpy).toHaveBeenCalled();
        expect(outdentSpy).toHaveBeenCalled();
    });
});
