/**
 * Locks the deprecation chain for `artisanpack/pullquote`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        isEmpty: (v?: string) => !v || v.length === 0,
        Content: ({ value, tagName }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
}));

import deprecated from '../deprecated';

describe('pullquote deprecation chain', () => {
    it('has 6 historical entries', () => {
        expect(deprecated).toHaveLength(6);
    });

    it('every entry exposes a save callable', () => {
        deprecated.forEach((entry, i) => {
            expect(
                typeof (entry as { save?: unknown }).save,
                `entry ${i}`
            ).toBe('function');
        });
    });

    it('v5 (entries[0]) renders figure > blockquote with has-text-align-*', () => {
        const entry = deprecated[0] as {
            save: (p: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({
                attributes: { textAlign: 'center', value: 'x', citation: 'A' },
            })
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<blockquote');
        expect(html).toContain('has-text-align-center');
    });

    it('v0 (entries[5]) renders citation in a footer tag', () => {
        const entry = deprecated[5] as {
            save: (p: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({ attributes: { value: 'x', citation: 'A', align: 'none' } })
        );
        expect(html).toContain('<footer');
    });

    it('v5 migrate converts multiline value to inline <br>-joined HTML', () => {
        const entry = deprecated[0] as {
            migrate: (a: Record<string, unknown>) => Record<string, unknown>;
        };
        const migrated = entry.migrate({ value: '<p>a</p><p>b</p>' });
        expect(migrated.value).toBe('a<br>b');
    });
});
