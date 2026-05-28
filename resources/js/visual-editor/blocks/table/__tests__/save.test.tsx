/**
 * Tests for the `artisanpack/table` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        isEmpty: (v?: string) => !v || v.length === 0,
        Content: ({ value, tagName }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'td') as keyof JSX.IntrinsicElements;
            return (
                <Tag dangerouslySetInnerHTML={{ __html: value ?? '' }} />
            );
        },
    }),
    __experimentalGetBorderClassesAndStyles: () => ({ className: '', style: {} }),
    __experimentalGetColorClassesAndStyles: () => ({ className: '', style: {} }),
    __experimentalGetElementClassName: () => 'wp-element-caption',
}));

import TableSave from '../save';
import metadata from '../block.json';

describe('artisanpack/table block.json', () => {
    it('declares the artisanpack namespace', () => {
        expect(metadata.name).toBe('artisanpack/table');
        expect(metadata.category).toBe('text');
    });

    it('has thead/tbody/tfoot query attributes', () => {
        expect(metadata.attributes.head.selector).toBe('thead tr');
        expect(metadata.attributes.body.selector).toBe('tbody tr');
        expect(metadata.attributes.foot.selector).toBe('tfoot tr');
    });
});

describe('TableSave', () => {
    it('returns null for empty tables', () => {
        const html = renderToStaticMarkup(
            <TableSave
                attributes={{
                    hasFixedLayout: true,
                    head: [],
                    body: [],
                    foot: [],
                }}
            />
        );
        expect(html).toBe('');
    });

    it('renders a figure > table when body has rows', () => {
        const html = renderToStaticMarkup(
            <TableSave
                attributes={{
                    hasFixedLayout: true,
                    head: [],
                    body: [
                        { cells: [{ content: 'cell', tag: 'td' }] },
                    ],
                    foot: [],
                }}
            />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<table');
        expect(html).toContain('has-fixed-layout');
        expect(html).toContain('<td');
        expect(html).toContain('cell');
    });

    it('renders caption when present', () => {
        const html = renderToStaticMarkup(
            <TableSave
                attributes={{
                    hasFixedLayout: true,
                    caption: 'A caption',
                    head: [],
                    body: [{ cells: [{ content: 'c', tag: 'td' }] }],
                    foot: [],
                }}
            />
        );
        expect(html).toContain('<figcaption');
        expect(html).toContain('A caption');
    });
});
