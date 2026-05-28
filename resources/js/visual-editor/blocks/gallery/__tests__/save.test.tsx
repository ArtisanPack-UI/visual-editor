/**
 * Tests for the `artisanpack/gallery` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props, children: null }),
        {
            save: (props?: Record<string, unknown>) => ({
                ...props,
                children: null,
            }),
        }
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
}));

import GallerySave from '../save';
import metadata from '../block.json';

describe('artisanpack/gallery block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/gallery');
        expect(metadata.category).toBe('media');
    });

    it('keeps the upstream nested-images schema', () => {
        expect(metadata.attributes.images.selector).toBe(
            '.blocks-gallery-item'
        );
        expect(metadata.attributes.caption.selector).toBe(
            '.blocks-gallery-caption'
        );
        expect(metadata.allowedBlocks).toEqual(['core/image']);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('GallerySave', () => {
    it('renders a figure with default classes when no columns/crop set', () => {
        const html = renderToStaticMarkup(
            <GallerySave attributes={{ imageCrop: true }} />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('has-nested-images');
        expect(html).toContain('columns-default');
        expect(html).toContain('is-cropped');
    });

    it('renders columns-N class when columns set', () => {
        const html = renderToStaticMarkup(
            <GallerySave attributes={{ columns: 4, imageCrop: false }} />
        );
        expect(html).toContain('columns-4');
        expect(html).not.toContain('columns-default');
        expect(html).not.toContain('is-cropped');
    });

    it('renders a caption when set', () => {
        const html = renderToStaticMarkup(
            <GallerySave
                attributes={{
                    imageCrop: true,
                    caption: 'Gallery caption',
                }}
            />
        );
        expect(html).toContain('<figcaption');
        expect(html).toContain('Gallery caption');
        expect(html).toContain('blocks-gallery-caption');
    });

    it('omits the caption when empty', () => {
        const html = renderToStaticMarkup(
            <GallerySave attributes={{ imageCrop: true, caption: '' }} />
        );
        expect(html).not.toContain('<figcaption');
    });
});
