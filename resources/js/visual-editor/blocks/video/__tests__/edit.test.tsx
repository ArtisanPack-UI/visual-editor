/**
 * Tests for the `artisanpack/video` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({
    video: 'video-icon',
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({ createErrorNotice: vi.fn() }),
}));

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/element', () => {
    const react = require('react') as typeof import('react');
    return {
        useRef: react.useRef,
        useEffect: react.useEffect,
        useState: react.useState,
    };
});

vi.mock('@wordpress/components', () => ({
    Disabled: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    SelectControl: () => null,
    ToggleControl: () => null,
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
    MediaUpload: () => null,
    MediaUploadCheck: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    RichText: ({ value }: { value?: string }) => (
        <span dangerouslySetInnerHTML={{ __html: value ?? '' }} />
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useBlockEditingMode: () => 'default',
}));

(globalThis as { React?: unknown }).React = require('react');

import VideoEdit from '../edit';

describe('VideoEdit', () => {
    it('renders the MediaPlaceholder when no src is set', () => {
        const setAttributes = vi.fn();
        const { getByTestId, queryByTestId } = render(
            <VideoEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
        expect(queryByTestId('replace-flow')).toBeNull();
    });

    it('renders a video element when src is set', () => {
        const setAttributes = vi.fn();
        const { container, getByTestId } = render(
            <VideoEdit
                attributes={{ src: 'https://example.com/clip.mp4' }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const video = container.querySelector('video');
        expect(video).toBeTruthy();
        expect(video?.getAttribute('src')).toBe(
            'https://example.com/clip.mp4'
        );
        expect(getByTestId('replace-flow')).toBeTruthy();
    });

    it('renders track elements for tracks attribute', () => {
        const setAttributes = vi.fn();
        const { container } = render(
            <VideoEdit
                attributes={{
                    src: 'https://example.com/clip.mp4',
                    tracks: [
                        {
                            id: 1,
                            src: 'https://example.com/en.vtt',
                            srcLang: 'en',
                            kind: 'subtitles',
                            label: 'English',
                        },
                    ],
                }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const track = container.querySelector('track');
        expect(track).toBeTruthy();
        expect(track?.getAttribute('src')).toBe(
            'https://example.com/en.vtt'
        );
    });
});
