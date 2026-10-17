/**
 * Locks the deprecation chain for `artisanpack/cover`.
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
            const Tag = (tagName ?? 'p') as keyof JSX.IntrinsicElements;
            return (
                <Tag
                    className={className}
                    dangerouslySetInnerHTML={{ __html: value ?? '' }}
                />
            );
        },
    }),
    InnerBlocks: Object.assign(() => null, {
        Content: () => null,
    }),
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
    }),
}));

vi.mock('@wordpress/compose', () => ({
    compose:
        (...fns: ((value: unknown) => unknown)[]) =>
        (value: unknown) =>
            fns.reduceRight((acc, fn) => fn(acc), value),
}));

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

import deprecated from '../deprecated';

describe('cover deprecation chain', () => {
    it('ships the full 14-entry chain (v14..v1) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(14);
    });

    it('renders v14 markup with the modern overlay-first structure', () => {
        const v14 = deprecated[0];
        const html = renderToStaticMarkup(
            v14.save({
                attributes: {
                    backgroundType: 'image',
                    url: 'https://example.com/img.jpg',
                    dimRatio: 50,
                    tagName: 'div',
                },
            })
        );
        expect(html).toContain('wp-block-cover__background');
        expect(html).toContain('wp-block-cover__image-background');
    });

    it('renders v3 legacy title markup with a <p class="wp-block-cover-text">', () => {
        const v3 = deprecated.find(
            (entry) =>
                (entry.attributes as { title?: { selector?: string } })?.title
                    ?.selector === 'p'
        );
        expect(v3).toBeTruthy();
        const html = renderToStaticMarkup(
            v3!.save({
                attributes: {
                    title: 'Hello',
                    dimRatio: 50,
                    backgroundType: 'image',
                    url: 'https://example.com/img.jpg',
                },
            })
        );
        expect(html).toContain('wp-block-cover-text');
    });

    it('renders v1 legacy <section>/<h2> markup', () => {
        const v1 = deprecated[deprecated.length - 1];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    title: 'Headline',
                    url: 'https://example.com/img.jpg',
                    dimRatio: 50,
                },
            })
        );
        expect(html).toContain('<section');
        expect(html).toContain('wp-block-cover-image');
        expect(html).toContain('<h2');
    });

    it('v12 isEligible returns true when overlay colors set without isUserOverlayColor', () => {
        const v12 = deprecated[2];
        expect(
            v12.isEligible({
                customOverlayColor: '#abcdef',
                isUserOverlayColor: undefined,
            })
        ).toBe(true);
        expect(
            v12.isEligible({
                customOverlayColor: '#abcdef',
                isUserOverlayColor: true,
            })
        ).toBe(false);
    });
});
