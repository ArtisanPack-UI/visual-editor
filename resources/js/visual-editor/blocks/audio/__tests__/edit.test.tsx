/**
 * Tests for the `artisanpack/audio` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({
    audio: 'audio-icon',
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({ createErrorNotice: vi.fn() }),
}));

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/components', () => ({
    Disabled: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    SelectControl: () => null,
    ToggleControl: () => null,
    __experimentalToolsPanel: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
    __experimentalToolsPanelItem: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
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
    RichText: ({ value }: { value?: string }) => (
        <span dangerouslySetInnerHTML={{ __html: value ?? '' }} />
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useBlockEditingMode: () => 'default',
}));

(globalThis as { React?: unknown }).React = require('react');

import AudioEdit from '../edit';

describe('AudioEdit', () => {
    it('renders the MediaPlaceholder when no src is set', () => {
        const setAttributes = vi.fn();
        const { getByTestId, queryByTestId } = render(
            <AudioEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
        expect(queryByTestId('replace-flow')).toBeNull();
    });

    it('renders an audio element when src is set', () => {
        const setAttributes = vi.fn();
        const { container, getByTestId } = render(
            <AudioEdit
                attributes={{ src: 'https://example.com/song.mp3' }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        const audio = container.querySelector('audio');
        expect(audio).toBeTruthy();
        expect(audio?.getAttribute('src')).toBe('https://example.com/song.mp3');
        expect(getByTestId('replace-flow')).toBeTruthy();
    });
});
