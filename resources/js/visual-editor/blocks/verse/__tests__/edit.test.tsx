/**
 * Tests for the `artisanpack/verse` edit component.
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
                    aria-label="verse-rich-text"
                    data-placeholder={props.placeholder}
                    onClick={() => props.onChange?.('Roses are red')}
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

    it('exits on a double Enter through the shared hook (#1101)', () => {
        const setAttributes = vi.fn();
        const insertBlocksAfter = vi.fn();
        render(
            <VerseEdit
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
            screen.getByRole('textbox', { name: 'verse-rich-text' })
        );
    });
});
