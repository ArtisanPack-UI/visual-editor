/**
 * Tests for the `artisanpack/social-links` edit and save components (#1023).
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';
import { renderToStaticMarkup } from 'react-dom/server';

let blockCount = 1;

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: (selector: (select: () => unknown) => unknown) =>
        selector(() => ({
            getBlockCount: () => blockCount,
            hasSelectedInnerBlock: () => false,
        })),
}));

vi.mock('@wordpress/block-editor', () => {
    const useBlockProps = Object.assign((props: Record<string, unknown>) => ({ ...props }), {
        save: (props: Record<string, unknown>) => ({ ...props }),
    });
    const useInnerBlocksProps = Object.assign(
        (props: Record<string, unknown>, options: { renderAppender?: unknown }) => ({
            ...props,
            'data-appender': options.renderAppender ? 'yes' : 'no',
        }),
        { save: (props: Record<string, unknown>) => ({ ...props }) }
    );
    return {
        ContrastChecker: () => null,
        InnerBlocks: { ButtonBlockAppender: () => null },
        InspectorControls: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
        useBlockProps,
        useInnerBlocksProps,
        withColors: () => (component: unknown) => component,
        __experimentalColorGradientSettingsDropdown: ({ settings }: { settings: { label: string }[] }) => (
            <span data-testid="color-setting">{settings[0].label}</span>
        ),
        __experimentalUseMultipleOriginColorsAndGradients: () => ({ hasColorsOrGradients: true }),
        store: 'block-editor',
    };
});

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
    return {
        SelectControl: ({
            label,
            value,
            options,
            onChange,
        }: {
            label: string;
            value: string;
            options: { label: string; value: string }[];
            onChange: (value: string) => void;
        }) => (
            <select aria-label={label} value={value} onChange={(event) => onChange(event.target.value)}>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        ),
        ToggleControl: ({ label, checked, onChange }: { label: string; checked: boolean; onChange: () => void }) => (
            <input type="checkbox" aria-label={label} checked={checked} onChange={onChange} />
        ),
        __experimentalBorderControl: ({
            value,
            onChange,
        }: {
            value: { color?: string; style?: string; width?: string };
            onChange: (next: unknown) => void;
        }) => (
            <div data-testid="border-control" data-value={JSON.stringify(value)}>
                <button
                    data-testid="set-border"
                    onClick={() => onChange({ color: '#cccccc', style: 'dashed', width: '2px' })}
                />
                <button data-testid="set-bad-border" onClick={() => onChange({ style: 'groove; color:red' })} />
                <button data-testid="clear-border" onClick={() => onChange(undefined)} />
            </div>
        ),
        __experimentalUnitControl: ({
            label,
            value,
            onChange,
        }: {
            label: string;
            value: string;
            onChange: (value?: string) => void;
        }) => <input aria-label={label} value={value} onChange={(event) => onChange(event.target.value)} />,
        __experimentalToolsPanel: Passthrough,
        __experimentalToolsPanelItem: Passthrough,
    };
});

(globalThis as { React?: unknown }).React = require('react');

import { SocialLinksEdit } from '../edit';
import save from '../save';
import { socialLinksClassName, socialLinksStyle } from '../utils';

function renderEdit(attributes: Record<string, unknown>, setAttributes = vi.fn(), isSelected = false) {
    return {
        setAttributes,
        ...render(
            <SocialLinksEdit
                clientId="links-1"
                attributes={attributes}
                iconColor={{}}
                iconBackgroundColor={{}}
                isSelected={isSelected}
                setAttributes={setAttributes}
                setIconColor={vi.fn()}
                setIconBackgroundColor={vi.fn()}
            />
        ),
    };
}

const ALL_ICON_STYLES = {
    iconColorValue: '#ffffff',
    iconBackgroundColorValue: '#000000',
    iconBorderColorValue: '#cccccc',
    iconBorderWidth: '2px',
    iconBorderStyle: 'dashed',
    iconBorderRadius: '8px',
    iconPadding: '0.5em',
    iconHoverColorValue: '#111111',
    iconHoverBackgroundColorValue: '#222222',
    iconHoverBorderColorValue: '#333333',
};

describe('socialLinksClassName', () => {
    it('adds the WordPress wrapper class and only allowlisted sizes', () => {
        expect(socialLinksClassName({})).toBe('wp-block-social-links');
        expect(socialLinksClassName({ size: 'has-huge-icon-size', showLabels: true })).toBe(
            'wp-block-social-links has-huge-icon-size has-visible-labels'
        );
        expect(socialLinksClassName({ size: 'evil"><script>' })).toBe('wp-block-social-links');
    });

    it('adds a has-icon-* class per set icon style', () => {
        expect(socialLinksClassName(ALL_ICON_STYLES)).toBe(
            'wp-block-social-links has-icon-color has-icon-background-color has-icon-border has-icon-border-radius ' +
                'has-icon-padding has-icon-hover-color has-icon-hover-background-color has-icon-hover-border-color'
        );
        expect(socialLinksClassName({ iconBorderStyle: 'dotted' })).toBe('wp-block-social-links has-icon-border');
        expect(socialLinksClassName({ iconBorderStyle: 'groove' })).toBe('wp-block-social-links');
    });

    it('prefers the palette colours withColors resolved', () => {
        expect(socialLinksClassName({}, { iconColor: '#abcdef' })).toBe('wp-block-social-links has-icon-color');
    });
});

describe('socialLinksStyle', () => {
    it('emits the custom properties in renderer order', () => {
        expect(Object.entries(socialLinksStyle(ALL_ICON_STYLES))).toEqual([
            ['--ap-social-links-color', '#ffffff'],
            ['--ap-social-links-bg', '#000000'],
            ['--ap-social-links-border-color', '#cccccc'],
            ['--ap-social-links-border-width', '2px'],
            ['--ap-social-links-border-style', 'dashed'],
            ['--ap-social-links-radius', '8px'],
            ['--ap-social-links-padding', '0.5em'],
            ['--ap-social-links-hover-color', '#111111'],
            ['--ap-social-links-hover-bg', '#222222'],
            ['--ap-social-links-hover-border-color', '#333333'],
        ]);
    });

    it('skips empty values', () => {
        expect(socialLinksStyle({ iconColorValue: '  ', iconBorderRadius: '' })).toEqual({});
    });
});

describe('SocialLinksSave', () => {
    it('saves a <ul> with the setting classes and icon style properties', () => {
        const html = renderToStaticMarkup(
            save({
                attributes: {
                    size: 'has-small-icon-size',
                    showLabels: true,
                    iconColorValue: '#000',
                    iconHoverColorValue: '#fff',
                },
            })
        );

        expect(html).toBe(
            '<ul class="wp-block-social-links has-small-icon-size has-visible-labels has-icon-color has-icon-hover-color" ' +
                'style="--ap-social-links-color:#000;--ap-social-links-hover-color:#fff"></ul>'
        );
    });

    it('saves no style attribute without icon styles', () => {
        expect(renderToStaticMarkup(save({ attributes: {} }))).toBe('<ul class="wp-block-social-links"></ul>');
    });
});

describe('SocialLinksEdit', () => {
    it('renders the list with the setting classes', () => {
        const { container } = renderEdit({ size: 'has-large-icon-size', showLabels: true });

        expect(container.querySelector('ul')?.className).toBe(
            'wp-block-social-links has-large-icon-size has-visible-labels'
        );
    });

    it('writes the icon size, show text and new tab settings', () => {
        const { getByLabelText, setAttributes } = renderEdit({ showLabels: false, openInNewTab: false });

        fireEvent.change(getByLabelText('Icon size'), { target: { value: 'has-huge-icon-size' } });
        expect(setAttributes).toHaveBeenCalledWith({ size: 'has-huge-icon-size' });

        fireEvent.change(getByLabelText('Icon size'), { target: { value: '' } });
        expect(setAttributes).toHaveBeenCalledWith({ size: undefined });

        fireEvent.click(getByLabelText('Show text'));
        expect(setAttributes).toHaveBeenCalledWith({ showLabels: true });

        fireEvent.click(getByLabelText('Open links in new tab'));
        expect(setAttributes).toHaveBeenCalledWith({ openInNewTab: true });
    });

    it('offers every icon color setting, Logos Only included', () => {
        const expected = [
            'Icon color',
            'Icon background',
            'Icon hover color',
            'Icon hover background',
            'Icon hover border',
        ];
        const regular = renderEdit({});
        expect(regular.getAllByTestId('color-setting').map((node) => node.textContent)).toEqual(expected);
        regular.unmount();

        const logosOnly = renderEdit({ className: 'is-style-logos-only' });
        expect(logosOnly.getAllByTestId('color-setting').map((node) => node.textContent)).toEqual(expected);
    });

    it('keeps the icon background when Logos Only is chosen', () => {
        const setAttributes = vi.fn();
        const { container } = renderEdit(
            { className: 'is-style-logos-only', iconBackgroundColorValue: '#ff0000' },
            setAttributes
        );

        expect(setAttributes).not.toHaveBeenCalled();
        expect(container.querySelector('ul')?.className).toContain('has-icon-background-color');
    });

    it('puts the icon style properties on the list', () => {
        const { container } = renderEdit({ iconBorderRadius: '8px', iconHoverColorValue: '#ff0000' });
        const list = container.querySelector('ul') as HTMLUListElement;

        expect(list.className).toBe('wp-block-social-links has-icon-border-radius has-icon-hover-color');
        expect(list.style.getPropertyValue('--ap-social-links-radius')).toBe('8px');
        expect(list.style.getPropertyValue('--ap-social-links-hover-color')).toBe('#ff0000');
    });

    it('writes the icon border from the border control and drops unknown styles', () => {
        const { getByTestId, setAttributes } = renderEdit({});

        fireEvent.click(getByTestId('set-border'));
        expect(setAttributes).toHaveBeenCalledWith({
            iconBorderColorValue: '#cccccc',
            iconBorderWidth: '2px',
            iconBorderStyle: 'dashed',
        });

        fireEvent.click(getByTestId('set-bad-border'));
        expect(setAttributes).toHaveBeenLastCalledWith({
            iconBorderColorValue: undefined,
            iconBorderWidth: undefined,
            iconBorderStyle: undefined,
        });

        fireEvent.click(getByTestId('clear-border'));
        expect(setAttributes).toHaveBeenLastCalledWith({
            iconBorderColorValue: undefined,
            iconBorderWidth: undefined,
            iconBorderStyle: undefined,
        });
    });

    it('passes the saved icon border to the border control and writes the radius', () => {
        const { getByTestId, getByLabelText, setAttributes } = renderEdit({
            iconBorderColorValue: '#cccccc',
            iconBorderStyle: 'solid',
            iconBorderWidth: '1px',
            iconBorderRadius: '4px',
        });

        expect(JSON.parse(getByTestId('border-control').dataset.value ?? '{}')).toEqual({
            color: '#cccccc',
            style: 'solid',
            width: '1px',
        });

        fireEvent.change(getByLabelText('Radius'), { target: { value: '12px' } });
        expect(setAttributes).toHaveBeenCalledWith({ iconBorderRadius: '12px' });

        fireEvent.change(getByLabelText('Radius'), { target: { value: '' } });
        expect(setAttributes).toHaveBeenLastCalledWith({ iconBorderRadius: undefined });
    });

    it('writes the icon padding', () => {
        const { getByLabelText, setAttributes } = renderEdit({ iconPadding: '4px' });

        fireEvent.change(getByLabelText('Padding'), { target: { value: '0.5em' } });
        expect(setAttributes).toHaveBeenCalledWith({ iconPadding: '0.5em' });

        fireEvent.change(getByLabelText('Padding'), { target: { value: '' } });
        expect(setAttributes).toHaveBeenLastCalledWith({ iconPadding: undefined });
    });

    it('shows the appender for an empty block or while it is selected', () => {
        blockCount = 0;
        expect(renderEdit({}).container.querySelector('ul')?.getAttribute('data-appender')).toBe('yes');

        blockCount = 2;
        expect(renderEdit({}).container.querySelector('ul')?.getAttribute('data-appender')).toBe('no');
        expect(renderEdit({}, vi.fn(), true).container.querySelector('ul')?.getAttribute('data-appender')).toBe('yes');
        blockCount = 1;
    });
});
