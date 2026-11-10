/**
 * Tests for the `artisanpack/code` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import type { ReactNode, Ref } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    SelectControl: (props: {
        label: string;
        value: string;
        options: Array<{ label: string; value: string }>;
        onChange: (value: string) => void;
    }) => (
        <select
            aria-label={props.label}
            value={props.value}
            onChange={(event) => props.onChange(event.target.value)}
        >
            {props.options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    ),
}));

vi.mock('@wordpress/block-editor', async () => {
    const { forwardRef } = await import('react');

    return {
        useBlockProps: Object.assign(
            (props?: Record<string, unknown>) => ({ ...props }),
            { save: (props?: Record<string, unknown>) => ({ ...props }) }
        ),
        InspectorControls: ({ children }: { children: ReactNode }) => <div>{children}</div>,
        RichText: forwardRef(function RichText(props: {
            value?: string;
            onChange?: (value: string) => void;
            placeholder?: string;
        }, ref: Ref<HTMLDivElement>) {
            return (
                <div
                    ref={ref}
                    role="textbox"
                    aria-label="code-rich-text"
                    data-placeholder={props.placeholder}
                    onClick={() => props.onChange?.('console.log(1)')}
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

import CodeEdit from '../edit';
import metadata from '../block.json';

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

    it('offers every language the block accepts, plus None', () => {
        render(<CodeEdit attributes={{ content: '' }} setAttributes={vi.fn()} />);
        const select = screen.getByRole('combobox', { name: 'Language' }) as HTMLSelectElement;
        const values = Array.from(select.options).map((option) => option.value);

        expect(values[0]).toBe('');
        expect(values.slice(1).sort()).toEqual([...metadata.attributes.language.enum].sort());
    });

    it('shows None for a block with no language', () => {
        render(<CodeEdit attributes={{ content: '' }} setAttributes={vi.fn()} />);
        const select = screen.getByRole('combobox', { name: 'Language' }) as HTMLSelectElement;

        expect(select.value).toBe('');
    });

    it('saves the selected language', () => {
        const setAttributes = vi.fn();
        render(<CodeEdit attributes={{ content: '' }} setAttributes={setAttributes} />);

        fireEvent.change(screen.getByRole('combobox', { name: 'Language' }), { target: { value: 'php' } });

        expect(setAttributes).toHaveBeenCalledWith({ language: 'php' });
    });

    it('clears the attribute on None, so the block saves as it did before', () => {
        const setAttributes = vi.fn();
        render(<CodeEdit attributes={{ content: '', language: 'php' }} setAttributes={setAttributes} />);

        fireEvent.change(screen.getByRole('combobox', { name: 'Language' }), { target: { value: '' } });

        expect(setAttributes).toHaveBeenCalledWith({ language: undefined });
    });

    it('exits on a double Enter through the shared hook (#1101)', () => {
        const setAttributes = vi.fn();
        const insertBlocksAfter = vi.fn();
        render(
            <CodeEdit
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
            screen.getByRole('textbox', { name: 'code-rich-text' })
        );
    });
});
