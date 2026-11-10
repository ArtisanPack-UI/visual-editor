/**
 * Tests for the `artisanpack/pullquote` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: vi.fn((name: string) => ({ name })),
    getDefaultBlockName: () => 'core/paragraph',
}));

const insertion = vi.hoisted(() => ({ allowed: true }));

vi.mock('@wordpress/data', () => ({
    useSelect: (mapSelect: (select: () => unknown) => unknown) =>
        mapSelect(() => ({
            canInsertBlockType: () => insertion.allowed,
            getBlockRootClientId: () => null,
        })),
}));

vi.mock('@wordpress/block-editor', () => ({
    store: 'core/block-editor',
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    AlignmentControl: ({
        value,
        onChange,
    }: { value?: string; onChange: (v?: string) => void }) => (
        <button onClick={() => onChange('right')}>
            align:{value ?? 'none'}
        </button>
    ),
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
    RichText: Object.assign(
        function RichText(props: {
            value?: string;
            onChange?: (v: string) => void;
            placeholder?: string;
            identifier?: string;
            __unstableOnSplitAtEnd?: () => void;
        }) {
            return (
                <div
                    role="textbox"
                    aria-label={`pullquote-${props.identifier}`}
                    data-placeholder={props.placeholder}
                    data-exits={props.__unstableOnSplitAtEnd ? 'yes' : 'no'}
                    onClick={() => props.onChange?.(`new-${props.identifier}`)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            props.__unstableOnSplitAtEnd?.();
                        }
                    }}
                    dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                />
            );
        },
        { isEmpty: (v?: string) => !v || v.length === 0 }
    ),
}));

import PullquoteEdit from '../edit';

describe('PullquoteEdit', () => {
    it('renders the value RichText with the quote placeholder', () => {
        const setAttributes = vi.fn();
        render(
            <PullquoteEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected={false}
            />
        );
        const valueBox = screen.getByRole('textbox', { name: 'pullquote-value' });
        expect(valueBox.getAttribute('data-placeholder')).toBe('Add quote');
    });

    it('shows the citation RichText when selected (even with empty citation)', () => {
        const setAttributes = vi.fn();
        render(
            <PullquoteEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const citeBox = screen.getByRole('textbox', {
            name: 'pullquote-citation',
        });
        expect(citeBox).toBeTruthy();
    });

    it('forwards alignment changes to setAttributes', () => {
        const setAttributes = vi.fn();
        render(
            <PullquoteEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected={false}
            />
        );
        fireEvent.click(screen.getByText('align:none'));
        expect(setAttributes).toHaveBeenCalledWith({ textAlign: 'right' });
    });

    it.each(['value', 'citation'])(
        'exits to a default block on Enter at the end of the %s (#1101)',
        (identifier) => {
            const insertBlocksAfter = vi.fn();
            render(
                <PullquoteEdit
                    attributes={{ value: 'Quote', citation: 'Someone' }}
                    setAttributes={vi.fn()}
                    isSelected
                    insertBlocksAfter={insertBlocksAfter}
                />
            );

            fireEvent.keyDown(
                screen.getByRole('textbox', { name: `pullquote-${identifier}` }),
                { key: 'Enter' }
            );

            expect(insertBlocksAfter).toHaveBeenCalledWith({ name: 'core/paragraph' });
        }
    );

    it('does not take over Enter when no block can be inserted after it', () => {
        insertion.allowed = false;
        const insertBlocksAfter = vi.fn();
        render(
            <PullquoteEdit
                attributes={{ value: 'Quote', citation: 'Someone' }}
                setAttributes={vi.fn()}
                isSelected
                clientId="pullquote-1"
                insertBlocksAfter={insertBlocksAfter}
            />
        );

        fireEvent.keyDown(screen.getByRole('textbox', { name: 'pullquote-value' }), { key: 'Enter' });
        fireEvent.keyDown(screen.getByRole('textbox', { name: 'pullquote-citation' }), { key: 'Enter' });

        expect(insertBlocksAfter).not.toHaveBeenCalled();
        expect(screen.getByRole('textbox', { name: 'pullquote-value' }).dataset.exits).toBe('no');
        expect(screen.getByRole('textbox', { name: 'pullquote-citation' }).dataset.exits).toBe('no');
        insertion.allowed = true;
    });

    it('does not take over Enter without insertBlocksAfter (locked parent)', () => {
        render(
            <PullquoteEdit
                attributes={{ value: 'Quote', citation: 'Someone' }}
                setAttributes={vi.fn()}
                isSelected
                clientId="pullquote-1"
            />
        );

        expect(screen.getByRole('textbox', { name: 'pullquote-value' }).dataset.exits).toBe('no');
        expect(screen.getByRole('textbox', { name: 'pullquote-citation' }).dataset.exits).toBe('no');
    });
});
