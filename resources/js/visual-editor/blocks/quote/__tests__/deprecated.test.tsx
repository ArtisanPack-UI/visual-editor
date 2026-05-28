/**
 * Locks the deprecation chain for `artisanpack/quote`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    InnerBlocks: Object.assign(() => null, {
        Content: () => <div data-testid="inner" />,
    }),
    RichText: Object.assign(() => null, {
        isEmpty: (v?: string) => !v || v.length === 0,
        Content: ({ value, tagName }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    parseWithAttributeSchema: () => [],
}));

import deprecated, { migrateToQuoteV2 } from '../deprecated';

describe('quote deprecation chain', () => {
    it('has 5 historical entries', () => {
        expect(deprecated).toHaveLength(5);
    });

    it('every entry exposes a save callable', () => {
        deprecated.forEach((entry, index) => {
            expect(
                typeof (entry as { save?: unknown }).save,
                `entry ${index}`
            ).toBe('function');
        });
    });

    it('v4 (entries[0]) renders blockquote with has-text-align-*', () => {
        const entry = deprecated[0] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({ attributes: { align: 'right', citation: 'Author' } })
        );
        expect(html).toContain('<blockquote');
        expect(html).toContain('has-text-align-right');
        expect(html).toContain('<cite');
    });

    it('v0 (entries[4]) renders citation in a footer tag', () => {
        const entry = deprecated[4] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({
                attributes: { value: 'q', citation: 'Author', style: 1 },
            })
        );
        expect(html).toContain('<footer');
    });

    it('migrateToQuoteV2 returns an attributes/innerBlocks tuple', () => {
        const result = migrateToQuoteV2({ value: '', citation: 'cite' });
        expect(Array.isArray(result)).toBe(true);
        expect(result).toHaveLength(2);
        expect((result[0] as { citation?: string }).citation).toBe('cite');
    });
});
