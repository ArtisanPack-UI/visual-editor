/**
 * Tests for the `artisanpack/details` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(
        () => null,
        {
            Content: ({ value }: { value?: string }) => <>{value ?? ''}</>,
        }
    ),
    InnerBlocks: Object.assign(
        () => null,
        {
            Content: () => null,
        }
    ),
}));

import DetailsSave from '../save';
import metadata from '../block.json';

describe('artisanpack/details block.json', () => {
    it('declares the artisanpack namespace and text category', () => {
        expect(metadata.name).toBe('artisanpack/details');
        expect(metadata.category).toBe('text');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.showContent.default).toBe(false);
        expect(metadata.attributes.summary.source).toBe('rich-text');
        expect(metadata.attributes.summary.selector).toBe('summary');
        expect(metadata.attributes.name.attribute).toBe('name');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('supports inner blocks via allowedBlocks', () => {
        expect(metadata.supports.allowedBlocks).toBe(true);
    });
});

describe('DetailsSave', () => {
    it('renders a <details> element', () => {
        const html = renderToStaticMarkup(
            <DetailsSave attributes={{ summary: 'Heading' }} />
        );
        expect(html).toContain('<details');
    });

    it('renders a <summary> with the summary content', () => {
        const html = renderToStaticMarkup(
            <DetailsSave attributes={{ summary: 'My Summary' }} />
        );
        expect(html).toContain('<summary');
        expect(html).toContain('My Summary');
    });

    it('falls back to "Details" when summary is empty', () => {
        const html = renderToStaticMarkup(
            <DetailsSave attributes={{}} />
        );
        expect(html).toContain('Details');
    });

    it('adds the wp-block-details class for renderer parity', () => {
        const html = renderToStaticMarkup(
            <DetailsSave attributes={{ summary: 'x' }} />
        );
        expect(html).toContain('wp-block-details');
    });

    it('reflects the showContent attribute as the open prop', () => {
        const html = renderToStaticMarkup(
            <DetailsSave attributes={{ summary: 'x', showContent: true }} />
        );
        expect(html).toContain('open');
    });

    it('emits the name attribute when provided', () => {
        const html = renderToStaticMarkup(
            <DetailsSave attributes={{ summary: 'x', name: 'accordion-1' }} />
        );
        expect(html).toContain('name="accordion-1"');
    });
});
