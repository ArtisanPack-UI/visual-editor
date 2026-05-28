/**
 * Tests for the `artisanpack/media-text` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({
    pullLeft: 'pull-left-icon',
    pullRight: 'pull-right-icon',
    media: 'media-icon',
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({
        createErrorNotice: vi.fn(),
        toggleSelection: vi.fn(),
    }),
    useSelect: (selector: (s: unknown) => unknown) => {
        const fakeStore = {
            getSettings: () => ({ imageSizes: [] }),
            getEntityRecord: () => null,
        };
        return selector(() => fakeStore);
    },
}));

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/core-data', () => ({
    store: 'core-store',
    useEntityProp: () => [undefined, () => undefined],
}));

vi.mock('@wordpress/blob', () => ({
    isBlobURL: (value?: string) => !!value && value.startsWith('blob:'),
    getBlobTypeByURL: () => 'image',
    createBlobURL: (file: File) => `blob:${file.name}`,
}));

vi.mock('@wordpress/compose', () => ({
    useViewportMatch: () => false,
    compose: (...fns: Array<(v: unknown) => unknown>) =>
        (v: unknown) =>
            fns.reduceRight((acc, fn) => fn(acc), v),
}));

vi.mock('@wordpress/element', () => {
    const React = require('react');
    return {
        useRef: React.useRef,
        useState: React.useState,
        forwardRef: React.forwardRef,
    };
});

vi.mock('@wordpress/components', () => ({
    ExternalLink: ({ children }: { children: React.ReactNode }) => (
        <a>{children}</a>
    ),
    FocalPointPicker: () => null,
    Placeholder: () => null,
    RangeControl: () => null,
    ResizableBox: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="resizable">{children}</div>
    ),
    Spinner: () => null,
    TextareaControl: () => null,
    ToggleControl: () => null,
    ToolbarButton: () => <button type="button" />,
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
    BlockVerticalAlignmentControl: () => null,
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    MediaPlaceholder: () => <div data-testid="placeholder" />,
    MediaReplaceFlow: () => <div data-testid="replace-flow" />,
    store: 'block-editor-store',
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useBlockEditingMode: () => 'default',
    useInnerBlocksProps: Object.assign(
        (props?: Record<string, unknown>) => ({
            ...props,
            'data-testid': 'inner-blocks',
        }),
        {
            save: (props?: Record<string, unknown>) => ({ ...props }),
        }
    ),
    __experimentalImageURLInputUI: () => <div data-testid="image-url-ui" />,
}));

(globalThis as { React?: unknown }).React = require('react');

import MediaTextEdit from '../edit';

describe('MediaTextEdit', () => {
    it('renders the MediaPlaceholder when no media has been selected', () => {
        const setAttributes = vi.fn();
        const { getByTestId, queryByTestId } = render(
            <MediaTextEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
        expect(queryByTestId('replace-flow')).toBeNull();
        expect(getByTestId('inspector')).toBeTruthy();
        expect(getByTestId('block-controls')).toBeTruthy();
    });

    it('renders the resizable media container when media is set', () => {
        const setAttributes = vi.fn();
        const { container, getByTestId } = render(
            <MediaTextEdit
                attributes={{
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    mediaWidth: 50,
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const img = container.querySelector('img');
        expect(img).toBeTruthy();
        expect(img?.getAttribute('src')).toBe('https://example.com/pic.jpg');
        expect(getByTestId('resizable')).toBeTruthy();
        expect(getByTestId('replace-flow')).toBeTruthy();
    });

    it('renders a <video> when mediaType is video', () => {
        const setAttributes = vi.fn();
        const { container } = render(
            <MediaTextEdit
                attributes={{
                    mediaUrl: 'https://example.com/clip.mp4',
                    mediaType: 'video',
                    mediaWidth: 50,
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const video = container.querySelector('video');
        expect(video).toBeTruthy();
        expect(video?.getAttribute('src')).toBe(
            'https://example.com/clip.mp4'
        );
    });

    it('renders the inner blocks container alongside the media figure', () => {
        const setAttributes = vi.fn();
        const { container } = render(
            <MediaTextEdit
                attributes={{
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    mediaWidth: 50,
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const innerBlocks = container.querySelector(
            '[data-testid="inner-blocks"]'
        );
        expect(innerBlocks).toBeTruthy();
    });
});
