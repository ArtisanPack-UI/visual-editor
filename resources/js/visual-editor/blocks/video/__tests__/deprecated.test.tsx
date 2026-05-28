/**
 * Locks the deprecation chain for `artisanpack/video`.
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
        }: {
            value?: string;
            tagName?: string;
        }) => {
            const Tag = (tagName ?? 'figcaption') as keyof JSX.IntrinsicElements;
            return (
                <Tag dangerouslySetInnerHTML={{ __html: value ?? '' }} />
            );
        },
    }),
}));

import deprecated from '../deprecated';

describe('video deprecation chain', () => {
    it('ships a single deprecation entry matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(1);
    });

    it('preserves the upstream attribute schema in v1', () => {
        const v1 = deprecated[0];
        expect(v1.attributes.src.selector).toBe('video');
        expect(v1.attributes.caption.selector).toBe('figcaption');
        expect(v1.attributes.playsInline.attribute).toBe('playsinline');
    });

    it('renders v1 markup with the legacy figcaption structure', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    src: 'https://example.com/clip.mp4',
                    caption: 'legacy',
                },
            })
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<video');
        expect(html).toContain('<figcaption');
        expect(html).toContain('legacy');
    });

    it('renders v1 markup without a figcaption when caption is empty', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    src: 'https://example.com/clip.mp4',
                    caption: '',
                },
            })
        );
        expect(html).not.toContain('<figcaption');
    });
});
