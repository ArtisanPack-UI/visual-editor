/**
 * Locks the deprecation chain for `artisanpack/gallery`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
}));

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
}));

import deprecated from '../deprecated';

describe('gallery deprecation chain', () => {
    it('ships seven deprecation entries (v1–v7) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(7);
    });

    it('v7 preserves the html-source caption schema', () => {
        const v7 = deprecated[0];
        expect(v7.attributes.caption.source).toBe('html');
        expect(v7.attributes.caption.selector).toBe('.blocks-gallery-caption');
    });

    it('v7 renders a nested-images figure with caption', () => {
        const v7 = deprecated[0];
        const html = renderToStaticMarkup(
            v7.save({
                attributes: {
                    caption: 'old caption',
                    columns: 3,
                    imageCrop: true,
                },
            })
        );
        expect(html).toContain('<figure');
        expect(html).toContain('has-nested-images');
        expect(html).toContain('columns-3');
        expect(html).toContain('is-cropped');
        expect(html).toContain('old caption');
    });

    it('v6 renders the legacy ul.blocks-gallery-grid structure', () => {
        const v6 = deprecated[1];
        const html = renderToStaticMarkup(
            v6.save({
                attributes: {
                    images: [
                        { url: 'a.png', alt: 'A', id: '1', caption: 'cap' },
                    ],
                    columns: 2,
                    imageCrop: true,
                    linkTo: 'none',
                },
            })
        );
        expect(html).toContain('blocks-gallery-grid');
        expect(html).toContain('blocks-gallery-item');
        expect(html).toContain('a.png');
        expect(html).toContain('cap');
    });

    it('v1 renders the legacy div.wp-block-gallery markup', () => {
        const v1 = deprecated[deprecated.length - 1];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    images: [{ url: 'x.png', alt: 'X', id: '99' }],
                    columns: 2,
                    align: 'none',
                    imageCrop: true,
                    linkTo: 'none',
                },
            })
        );
        expect(html).toContain('alignnone');
        expect(html).toContain('is-cropped');
        expect(html).toContain('blocks-gallery-image');
        expect(html).toContain('x.png');
    });

    it('v6 migrate() returns nested image blocks under the new attributes', () => {
        const v6 = deprecated[1] as {
            migrate?: (attrs: Record<string, unknown>) => unknown;
        };
        expect(typeof v6.migrate).toBe('function');
        const [nextAttrs, innerBlocks] = (v6.migrate as (
            attrs: Record<string, unknown>
        ) => [Record<string, unknown>, Array<{ name: string }>])({
            images: [{ url: 'one.png', alt: 'one', id: '1' }],
            ids: [1],
            linkTo: 'file',
            sizeSlug: 'large',
        });
        expect(nextAttrs.linkTo).toBe('media');
        expect(nextAttrs.allowResize).toBe(false);
        expect(innerBlocks).toHaveLength(1);
        expect(innerBlocks[0].name).toBe('core/image');
    });
});
