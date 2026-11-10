/**
 * Tests for the `artisanpack/quote` edit component.
 */

import { beforeEach, describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

const blockCounts = vi.hoisted(() => new Map<string, number>());

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: (
        mapSelect: (
            select: () => { getBlockCount: (rootClientId: string) => number }
        ) => unknown
    ) =>
        mapSelect(() => ({
            getBlockCount: (rootClientId: string) =>
                blockCounts.get(rootClientId) ?? 0,
        })),
}));

vi.mock('@wordpress/block-editor', () => ({
    store: 'core/block-editor',
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
    RichText: Object.assign(
        function RichText(props: {
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
        {
            isEmpty: (value?: { length: number } | string) =>
                !value || value.length === 0,
        }
    ),
}));

import QuoteEdit from '../edit';

describe('QuoteEdit', () => {
    beforeEach(() => {
        blockCounts.clear();
        blockCounts.set('abc', 1);
    });

    it('holds the citation back until the template paragraph exists (#1105)', () => {
        blockCounts.set('abc', 0);
        render(
            <QuoteEdit
                attributes={{}}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(
            screen.queryByRole('textbox', { name: 'citation-rich-text' })
        ).toBeNull();
        expect(screen.getByTestId('inner-blocks')).toBeTruthy();
    });

    it('treats an empty rich-text citation as empty', () => {
        blockCounts.set('abc', 0);
        render(
            <QuoteEdit
                attributes={{ citation: { length: 0 } as unknown as string }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(
            screen.queryByRole('textbox', { name: 'citation-rich-text' })
        ).toBeNull();
    });

    it('keeps a non-empty citation visible without inner blocks', () => {
        blockCounts.set('abc', 0);
        render(
            <QuoteEdit
                attributes={{ citation: 'Ada Lovelace' }}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(
            screen.getByRole('textbox', { name: 'citation-rich-text' })
                .innerHTML
        ).toBe('Ada Lovelace');
    });

    it('shows the empty citation once the quote has inner blocks', () => {
        render(
            <QuoteEdit
                attributes={{}}
                setAttributes={vi.fn()}
                clientId="abc"
            />
        );
        expect(
            screen.getByRole('textbox', { name: 'citation-rich-text' })
        ).toBeTruthy();
    });

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
