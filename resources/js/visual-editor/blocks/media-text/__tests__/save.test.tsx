/**
 * Tests for the `artisanpack/media-text` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
}));

import MediaTextSave from '../save';
import metadata from '../block.json';

describe('artisanpack/media-text block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/media-text');
        expect(metadata.category).toBe('media');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.mediaUrl.selector).toBe(
            'figure video,figure img'
        );
        expect(metadata.attributes.mediaAlt.selector).toBe('figure img');
        expect(metadata.attributes.href.selector).toBe('figure a');
        expect(metadata.attributes.mediaWidth.default).toBe(50);
        expect(metadata.attributes.isStackedOnMobile.default).toBe(true);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('keeps usesContext for featured-image resolution', () => {
        expect(metadata.usesContext).toEqual(['postId', 'postType']);
    });
});

describe('MediaTextSave', () => {
    it('renders a figure + content layout (media on left by default)', () => {
        const html = renderToStaticMarkup(
            <MediaTextSave
                attributes={{
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    mediaAlt: 'A photo',
                }}
            />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<img');
        expect(html).toContain('src="https://example.com/pic.jpg"');
        expect(html).toContain('alt="A photo"');
        expect(html).toContain('wp-block-media-text__media');
        expect(html).toContain('wp-block-media-text__content');
    });

    it('puts media on the right when mediaPosition=right', () => {
        const html = renderToStaticMarkup(
            <MediaTextSave
                attributes={{
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    mediaPosition: 'right',
                }}
            />
        );
        // The content div precedes the figure when media is on the right.
        const contentIdx = html.indexOf('wp-block-media-text__content');
        const figureIdx = html.indexOf('<figure');
        expect(contentIdx).toBeGreaterThan(-1);
        expect(figureIdx).toBeGreaterThan(-1);
        expect(contentIdx).toBeLessThan(figureIdx);
    });

    it('renders a <video> for video mediaType', () => {
        const html = renderToStaticMarkup(
            <MediaTextSave
                attributes={{
                    mediaUrl: 'https://example.com/clip.mp4',
                    mediaType: 'video',
                }}
            />
        );
        expect(html).toContain('<video');
        expect(html).toContain('controls');
        expect(html).toContain('src="https://example.com/clip.mp4"');
    });

    it('wraps the image in an anchor when href is set', () => {
        const html = renderToStaticMarkup(
            <MediaTextSave
                attributes={{
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    href: 'https://example.com/post',
                    linkTarget: '_blank',
                    rel: 'noopener',
                    linkClass: 'my-link',
                }}
            />
        );
        expect(html).toContain('<a');
        expect(html).toContain('href="https://example.com/post"');
        expect(html).toContain('target="_blank"');
        expect(html).toContain('rel="noopener"');
        expect(html).toContain('class="my-link"');
    });

    it('adds wp-image-{id} and size-{slug} classes for known images', () => {
        const html = renderToStaticMarkup(
            <MediaTextSave
                attributes={{
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    mediaId: 42,
                    mediaSizeSlug: 'large',
                }}
            />
        );
        expect(html).toContain('wp-image-42');
        expect(html).toContain('size-large');
    });
});
