/**
 * Tests for the `artisanpack/heading` save component.
 *
 * Save shape MUST stay byte-equivalent to upstream `core/heading`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        Content: ({ value }: { value?: string }) =>
            value !== undefined && value !== ''
                ? <span dangerouslySetInnerHTML={{ __html: value }} />
                : null,
    }),
}));

import HeadingSave from '../save';
import metadata from '../block.json';

describe('artisanpack/heading block.json', () => {
    it('declares the artisanpack namespace and text category', () => {
        expect(metadata.name).toBe('artisanpack/heading');
        expect(metadata.category).toBe('text');
    });

    it('keeps the upstream content rich-text shape', () => {
        expect(metadata.attributes.content.type).toBe('rich-text');
        expect(metadata.attributes.content.source).toBe('rich-text');
        expect(metadata.attributes.content.selector).toBe('h1,h2,h3,h4,h5,h6');
    });

    it('defaults level to 2', () => {
        expect(metadata.attributes.level.default).toBe(2);
    });
});

describe('HeadingSave', () => {
    it('renders an h2 by default', () => {
        const html = renderToStaticMarkup(
            <HeadingSave attributes={{ content: 'Hello', level: 2 }} />
        );
        expect(html).toContain('<h2');
        expect(html).toContain('Hello');
    });

    it('renders h1–h6 based on the level attribute', () => {
        for (const level of [1, 2, 3, 4, 5, 6]) {
            const html = renderToStaticMarkup(
                <HeadingSave attributes={{ content: 'x', level }} />
            );
            expect(html).toContain(`<h${level}`);
        }
    });

    it('renders empty content as a heading tag with no children', () => {
        const html = renderToStaticMarkup(
            <HeadingSave attributes={{ content: '', level: 3 }} />
        );
        expect(html).toMatch(/<h3[^>]*><\/h3>/);
    });
});
