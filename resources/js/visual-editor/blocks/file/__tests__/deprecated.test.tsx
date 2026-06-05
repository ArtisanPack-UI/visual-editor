/**
 * Locks the deprecation chain for `artisanpack/file`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (format: string, ...args: unknown[]) => {
        let i = 0;
        return format.replace(/%s/g, () => String(args[i++] ?? ''));
    },
}));

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
        }: {
            value?: string;
            tagName?: string;
        }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return <Tag dangerouslySetInnerHTML={{ __html: value ?? '' }} />;
        },
    }),
    __experimentalGetElementClassName: (name: string) =>
        `wp-element-${name}`,
}));

import deprecated from '../deprecated';

describe('file deprecation chain', () => {
    it('ships three deprecation entries matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(3);
    });

    it('preserves the legacy html-source fileName/downloadButtonText schema across all entries', () => {
        for (const entry of deprecated) {
            expect(entry.attributes.fileName.source).toBe('html');
            expect(entry.attributes.fileName.selector).toBe('a:not([download])');
            expect(entry.attributes.downloadButtonText.source).toBe('html');
            expect(entry.attributes.downloadButtonText.selector).toBe(
                'a[download]'
            );
        }
    });

    it('v3 includes the fileId aria-describedby attribute', () => {
        const v3 = deprecated[0];
        expect(
            (v3.attributes as Record<string, unknown>).fileId
        ).toBeDefined();
    });

    it('v1 omits the fileId attribute (pre-PR#28062)', () => {
        const v1 = deprecated[2];
        expect(
            (v1.attributes as Record<string, unknown>).fileId
        ).toBeUndefined();
    });

    it('each entry renders the legacy markup with a download anchor', () => {
        for (const entry of deprecated) {
            const html = renderToStaticMarkup(
                entry.save({
                    attributes: {
                        href: 'https://example.com/doc.pdf',
                        fileName: 'doc',
                        textLinkHref: 'https://example.com/doc.pdf',
                        showDownloadButton: true,
                        downloadButtonText: 'Download',
                    },
                })
            );
            expect(html).toContain('<div');
            expect(html).toContain('download');
            expect(html).toContain('doc');
        }
    });
});
