/**
 * Tests for the `artisanpack/video` save component.
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
}));

import VideoSave from '../save';
import metadata from '../block.json';

describe('artisanpack/video block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/video');
        expect(metadata.category).toBe('media');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.src.selector).toBe('video');
        expect(metadata.attributes.caption.selector).toBe('figcaption');
        expect(metadata.attributes.autoplay.attribute).toBe('autoplay');
        expect(metadata.attributes.playsInline.attribute).toBe('playsinline');
        expect(metadata.attributes.controls.default).toBe(true);
        expect(metadata.attributes.preload.default).toBe('metadata');
        expect(metadata.attributes.tracks.type).toBe('array');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('VideoSave', () => {
    it('renders a figure with no video element when no src is set', () => {
        const html = renderToStaticMarkup(<VideoSave attributes={{}} />);
        expect(html).toContain('<figure');
        expect(html).not.toContain('<video');
    });

    it('renders a figure with a video element when src is set', () => {
        const html = renderToStaticMarkup(
            <VideoSave attributes={{ src: 'https://example.com/clip.mp4' }} />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<video');
        expect(html).toContain('src="https://example.com/clip.mp4"');
    });

    it('omits the preload attribute when set to the metadata default', () => {
        const html = renderToStaticMarkup(
            <VideoSave
                attributes={{
                    src: 'https://example.com/clip.mp4',
                    preload: 'metadata',
                }}
            />
        );
        expect(html).not.toContain('preload=');
    });

    it('serializes a non-default preload value', () => {
        const html = renderToStaticMarkup(
            <VideoSave
                attributes={{
                    src: 'https://example.com/clip.mp4',
                    preload: 'auto',
                }}
            />
        );
        expect(html).toContain('preload="auto"');
    });

    it('renders track elements for each entry in tracks', () => {
        const html = renderToStaticMarkup(
            <VideoSave
                attributes={{
                    src: 'https://example.com/clip.mp4',
                    tracks: [
                        {
                            id: 1,
                            src: 'https://example.com/en.vtt',
                            srcLang: 'en',
                            label: 'English',
                            kind: 'subtitles',
                        },
                        {
                            id: 2,
                            src: 'https://example.com/fr.vtt',
                            srcLang: 'fr',
                            label: 'French',
                            kind: 'subtitles',
                        },
                    ],
                }}
            />
        );
        expect(html).toContain('<track');
        expect(html).toContain('src="https://example.com/en.vtt"');
        expect(html).toContain('src="https://example.com/fr.vtt"');
    });

    it('renders a caption when set', () => {
        const html = renderToStaticMarkup(
            <VideoSave
                attributes={{
                    src: 'https://example.com/clip.mp4',
                    caption: 'Hello caption',
                }}
            />
        );
        expect(html).toContain('<figcaption');
        expect(html).toContain('Hello caption');
    });

    it('omits the caption when empty', () => {
        const html = renderToStaticMarkup(
            <VideoSave
                attributes={{
                    src: 'https://example.com/clip.mp4',
                    caption: '',
                }}
            />
        );
        expect(html).not.toContain('<figcaption');
    });
});
