/**
 * Tests for the `artisanpack/paragraph` save component.
 *
 * The save shape MUST stay byte-equivalent to upstream `core/paragraph` so
 * documents authored against the upstream block deserialize cleanly when
 * opened against the fork.
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
            value !== undefined && value !== '' ? (
                <span
                    data-testid="richtext-content"
                    dangerouslySetInnerHTML={{ __html: value }}
                />
            ) : null,
    }),
}));

vi.mock('@wordpress/i18n', () => ({
    isRTL: () => false,
    __: (text: string) => text,
}));

import ParagraphSave from '../save';
import metadata from '../block.json';

const baseAttrs = {
    content: 'Hello world',
    dropCap: false,
};

describe('artisanpack/paragraph block.json', () => {
    it('declares the artisanpack namespace and text category', () => {
        expect(metadata.name).toBe('artisanpack/paragraph');
        expect(metadata.category).toBe('text');
    });

    it('keeps the upstream content rich-text shape', () => {
        expect(metadata.attributes.content.type).toBe('rich-text');
        expect(metadata.attributes.content.source).toBe('rich-text');
        expect(metadata.attributes.content.selector).toBe('p');
    });

    it('inherits upstream supports (align/splitting/anchor)', () => {
        expect(metadata.supports.align).toEqual(['wide', 'full']);
        expect(metadata.supports.splitting).toBe(true);
        expect(metadata.supports.anchor).toBe(true);
        expect(metadata.supports.className).toBe(false);
    });
});

describe('ParagraphSave', () => {
    it('renders a <p> tag with the wp-block-paragraph class', () => {
        const html = renderToStaticMarkup(
            <ParagraphSave attributes={baseAttrs} />
        );
        expect(html).toContain('<p');
        expect(html).toContain('wp-block-paragraph');
        expect(html).toContain('Hello world');
    });

    it('adds has-drop-cap when dropCap is true and text is not center/right-aligned', () => {
        const html = renderToStaticMarkup(
            <ParagraphSave
                attributes={{
                    ...baseAttrs,
                    dropCap: true,
                    style: { typography: { textAlign: 'left' } },
                }}
            />
        );
        expect(html).toContain('has-drop-cap');
    });

    it('does NOT add has-drop-cap when text is centered (even if dropCap=true)', () => {
        const html = renderToStaticMarkup(
            <ParagraphSave
                attributes={{
                    ...baseAttrs,
                    dropCap: true,
                    style: { typography: { textAlign: 'center' } },
                }}
            />
        );
        expect(html).not.toContain('has-drop-cap');
    });

    it('does NOT add has-drop-cap when text is right-aligned (LTR runtime)', () => {
        const html = renderToStaticMarkup(
            <ParagraphSave
                attributes={{
                    ...baseAttrs,
                    dropCap: true,
                    style: { typography: { textAlign: 'right' } },
                }}
            />
        );
        expect(html).not.toContain('has-drop-cap');
    });

    it('forwards the direction attribute to dir=""', () => {
        const html = renderToStaticMarkup(
            <ParagraphSave
                attributes={{ ...baseAttrs, direction: 'rtl' }}
            />
        );
        expect(html).toContain('dir="rtl"');
    });

    it('renders empty content as <p> with no children', () => {
        const html = renderToStaticMarkup(
            <ParagraphSave attributes={{ ...baseAttrs, content: '' }} />
        );
        expect(html).toMatch(/<p[^>]*><\/p>/);
    });
});
