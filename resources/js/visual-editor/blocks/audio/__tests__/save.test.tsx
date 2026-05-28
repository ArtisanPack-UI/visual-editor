/**
 * Tests for the `artisanpack/audio` save component.
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
    __experimentalGetElementClassName: (name: string) =>
        `wp-element-${name}`,
}));

import AudioSave from '../save';
import metadata from '../block.json';

describe('artisanpack/audio block.json', () => {
    it('declares the artisanpack namespace and media category', () => {
        expect(metadata.name).toBe('artisanpack/audio');
        expect(metadata.category).toBe('media');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.src.selector).toBe('audio');
        expect(metadata.attributes.caption.selector).toBe('figcaption');
        expect(metadata.attributes.autoplay.attribute).toBe('autoplay');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('AudioSave', () => {
    it('renders null when no src is set', () => {
        const html = renderToStaticMarkup(<AudioSave attributes={{}} />);
        expect(html).toBe('');
    });

    it('renders a figure with an audio element when src is set', () => {
        const html = renderToStaticMarkup(
            <AudioSave attributes={{ src: 'https://example.com/song.mp3' }} />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('src="https://example.com/song.mp3"');
        expect(html).toMatch(/controls(=""|=)/);
    });

    it('renders a caption when set', () => {
        const html = renderToStaticMarkup(
            <AudioSave
                attributes={{
                    src: 'https://example.com/song.mp3',
                    caption: 'Hello caption',
                }}
            />
        );
        expect(html).toContain('<figcaption');
        expect(html).toContain('Hello caption');
    });

    it('omits the caption when empty', () => {
        const html = renderToStaticMarkup(
            <AudioSave
                attributes={{
                    src: 'https://example.com/song.mp3',
                    caption: '',
                }}
            />
        );
        expect(html).not.toContain('<figcaption');
    });
});
