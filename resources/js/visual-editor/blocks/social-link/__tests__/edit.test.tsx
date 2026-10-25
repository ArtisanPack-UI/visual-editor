/**
 * Tests for the `artisanpack/social-link` edit component (#1023).
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

const removeBlock = vi.fn();

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({ keyboardReturn: 'return-icon' }));
vi.mock('@wordpress/keycodes', () => ({ BACKSPACE: 8, DELETE: 46, ENTER: 13 }));
vi.mock('@wordpress/compose', () => ({ useMergeRefs: () => () => undefined }));
vi.mock('@wordpress/data', () => ({ useDispatch: () => ({ removeBlock }) }));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return {
        useRef: React.useRef,
        useState: React.useState,
        createInterpolateElement: (text: string) => text,
    };
});

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    InspectorControls: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    URLPopover: ({ children }: { children: React.ReactNode }) => <div data-testid="url-popover">{children}</div>,
    URLInput: ({
        value,
        onChange,
        onKeyDown,
    }: {
        value?: string;
        onChange: (value: string) => void;
        onKeyDown: (event: { keyCode: number; defaultPrevented: boolean }) => void;
    }) => (
        <input
            aria-label="Enter social link"
            value={value ?? ''}
            onChange={(event) => onChange(event.target.value)}
            onKeyDown={(event) => onKeyDown({ keyCode: event.keyCode, defaultPrevented: false })}
        />
    ),
    useBlockEditingMode: () => 'default',
    useBlockProps: (props: Record<string, unknown>) => {
        const { ref: _ref, ...rest } = props;
        return rest;
    },
    store: 'block-editor',
}));

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
    return {
        Button: () => null,
        Dropdown: () => null,
        ExternalLink: () => null,
        TextControl: ({ label, value, placeholder }: { label: string; value: string; placeholder?: string }) => (
            <input aria-label={label} value={value} placeholder={placeholder} readOnly />
        ),
        ToolbarButton: () => null,
        __experimentalInputControlSuffixWrapper: Passthrough,
        __experimentalToolsPanel: Passthrough,
        __experimentalToolsPanelItem: Passthrough,
    };
});

(globalThis as { React?: unknown }).React = require('react');

import SocialLinkEdit from '../edit';

function renderEdit(
    attributes: Record<string, unknown>,
    context: Record<string, unknown> = {},
    isSelected = true,
    setAttributes = vi.fn()
) {
    return {
        setAttributes,
        ...render(
            <SocialLinkEdit
                attributes={attributes}
                context={context}
                isSelected={isSelected}
                setAttributes={setAttributes}
                clientId="link-1"
            />
        ),
    };
}

describe('SocialLinkEdit', () => {
    it('renders the WordPress classes for the service and flags a missing URL', () => {
        const { container } = renderEdit({ service: 'facebook' });
        const li = container.querySelector('li');

        expect(li?.className).toBe(
            'wp-social-link wp-block-social-link wp-social-link-facebook wp-social-link__is-incomplete'
        );
        expect(container.querySelector('button')?.className).toBe('wp-block-social-link-anchor');
    });

    it('hides the label for screen readers only unless the parent shows text', () => {
        const hidden = renderEdit({ service: 'github', url: 'https://github.com' });
        expect(hidden.container.querySelector('.wp-block-social-link-label')?.className).toBe(
            'wp-block-social-link-label screen-reader-text'
        );
        expect(hidden.container.querySelector('.wp-block-social-link-label')?.textContent).toBe('GitHub');

        const shown = renderEdit(
            { service: 'github', url: 'https://github.com', label: 'Code' },
            { 'artisanpack/socialLinksShowLabels': true }
        );
        expect(shown.container.querySelector('.wp-block-social-link-label')?.className).toBe(
            'wp-block-social-link-label'
        );
        expect(shown.container.querySelector('.wp-block-social-link-label')?.textContent).toBe('Code');
    });

    it('leaves the icon styling to the parent (no inline style)', () => {
        const { container } = renderEdit({ service: 'x', url: 'https://x.com' });

        expect(container.querySelector('li')?.getAttribute('style')).toBeNull();
    });

    it('falls back to a generic name for an unknown service', () => {
        const { container, getByLabelText } = renderEdit({ service: 'myspace', url: 'https://example.com' });

        expect(container.querySelector('.wp-block-social-link-label')?.textContent).toBe('Social Icon');
        expect((getByLabelText('Text') as HTMLInputElement).placeholder).toBe('Social Icon');
    });

    it('opens the URL popover on click and writes the URL', () => {
        const { container, getByLabelText, setAttributes, queryByTestId } = renderEdit({ service: 'facebook' });

        expect(queryByTestId('url-popover')).toBeNull();
        fireEvent.click(container.querySelector('button') as HTMLButtonElement);
        fireEvent.change(getByLabelText('Enter social link'), { target: { value: 'https://facebook.com/me' } });

        expect(setAttributes).toHaveBeenCalledWith({ url: 'https://facebook.com/me' });
    });

    it('does not open the popover while the block is not selected', () => {
        const { container, queryByTestId } = renderEdit({ service: 'facebook' }, {}, false);

        fireEvent.click(container.querySelector('button') as HTMLButtonElement);
        expect(queryByTestId('url-popover')).toBeNull();
    });

    it('removes an empty icon when Backspace is pressed in the URL field', () => {
        removeBlock.mockClear();
        const { container, getByLabelText } = renderEdit({ service: 'facebook' });

        fireEvent.click(container.querySelector('button') as HTMLButtonElement);
        fireEvent.keyDown(getByLabelText('Enter social link'), { keyCode: 8 });
        expect(removeBlock).toHaveBeenCalledWith('link-1');
    });

    it('keeps an icon that has a URL when Backspace is pressed', () => {
        removeBlock.mockClear();
        const { container, getByLabelText } = renderEdit({ service: 'facebook', url: 'https://facebook.com' });

        fireEvent.click(container.querySelector('button') as HTMLButtonElement);
        fireEvent.keyDown(getByLabelText('Enter social link'), { keyCode: 8 });
        expect(removeBlock).not.toHaveBeenCalled();
    });
});
