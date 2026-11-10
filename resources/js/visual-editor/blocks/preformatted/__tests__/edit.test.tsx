/**
 * Tests for the `artisanpack/preformatted` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import type { Ref } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/block-editor', async () => {
    const { forwardRef } = await import('react');

    return {
        useBlockProps: Object.assign(
            (props?: Record<string, unknown>) => ({ ...props }),
            { save: (props?: Record<string, unknown>) => ({ ...props }) }
        ),
        RichText: forwardRef(function RichText(props: {
            value?: string;
            onChange?: (v: string) => void;
            placeholder?: string;
        }, ref: Ref<HTMLDivElement>) {
            return (
                <div
                    ref={ref}
                    role="textbox"
                    aria-label="preformatted-rich-text"
                    data-placeholder={props.placeholder}
                    onClick={() => props.onChange?.('multi\nline')}
                    dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                />
            );
        }),
    };
});

const exitRef = vi.hoisted(() => vi.fn());
const useExitOnDoubleEnterSpy = vi.hoisted(() => vi.fn(() => exitRef));

vi.mock('../../_shared/use-exit-on-double-enter', () => ({
    useExitOnDoubleEnter: useExitOnDoubleEnterSpy,
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

    it('exits on a double Enter through the shared hook (#1101)', () => {
        const setAttributes = vi.fn();
        const insertBlocksAfter = vi.fn();
        render(
            <PreformattedEdit
                attributes={{ content: 'foo' }}
                setAttributes={setAttributes}
                clientId="block-1"
                insertBlocksAfter={insertBlocksAfter}
            />
        );

        expect(useExitOnDoubleEnterSpy).toHaveBeenLastCalledWith({
            clientId: 'block-1',
            setAttributes,
            insertBlocksAfter,
        });
        expect(exitRef).toHaveBeenCalledWith(
            screen.getByRole('textbox', { name: 'preformatted-rich-text' })
        );
    });
});
