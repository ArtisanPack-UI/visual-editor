/**
 * Tests for the `artisanpack/paragraph` edit component.
 *
 * `@wordpress/*` is mocked to the smallest viable surface so we can render
 * the component in jsdom and assert on the wrapper class, drop-cap toggle,
 * and content change wiring without spinning up a real block editor.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

const settingsState: Record<string, unknown> = {
    'typography.dropCap': true,
};

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
    isRTL: () => false,
}));

vi.mock('@wordpress/components', () => ({
    ToolbarButton: ({ title, onClick }: { title: string; onClick: () => void }) => (
        <button onClick={onClick}>{title}</button>
    ),
    ToggleControl: ({
        label,
        checked,
        onChange,
        disabled,
    }: {
        label: string;
        checked: boolean;
        onChange: (next: boolean) => void;
        disabled?: boolean;
    }) => (
        <label>
            {label}
            <input
                type="checkbox"
                aria-label={label}
                checked={checked}
                onChange={() => onChange(!checked)}
                disabled={disabled}
            />
        </label>
    ),
    __experimentalToolsPanelItem: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="tools-panel-item">{children}</div>
    ),
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({
            ...props,
            'data-testid': 'block-props',
        }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useSettings: (path: string) => [settingsState[path]],
    useBlockEditingMode: () => 'default',
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
                    aria-label="paragraph-rich-text"
                    className={props.className}
                    data-tag={props.tagName}
                    data-placeholder={props.placeholder}
                    onClick={() => props.onChange?.('changed')}
                    dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                />
            );
        },
        {
            isEmpty: (value?: string) => !value || value.length === 0,
            Content: ({ value }: { value?: string }) => <>{value}</>,
        }
    ),
    store: { name: 'core/block-editor' },
}));

vi.mock('@wordpress/blocks', () => ({
    getBlockSupport: () => false,
    hasBlockSupport: () => false,
    createBlock: vi.fn(),
    getDefaultBlockName: () => 'artisanpack/paragraph',
}));

vi.mock('@wordpress/icons', () => ({
    formatLTR: 'formatLTR-icon',
}));

vi.mock('@wordpress/data', () => ({
    useSelect: () => ({
        getBlockRootClientId: () => null,
        getBlockIndex: () => 0,
        getBlockOrder: () => [],
        getBlockName: () => null,
        getBlock: () => ({ innerBlocks: [] }),
        getNextBlockClientId: () => null,
        canInsertBlockType: () => false,
    }),
    useDispatch: () => ({
        moveBlocksToPosition: vi.fn(),
        replaceInnerBlocks: vi.fn(),
        duplicateBlocks: vi.fn(),
        insertBlock: vi.fn(),
        __unstableMarkNextChangeAsNotPersistent: vi.fn(),
    }),
    useRegistry: () => ({ batch: (fn: () => void) => fn() }),
}));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return {
        useRef: React.useRef,
        useEffect: React.useEffect,
    };
});

vi.mock('@wordpress/compose', () => ({
    useRefEffect: () => null,
    useEvent: (fn: (...args: unknown[]) => unknown) => fn,
}));

vi.mock('@wordpress/keycodes', () => ({
    ENTER: 13,
}));

vi.mock('@wordpress/deprecated', () => ({
    default: () => {},
}));

import ParagraphEdit from '../edit';

const baseAttrs = {
    content: 'Hello world',
    dropCap: false,
};

describe('ParagraphEdit', () => {
    it('renders the wp-block-paragraph wrapper class on the RichText', () => {
        const setAttributes = vi.fn();
        render(
            <ParagraphEdit
                attributes={baseAttrs}
                setAttributes={setAttributes}
                clientId="abc-123"
                isSelected={false}
                name="artisanpack/paragraph"
            />
        );

        const textbox = screen.getByRole('textbox', { name: 'paragraph-rich-text' });
        expect(textbox.className).toContain('wp-block-paragraph');
    });

    it('calls setAttributes when RichText fires onChange', () => {
        const setAttributes = vi.fn();
        render(
            <ParagraphEdit
                attributes={baseAttrs}
                setAttributes={setAttributes}
                clientId="abc-123"
                isSelected={false}
                name="artisanpack/paragraph"
            />
        );

        fireEvent.click(screen.getByRole('textbox', { name: 'paragraph-rich-text' }));
        expect(setAttributes).toHaveBeenCalledWith({ content: 'changed' });
    });

    it('exposes the drop-cap toggle when selected and dropCap setting is enabled', () => {
        const setAttributes = vi.fn();
        render(
            <ParagraphEdit
                attributes={baseAttrs}
                setAttributes={setAttributes}
                clientId="abc-123"
                isSelected
                name="artisanpack/paragraph"
            />
        );

        const toggle = screen.getByLabelText('Drop cap');
        expect(toggle).not.toBeNull();
        fireEvent.click(toggle);
        expect(setAttributes).toHaveBeenCalledWith({ dropCap: true });
    });

    it('uses the placeholder attribute when present', () => {
        const setAttributes = vi.fn();
        render(
            <ParagraphEdit
                attributes={{ ...baseAttrs, content: '', placeholder: 'Custom hint' }}
                setAttributes={setAttributes}
                clientId="abc-123"
                isSelected={false}
                name="artisanpack/paragraph"
            />
        );

        const textbox = screen.getByRole('textbox', { name: 'paragraph-rich-text' });
        expect(textbox.getAttribute('data-placeholder')).toBe('Custom hint');
    });
});
