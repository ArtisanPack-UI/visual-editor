/**
 * Locks the deprecation chain for `artisanpack/embed`.
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

describe('embed deprecation chain', () => {
    it('ships two deprecation entries matching upstream (v2 + v1)', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(2);
    });

    it('v2 returns null with no url', () => {
        const v2 = deprecated[0];
        const html = renderToStaticMarkup(v2.save({ attributes: {} }));
        expect(html).toBe('');
    });

    it('v2 renders a figure with wrapper div and url', () => {
        const v2 = deprecated[0];
        const html = renderToStaticMarkup(
            v2.save({
                attributes: {
                    url: 'https://example.com/post',
                    type: 'rich',
                    providerNameSlug: 'example',
                },
            })
        );
        expect(html).toContain('<figure');
        expect(html).toContain('wp-block-embed__wrapper');
        expect(html).toContain('is-type-rich');
        expect(html).toContain('is-provider-example');
    });

    it('v1 renders a figure without the wrapper div', () => {
        const v1 = deprecated[1];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    url: 'https://example.com/post',
                    type: 'rich',
                    providerNameSlug: 'example',
                },
            })
        );
        expect(html).toContain('<figure');
        expect(html).not.toContain('wp-block-embed__wrapper');
        expect(html).toContain('is-type-rich');
    });

    it('v1 returns null with no url', () => {
        const v1 = deprecated[1];
        const html = renderToStaticMarkup(
            v1.save({ attributes: { url: undefined } })
        );
        expect(html).toBe('');
    });
});
