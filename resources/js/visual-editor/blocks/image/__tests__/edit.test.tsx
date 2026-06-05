/**
 * Tests for the `artisanpack/image` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

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

vi.mock('@wordpress/components', () => ({
    TextareaControl: () => null,
    TextControl: () => null,
    __experimentalToolsPanel: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
    __experimentalToolsPanelItem: ({
        children,
    }: {
        children: React.ReactNode;
    }) => <div>{children}</div>,
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    BlockIcon: () => null,
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    MediaPlaceholder: () => <div data-testid="placeholder" />,
    MediaReplaceFlow: () => <div data-testid="replace-flow" />,
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

(globalThis as { React?: unknown }).React = require('react');

import ImageEdit from '../edit';

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
});
