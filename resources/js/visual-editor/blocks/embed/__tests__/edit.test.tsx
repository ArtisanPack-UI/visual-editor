/**
 * Tests for the `artisanpack/embed` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
    sprintf: (fmt: string, ...args: unknown[]) => {
        let i = 0;
        return fmt.replace(/%s/g, () => String(args[i++]));
    },
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({ invalidateResolution: vi.fn() }),
    useSelect: (mapSelect: (sel: unknown) => unknown) => {
        return mapSelect(() => ({
            getEmbedPreview: () => undefined,
            isPreviewEmbedFallback: () => false,
            isRequestingEmbedPreview: () => false,
            getThemeSupports: () => ({ 'responsive-embeds': true }),
            hasFinishedResolution: () => true,
        }));
    },
}));

vi.mock('@wordpress/core-data', () => ({
    store: 'core-store',
}));

vi.mock('@wordpress/element', async () => {
    const actual = await vi.importActual<typeof import('react')>('react');
    return {
        useState: actual.useState,
        useEffect: actual.useEffect,
        useMemo: actual.useMemo,
        useRef: actual.useRef,
        renderToString: () => '',
    };
});

vi.mock('@wordpress/url', () => ({
    getAuthority: (url: string) => {
        try {
            return new URL(url).host;
        } catch {
            return '';
        }
    },
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    getBlockType: () => ({ name: 'artisanpack/embed' }),
    getBlockVariations: () => [],
}));

vi.mock('@wordpress/components', () => ({
    Spinner: () => <span data-testid="spinner" />,
    Placeholder: ({
        children,
        label,
    }: {
        children?: React.ReactNode;
        label?: string;
    }) => (
        <div data-testid="placeholder" data-label={label}>
            {children}
        </div>
    ),
    SandBox: () => <div data-testid="sandbox" />,
    Button: ({ children }: { children?: React.ReactNode }) => (
        <button>{children}</button>
    ),
    ExternalLink: ({ children }: { children?: React.ReactNode }) => (
        <a>{children}</a>
    ),
    ToolbarButton: () => null,
    ToolbarGroup: ({ children }: { children?: React.ReactNode }) => (
        <div>{children}</div>
    ),
    ToggleControl: () => null,
    __experimentalToolsPanel: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalToolsPanelItem: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalHStack: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalVStack: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalInputControl: () => <input />,
}));

vi.mock('@wordpress/icons', () => ({
    pencil: 'pencil-icon',
}));

vi.mock('@wordpress/primitives', () => ({
    SVG: ({ children }: { children?: React.ReactNode }) => (
        <svg>{children}</svg>
    ),
    Path: () => null,
    G: ({ children }: { children?: React.ReactNode }) => <g>{children}</g>,
}));

vi.mock('@wordpress/compose', () => ({
    useMergeRefs: () => () => undefined,
    useFocusableIframe: () => () => undefined,
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    BlockIcon: () => null,
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
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
}));

(globalThis as { React?: unknown }).React = require('react');

import EmbedEdit from '../edit';

describe('EmbedEdit', () => {
    it('renders the URL placeholder when no preview is available', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <EmbedEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
    });

    it('renders the placeholder for a URL with no resolvable preview', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <EmbedEdit
                attributes={{ url: 'https://example.com/post' }}
                setAttributes={setAttributes}
                isSelected
            />
        );
        // Without a real preview, edit.tsx falls through to the placeholder.
        expect(getByTestId('placeholder')).toBeTruthy();
    });
});
