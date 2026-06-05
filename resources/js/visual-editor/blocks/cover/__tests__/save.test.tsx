/**
 * Tests for the `artisanpack/cover` save component.
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
    getColorClassName: (prefix: string, name?: string) =>
        name ? `has-${name}-${prefix}` : undefined,
    __experimentalGetGradientClass: (name?: string) =>
        name ? `has-${name}-gradient-background` : undefined,
}));

import CoverSave from '../save';
import metadata from '../block.json';

describe('artisanpack/cover block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/cover');
        expect(metadata.category).toBe('media');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('keeps the upstream attribute defaults', () => {
        expect(metadata.attributes.dimRatio.default).toBe(100);
        expect(metadata.attributes.tagName.default).toBe('div');
        expect(metadata.attributes.isDark.default).toBe(true);
    });
});

describe('CoverSave', () => {
    it('renders a div by default with the inner container', () => {
        const html = renderToStaticMarkup(
            <CoverSave attributes={{ dimRatio: 100 }} />
        );
        expect(html).toContain('wp-block-cover__inner-container');
        expect(html).toContain('wp-block-cover__background');
    });

    it('renders an img background when backgroundType=image and url is set', () => {
        const html = renderToStaticMarkup(
            <CoverSave
                attributes={{
                    backgroundType: 'image',
                    url: 'https://example.com/photo.jpg',
                    alt: 'cover alt',
                    dimRatio: 50,
                }}
            />
        );
        expect(html).toContain('<img');
        expect(html).toContain('src="https://example.com/photo.jpg"');
        expect(html).toContain('wp-block-cover__image-background');
    });

    it('renders a video background when backgroundType=video and url is set', () => {
        const html = renderToStaticMarkup(
            <CoverSave
                attributes={{
                    backgroundType: 'video',
                    url: 'https://example.com/clip.mp4',
                    dimRatio: 50,
                }}
            />
        );
        expect(html).toContain('<video');
        expect(html).toContain('src="https://example.com/clip.mp4"');
        expect(html).toContain('wp-block-cover__video-background');
    });

    it('honors the tagName attribute', () => {
        const html = renderToStaticMarkup(
            <CoverSave
                attributes={{ tagName: 'section', dimRatio: 100 }}
            />
        );
        expect(html).toMatch(/^<section/);
    });

    it('renders the embed-video figure for embed-video backgroundType', () => {
        const html = renderToStaticMarkup(
            <CoverSave
                attributes={{
                    backgroundType: 'embed-video',
                    url: 'https://youtu.be/abc',
                    dimRatio: 50,
                }}
            />
        );
        expect(html).toContain('wp-block-cover__embed-background');
        expect(html).toContain('wp-block-embed__wrapper');
    });
});
