/**
 * Locks the deprecation chain for `artisanpack/image`.
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
    __experimentalGetElementClassName: (name: string) => `wp-element-${name}`,
    __experimentalGetBorderClassesAndStyles: () => ({
        className: '',
        style: {},
    }),
}));

import deprecated from '../deprecated';

describe('image deprecation chain', () => {
    it('ships eight deprecation entries matching upstream (v8..v1)', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(8);
    });

    it('the newest entry (index 0 = v8) uses string width/height and supports behaviors', () => {
        const v8 = deprecated[0];
        expect(v8.attributes.width.type).toBe('string');
        expect(v8.attributes.height.type).toBe('string');
        expect(v8.attributes.behaviors.type).toBe('object');
        expect(typeof v8.isEligible).toBe('function');
        expect(v8.isEligible({ behaviors: { lightbox: { enabled: true } } })).toBe(true);
        expect(v8.isEligible({})).toBe(false);
    });

    it('the oldest entry (last) uses children-source caption (v1)', () => {
        const v1 = deprecated[deprecated.length - 1];
        expect(v1.attributes.caption.source).toBe('children');
        expect(v1.attributes.caption.selector).toBe('figcaption');
    });

    it('v8 save renders figure → img → optional figcaption', () => {
        const v8 = deprecated[0];
        const html = renderToStaticMarkup(
            v8.save({
                attributes: {
                    url: 'https://example.com/photo.jpg',
                    alt: 'legacy alt',
                    caption: 'legacy',
                    id: 99,
                    width: '500px',
                    height: '300px',
                },
            })
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<img');
        expect(html).toContain('wp-image-99');
        expect(html).toContain('<figcaption');
        expect(html).toContain('legacy');
    });

    it('v8 migrate hoists behaviors.lightbox.enabled onto attributes.lightbox', () => {
        const v8 = deprecated[0];
        const next = v8.migrate({
            url: 'https://example.com/photo.jpg',
            behaviors: { lightbox: { enabled: true } },
        });
        expect(next.lightbox).toEqual({ enabled: true });
        expect((next as { behaviors?: unknown }).behaviors).toBeUndefined();
    });

    it('v4 save wraps left-aligned images in a div', () => {
        const v4 = deprecated[4];
        const html = renderToStaticMarkup(
            v4.save({
                attributes: {
                    url: 'https://example.com/photo.jpg',
                    align: 'left',
                },
            })
        );
        expect(html).toContain('<div');
        expect(html).toContain('alignleft');
    });
});
