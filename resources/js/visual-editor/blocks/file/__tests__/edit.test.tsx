/**
 * Tests for the `artisanpack/file` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({
    file: 'file-icon',
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({ createErrorNotice: vi.fn() }),
    useSelect: () => undefined,
}));

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/core-data', () => ({
    store: 'core-store',
}));

vi.mock('@wordpress/blob', () => ({
    isBlobURL: (url?: string) => !!url && url.startsWith('blob:'),
}));

vi.mock('@wordpress/url', () => ({
    getFilename: (url?: string) => (url ?? '').split('/').pop() ?? '',
}));

vi.mock('@wordpress/element', () => ({
    useEffect: () => undefined,
}));

vi.mock('@wordpress/components', () => ({
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
    RichText: Object.assign(
        ({ value, tagName }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return <Tag dangerouslySetInnerHTML={{ __html: value ?? '' }} />;
        },
        { isEmpty: (value?: string) => !value || value === '' }
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    __experimentalGetElementClassName: (name: string) =>
        `wp-element-${name}`,
}));

(globalThis as { React?: unknown }).React = require('react');

import FileEdit from '../edit';

describe('FileEdit', () => {
    it('renders the MediaPlaceholder when no href is set', () => {
        const setAttributes = vi.fn();
        const { getByTestId, queryByTestId } = render(
            <FileEdit
                attributes={{}}
                setAttributes={setAttributes}
                isSelected
                clientId="abc"
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
        expect(queryByTestId('replace-flow')).toBeNull();
    });

    it('renders file UI with replace-flow when href is set', () => {
        const setAttributes = vi.fn();
        const { container, getByTestId } = render(
            <FileEdit
                attributes={{
                    href: 'https://example.com/doc.pdf',
                    fileName: 'doc.pdf',
                    textLinkHref: 'https://example.com/doc.pdf',
                    showDownloadButton: true,
                    downloadButtonText: 'Download',
                }}
                setAttributes={setAttributes}
                isSelected
                clientId="abc"
            />
        );
        expect(getByTestId('replace-flow')).toBeTruthy();
        expect(getByTestId('inspector')).toBeTruthy();
        expect(
            container.querySelector('.wp-block-file__content-wrapper')
        ).toBeTruthy();
    });
});
