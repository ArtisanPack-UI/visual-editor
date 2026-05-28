/**
 * Tests for the `artisanpack/file` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        isEmpty: (value?: string) => !value || value === '',
        Content: ({
            value,
            tagName,
            className,
        }: {
            value?: string;
            tagName?: string;
            className?: string;
        }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return (
                <Tag
                    className={className}
                    dangerouslySetInnerHTML={{ __html: value ?? '' }}
                />
            );
        },
    }),
    __experimentalGetElementClassName: (name: string) =>
        `wp-element-${name}`,
}));

import FileSave from '../save';
import metadata from '../block.json';

describe('artisanpack/file block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/file');
        expect(metadata.category).toBe('media');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.href.role).toBe('content');
        expect(metadata.attributes.fileName.selector).toBe('a:not([download])');
        expect(metadata.attributes.downloadButtonText.selector).toBe(
            'a[download]'
        );
        expect(metadata.attributes.showDownloadButton.default).toBe(true);
        expect(metadata.attributes.previewHeight.default).toBe(600);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('FileSave', () => {
    it('renders null when no href is set', () => {
        const html = renderToStaticMarkup(<FileSave attributes={{}} />);
        expect(html).toBe('');
    });

    it('renders a div with a download button when href is set', () => {
        const html = renderToStaticMarkup(
            <FileSave
                attributes={{
                    href: 'https://example.com/doc.pdf',
                    showDownloadButton: true,
                    downloadButtonText: 'Download',
                }}
            />
        );
        expect(html).toContain('<div');
        expect(html).toContain('href="https://example.com/doc.pdf"');
        expect(html).toContain('download');
    });

    it('renders the filename anchor when fileName is set', () => {
        const html = renderToStaticMarkup(
            <FileSave
                attributes={{
                    href: 'https://example.com/doc.pdf',
                    fileName: 'Spec.pdf',
                    textLinkHref: 'https://example.com/doc.pdf',
                }}
            />
        );
        expect(html).toContain('Spec.pdf');
    });

    it('renders a PDF embed object when displayPreview is true', () => {
        const html = renderToStaticMarkup(
            <FileSave
                attributes={{
                    href: 'https://example.com/doc.pdf',
                    displayPreview: true,
                    previewHeight: 800,
                }}
            />
        );
        expect(html).toContain('<object');
        expect(html).toContain('type="application/pdf"');
        expect(html).toContain('800px');
    });

    it('omits the download button when showDownloadButton is false', () => {
        const html = renderToStaticMarkup(
            <FileSave
                attributes={{
                    href: 'https://example.com/doc.pdf',
                    showDownloadButton: false,
                }}
            />
        );
        expect(html).not.toContain('download=""');
        expect(html).not.toContain('wp-block-file__button');
    });
});
