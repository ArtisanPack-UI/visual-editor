/**
 * Tests for the `artisanpack/image` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
    sprintf: (text: string, ...args: unknown[]) => `${text}:${args.join(',')}`,
}));

vi.mock('@wordpress/icons', () => ({
    image: 'image-icon',
    plugins: 'plugins-icon',
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({ createErrorNotice: vi.fn() }),
    useSelect: () => ({}),
}));

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/blob', () => ({
    isBlobURL: (url?: string) => !!url && url.startsWith('blob:'),
    createBlobURL: (file: File) => `blob:mock/${file.name}`,
}));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return {
        useState: React.useState,
        useEffect: React.useEffect,
        useRef: React.useRef,
        useCallback: React.useCallback,
        useMemo: React.useMemo,
    };
});

vi.mock('@wordpress/compose', () => ({
    useResizeObserver: () => [null, { width: 0 }],
}));

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: React.ReactNode }) => (
        <div>{children}</div>
    );
    const BaseControl = Object.assign(Passthrough, {
        VisualLabel: ({ children }: { children?: React.ReactNode }) => (
            <span>{children}</span>
        ),
    });
    return {
        BaseControl,
        Button: ({ children, ...rest }: { children?: React.ReactNode }) => (
            <button {...rest}>{children}</button>
        ),
        ButtonGroup: Passthrough,
        CheckboxControl: () => null,
        Flex: Passthrough,
        FlexItem: Passthrough,
        ResizableBox: ({ children }: { children: React.ReactNode }) => (
            <div data-testid="resizable-box">{children}</div>
        ),
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
            <select
                aria-label={label}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        ),
        TextareaControl: () => null,
        TextControl: ({ label, value }: { label: string; value: string }) => (
            <input aria-label={label} value={value} readOnly />
        ),
        __experimentalHStack: Passthrough,
        __experimentalNumberControl: () => null,
        __experimentalToggleGroupControl: Passthrough,
        __experimentalToggleGroupControlOption: () => null,
        __experimentalToolsPanel: Passthrough,
        __experimentalToolsPanelItem: Passthrough,
        __experimentalVStack: Passthrough,
    };
});

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    BlockIcon: () => null,
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    MediaPlaceholder: () => <div data-testid="placeholder" />,
    MediaReplaceFlow: ({
        onSelect,
        onSelectURL,
    }: {
        onSelect: (media: { id: number; url: string }) => void;
        onSelectURL: (url: string) => void;
    }) => (
        <div data-testid="replace-flow">
            <button
                data-testid="replace-media"
                onClick={() =>
                    onSelect({ id: 9, url: 'https://example.com/new.jpg' })
                }
            />
            <button
                data-testid="replace-url"
                onClick={() => onSelectURL('https://example.com/pasted.jpg')}
            />
        </div>
    ),
    RichText: Object.assign(
        ({ value }: { value?: string }) => (
            <span dangerouslySetInnerHTML={{ __html: value ?? '' }} />
        ),
        {
            isEmpty: (value?: string) => !value || value === '',
        }
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useBlockEditingMode: () => 'default',
    __experimentalGetBorderClassesAndStyles: () => ({
        className: '',
        style: {},
    }),
    __experimentalGetShadowClassesAndStyles: () => ({
        className: '',
        style: {},
    }),
}));

vi.mock('../../_shared/link-toolbar-control', () => ({
    default: ({
        url,
        onChange,
        onRemove,
    }: {
        url?: string;
        onChange: (next: {
            url: string;
            linkTarget: string | undefined;
            rel: string | undefined;
        }) => void;
        onRemove: () => void;
    }) => (
        <div data-testid="link-toolbar" data-url={url ?? ''}>
            <button
                data-testid="set-custom-link"
                onClick={() =>
                    onChange({
                        url: 'https://example.com/page',
                        linkTarget: '_blank',
                        rel: 'noreferrer noopener',
                    })
                }
            />
            <button
                data-testid="set-media-link"
                onClick={() =>
                    onChange({
                        url: 'https://example.com/photo.jpg',
                        linkTarget: undefined,
                        rel: undefined,
                    })
                }
            />
            <button data-testid="remove-link" onClick={onRemove} />
        </div>
    ),
}));

(globalThis as { React?: unknown }).React = require('react');

import ImageEdit from '../edit';
import {
    getLinkDestinationAttributes,
    resolveLinkDestination,
} from '../image-editor';

const PHOTO = 'https://example.com/photo.jpg';

describe('ImageEdit', () => {
    it('renders the MediaPlaceholder when no url is set', () => {
        const setAttributes = vi.fn();
        const { getByTestId, queryByTestId } = render(
            <ImageEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
        expect(queryByTestId('replace-flow')).toBeNull();
    });

    it('renders an img element when url is set', () => {
        const setAttributes = vi.fn();
        const { container, getByTestId } = render(
            <ImageEdit
                attributes={{ url: 'https://example.com/photo.jpg' }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const img = container.querySelector('img');
        expect(img).toBeTruthy();
        expect(img?.getAttribute('src')).toBe('https://example.com/photo.jpg');
        expect(getByTestId('replace-flow')).toBeTruthy();
    });

    it('wraps the image in an anchor when href is set', () => {
        const setAttributes = vi.fn();
        const { container } = render(
            <ImageEdit
                attributes={{
                    url: 'https://example.com/photo.jpg',
                    href: 'https://example.com/page',
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const link = container.querySelector('a');
        expect(link).toBeTruthy();
        expect(link?.getAttribute('href')).toBe('https://example.com/page');
    });

    it('sets a custom link from the toolbar', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <ImageEdit
                attributes={{ url: PHOTO }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        fireEvent.click(getByTestId('set-custom-link'));
        expect(setAttributes).toHaveBeenCalledWith({
            href: 'https://example.com/page',
            linkTarget: '_blank',
            rel: 'noreferrer noopener',
            linkDestination: 'custom',
        });
    });

    it('treats a toolbar link to the image itself as a media-file link', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <ImageEdit
                attributes={{ url: PHOTO }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        fireEvent.click(getByTestId('set-media-link'));
        expect(setAttributes).toHaveBeenCalledWith(
            expect.objectContaining({ href: PHOTO, linkDestination: 'media' })
        );
    });

    it('clears every link attribute except the class on unlink', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <ImageEdit
                attributes={{
                    url: PHOTO,
                    href: 'https://example.com/page',
                    linkTarget: '_blank',
                    rel: 'noopener',
                    linkClass: 'keep-me',
                    linkDestination: 'custom',
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        fireEvent.click(getByTestId('remove-link'));
        expect(setAttributes).toHaveBeenCalledWith({
            href: undefined,
            linkTarget: undefined,
            rel: undefined,
            linkDestination: 'none',
        });
    });

    it('does not render the link toolbar when the block is not selected', () => {
        const { queryByTestId } = render(
            <ImageEdit
                attributes={{ url: PHOTO }}
                setAttributes={vi.fn()}
                isSelected={false}
            />
        );
        expect(queryByTestId('link-toolbar')).toBeNull();
    });

    it('links to the media file from the "Link to" select', () => {
        const setAttributes = vi.fn();
        const { getByLabelText } = render(
            <ImageEdit
                attributes={{ url: PHOTO }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const select = getByLabelText('Link to') as HTMLSelectElement;
        expect(select.value).toBe('none');
        fireEvent.change(select, { target: { value: 'media' } });
        expect(setAttributes).toHaveBeenCalledWith({
            href: PHOTO,
            linkDestination: 'media',
        });
    });

    it('shows the advanced link fields only when the image is linked', () => {
        const { queryByLabelText, rerender } = render(
            <ImageEdit
                attributes={{ url: PHOTO }}
                setAttributes={vi.fn()}
                isSelected
            />
        );
        expect(queryByLabelText('Link CSS class')).toBeNull();

        rerender(
            <ImageEdit
                attributes={{
                    url: PHOTO,
                    href: 'https://example.com/page',
                    rel: 'nofollow',
                    linkClass: 'promo',
                }}
                setAttributes={vi.fn()}
                isSelected
            />
        );
        expect(
            (queryByLabelText('Link CSS class') as HTMLInputElement).value
        ).toBe('promo');
        expect(
            (queryByLabelText('Link relation') as HTMLInputElement).value
        ).toBe('nofollow');
    });

    it('moves a media-file link along with a replaced image', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <ImageEdit
                attributes={{
                    url: PHOTO,
                    id: 1,
                    href: PHOTO,
                    linkDestination: 'media',
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        fireEvent.click(getByTestId('replace-media'));
        expect(setAttributes).toHaveBeenCalledWith(
            expect.objectContaining({
                url: 'https://example.com/new.jpg',
                href: 'https://example.com/new.jpg',
            })
        );

        fireEvent.click(getByTestId('replace-url'));
        expect(setAttributes).toHaveBeenCalledWith(
            expect.objectContaining({
                url: 'https://example.com/pasted.jpg',
                href: 'https://example.com/pasted.jpg',
            })
        );
    });

    it('leaves a custom link alone when the image is replaced', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <ImageEdit
                attributes={{
                    url: PHOTO,
                    id: 1,
                    href: 'https://example.com/page',
                    linkDestination: 'custom',
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        fireEvent.click(getByTestId('replace-media'));
        expect(setAttributes.mock.calls[0][0]).not.toHaveProperty('href');
    });
});

describe('resolveLinkDestination', () => {
    it.each([
        [{ url: PHOTO }, 'none'],
        [{ url: PHOTO, linkDestination: 'custom' }, 'custom'],
        [{ url: PHOTO, linkDestination: 'media' }, 'none'],
        [{ url: PHOTO, href: PHOTO }, 'media'],
        [{ url: PHOTO, href: 'https://example.com/page' }, 'custom'],
        [{ url: PHOTO, href: PHOTO, linkDestination: 'custom' }, 'custom'],
        [{ url: PHOTO, href: 'https://x.test', linkDestination: 'attachment' }, 'custom'],
    ])('resolves %j to %s', (attributes, expected) => {
        expect(resolveLinkDestination(attributes)).toBe(expected);
    });
});

describe('getLinkDestinationAttributes', () => {
    it('keeps an existing custom href when re-selecting "Custom URL"', () => {
        expect(
            getLinkDestinationAttributes('custom', {
                url: PHOTO,
                href: 'https://example.com/page',
            })
        ).toEqual({
            href: 'https://example.com/page',
            linkDestination: 'custom',
        });
    });

    it('drops a media-file href when switching to "Custom URL"', () => {
        expect(
            getLinkDestinationAttributes('custom', { url: PHOTO, href: PHOTO })
        ).toEqual({ href: undefined, linkDestination: 'custom' });
    });
});
