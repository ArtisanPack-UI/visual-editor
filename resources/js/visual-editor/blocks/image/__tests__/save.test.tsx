/**
 * Tests for the `artisanpack/image` save component.
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
            const Tag = (tagName ?? 'figcaption') as keyof JSX.IntrinsicElements;
            return (
                <Tag
                    className={className}
                    dangerouslySetInnerHTML={{ __html: value ?? '' }}
                />
            );
        },
    }),
    __experimentalGetElementClassName: (name: string) => `wp-element-${name}`,
    __experimentalGetBorderClassesAndStyles: () => ({
        className: '',
        style: {},
    }),
    __experimentalGetShadowClassesAndStyles: () => ({
        className: '',
        style: {},
    }),
}));

import ImageSave from '../save';
import metadata from '../block.json';

describe('artisanpack/image block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/image');
        expect(metadata.category).toBe('media');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.url.selector).toBe('img');
        expect(metadata.attributes.url.attribute).toBe('src');
        expect(metadata.attributes.caption.selector).toBe('figcaption');
        expect(metadata.attributes.alt.attribute).toBe('alt');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('ImageSave', () => {
    it('renders a figure with an img element when url is set', () => {
        const html = renderToStaticMarkup(
            <ImageSave
                attributes={{ url: 'https://example.com/photo.jpg', alt: 'A photo' }}
            />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('src="https://example.com/photo.jpg"');
        expect(html).toContain('alt="A photo"');
    });

    it('renders an anchor wrapper when href is set', () => {
        const html = renderToStaticMarkup(
            <ImageSave
                attributes={{
                    url: 'https://example.com/photo.jpg',
                    href: 'https://example.com/page',
                    linkTarget: '_blank',
                    rel: 'noopener',
                }}
            />
        );
        expect(html).toContain('<a');
        expect(html).toContain('href="https://example.com/page"');
        expect(html).toContain('target="_blank"');
        expect(html).toContain('rel="noopener"');
    });

    it('renders a caption when set', () => {
        const html = renderToStaticMarkup(
            <ImageSave
                attributes={{
                    url: 'https://example.com/photo.jpg',
                    caption: 'Hello caption',
                }}
            />
        );
        expect(html).toContain('<figcaption');
        expect(html).toContain('Hello caption');
    });

    it('omits the caption when empty and no bindings are set', () => {
        const html = renderToStaticMarkup(
            <ImageSave
                attributes={{
                    url: 'https://example.com/photo.jpg',
                    caption: '',
                }}
            />
        );
        expect(html).not.toContain('<figcaption');
    });

    it('adds wp-image-{id} class when id is present', () => {
        const html = renderToStaticMarkup(
            <ImageSave
                attributes={{ url: 'https://example.com/photo.jpg', id: 42 }}
            />
        );
        expect(html).toContain('wp-image-42');
    });

    it('adds size-{slug} class when sizeSlug is present', () => {
        const html = renderToStaticMarkup(
            <ImageSave
                attributes={{
                    url: 'https://example.com/photo.jpg',
                    sizeSlug: 'large',
                }}
            />
        );
        expect(html).toContain('size-large');
    });
});
