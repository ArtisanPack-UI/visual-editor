/**
 * Tests for the `artisanpack/quote` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    InnerBlocks: Object.assign(() => null, {
        Content: () => <div data-testid="inner-blocks" />,
    }),
    RichText: Object.assign(() => null, {
        isEmpty: (value?: string) => !value || value.length === 0,
        Content: ({
            value,
            tagName,
        }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
}));

import QuoteSave from '../save';
import metadata from '../block.json';

describe('artisanpack/quote block.json', () => {
    it('declares the artisanpack namespace and text category', () => {
        expect(metadata.name).toBe('artisanpack/quote');
        expect(metadata.category).toBe('text');
    });

    it('keeps the upstream content attribute selectors', () => {
        expect(metadata.attributes.value.selector).toBe('blockquote');
        expect(metadata.attributes.citation.selector).toBe('cite');
    });
});

describe('QuoteSave', () => {
    it('renders a blockquote element', () => {
        const html = renderToStaticMarkup(<QuoteSave attributes={{}} />);
        expect(html).toContain('<blockquote');
    });

    it('adds has-text-align-* class when textAlign is set', () => {
        const html = renderToStaticMarkup(
            <QuoteSave attributes={{ textAlign: 'right' }} />
        );
        expect(html).toContain('has-text-align-right');
    });

    it('renders citation in a cite tag when present', () => {
        const html = renderToStaticMarkup(
            <QuoteSave attributes={{ citation: 'Author Name' }} />
        );
        expect(html).toContain('<cite');
        expect(html).toContain('Author Name');
    });

    it('omits cite tag when citation is empty', () => {
        const html = renderToStaticMarkup(
            <QuoteSave attributes={{ citation: '' }} />
        );
        expect(html).not.toContain('<cite');
    });
});
