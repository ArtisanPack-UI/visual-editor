/**
 * Tests for the `artisanpack/embed` save component.
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

import EmbedSave from '../save';
import metadata from '../block.json';

describe('artisanpack/embed block.json', () => {
    it('declares the artisanpack namespace and embed category', () => {
        expect(metadata.name).toBe('artisanpack/embed');
        expect(metadata.category).toBe('embed');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.url.type).toBe('string');
        expect(metadata.attributes.caption.selector).toBe('figcaption');
        expect(metadata.attributes.allowResponsive.default).toBe(true);
        expect(metadata.attributes.responsive.default).toBe(false);
        expect(metadata.attributes.previewable.default).toBe(true);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('EmbedSave', () => {
    it('renders null when no url is set', () => {
        const html = renderToStaticMarkup(<EmbedSave attributes={{}} />);
        expect(html).toBe('');
    });

    it('renders a figure with the URL on its own line when url is set', () => {
        const html = renderToStaticMarkup(
            <EmbedSave attributes={{ url: 'https://example.com/post' }} />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('wp-block-embed');
        expect(html).toContain('wp-block-embed__wrapper');
        expect(html).toContain('https://example.com/post');
    });

    it('applies provider and type classes', () => {
        const html = renderToStaticMarkup(
            <EmbedSave
                attributes={{
                    url: 'https://youtube.com/watch?v=abc',
                    type: 'video',
                    providerNameSlug: 'youtube',
                }}
            />
        );
        expect(html).toContain('is-type-video');
        expect(html).toContain('is-provider-youtube');
        expect(html).toContain('wp-block-embed-youtube');
    });

    it('renders a caption when set', () => {
        const html = renderToStaticMarkup(
            <EmbedSave
                attributes={{
                    url: 'https://example.com/post',
                    caption: 'Hello caption',
                }}
            />
        );
        expect(html).toContain('<figcaption');
        expect(html).toContain('Hello caption');
    });

    it('omits the caption when empty', () => {
        const html = renderToStaticMarkup(
            <EmbedSave
                attributes={{
                    url: 'https://example.com/post',
                    caption: '',
                }}
            />
        );
        expect(html).not.toContain('<figcaption');
    });
});
