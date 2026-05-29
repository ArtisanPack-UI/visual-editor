/**
 * Tests for the `artisanpack/search` edit component.
 *
 * Focuses on the editor preview's accessible button output: the icon
 * button must always carry an aria-label (buttonText → label → "Search"),
 * mirroring the front-end renderers' #338 fix, and the text button must
 * render the button text via RichText.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (text: string) => text,
}));

vi.mock('@wordpress/dom', () => ({
    __unstableStripHTML: (html: string) => html,
}));

vi.mock('@wordpress/element', () => ({
    useEffect: () => undefined,
    useRef: () => ({ current: null }),
}));

vi.mock('@wordpress/compose', () => ({
    useInstanceId: () => 'instance-id',
}));

vi.mock('@wordpress/icons', () => ({
    Icon: () => <span data-testid="search-icon" />,
    search: {},
}));

vi.mock('@wordpress/data', () => ({
    useSelect: () => false,
    useDispatch: () => ({ __unstableMarkNextChangeAsNotPersistent: () => undefined }),
}));

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: React.ReactNode }) => <>{children}</>;
    return {
        SelectControl: () => null,
        ToggleControl: () => null,
        ResizableBox: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
        __experimentalUseCustomUnits: () => [],
        __experimentalUnitControl: () => null,
        __experimentalToggleGroupControl: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
        __experimentalToggleGroupControlOption: () => null,
        __experimentalToolsPanel: Passthrough,
        __experimentalToolsPanelItem: Passthrough,
        __experimentalVStack: Passthrough,
    };
});

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    RichText: ({ value, 'aria-label': ariaLabel, className }: { value?: string; 'aria-label'?: string; className?: string }) => (
        <span role="textbox" aria-label={ariaLabel} className={className}>
            {value}
        </span>
    ),
    store: { name: 'core/block-editor' },
    __experimentalGetElementClassName: () => 'wp-element-button',
    useSettings: () => [undefined, undefined],
    __experimentalUseBorderProps: () => ({ className: '', style: {} }),
    __experimentalUseColorProps: () => ({ className: '', style: {} }),
    getTypographyClassesAndStyles: () => ({ className: '', style: {} }),
}));

(globalThis as { React?: unknown }).React = require('react');

import SearchEdit from '../edit';

describe('SearchEdit', () => {
    it('renders the search input and label', () => {
        const { container } = render(
            <SearchEdit
                attributes={{ showLabel: true, label: 'Find', buttonPosition: 'button-outside' }}
                setAttributes={vi.fn()}
            />
        );
        expect(container.querySelector('input[type="search"]')).toBeTruthy();
    });

    it('labels the icon button with the button text', () => {
        const { container } = render(
            <SearchEdit
                attributes={{ buttonUseIcon: true, buttonText: 'Go', buttonPosition: 'button-outside' }}
                setAttributes={vi.fn()}
            />
        );
        const button = container.querySelector('button[aria-label]');
        expect(button).toBeTruthy();
        expect(button?.getAttribute('aria-label')).toBe('Go');
    });

    it('falls back to the label, then "Search", when the icon button has no button text', () => {
        const { container: withLabel } = render(
            <SearchEdit
                attributes={{ buttonUseIcon: true, label: 'Site search', buttonPosition: 'button-outside' }}
                setAttributes={vi.fn()}
            />
        );
        expect(withLabel.querySelector('button')?.getAttribute('aria-label')).toBe('Site search');

        const { container: bare } = render(
            <SearchEdit
                attributes={{ buttonUseIcon: true, buttonPosition: 'button-outside' }}
                setAttributes={vi.fn()}
            />
        );
        expect(bare.querySelector('button')?.getAttribute('aria-label')).toBe('Search');
    });

    it('renders the text button via RichText when not using an icon', () => {
        const { getByRole } = render(
            <SearchEdit
                attributes={{ buttonUseIcon: false, buttonText: 'Submit', buttonPosition: 'button-outside' }}
                setAttributes={vi.fn()}
            />
        );
        expect(getByRole('textbox').textContent).toBe('Submit');
    });

    it('mounts the inspector controls', () => {
        const { getByTestId } = render(
            <SearchEdit
                attributes={{ buttonPosition: 'button-outside' }}
                setAttributes={vi.fn()}
            />
        );
        expect(getByTestId('inspector')).toBeTruthy();
    });
});
