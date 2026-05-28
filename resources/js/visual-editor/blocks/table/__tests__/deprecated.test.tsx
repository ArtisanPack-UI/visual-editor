/**
 * Locks the deprecation chain for `artisanpack/table`.
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
            const Tag = (tagName ?? 'td') as keyof JSX.IntrinsicElements;
            return (
                <Tag dangerouslySetInnerHTML={{ __html: value ?? '' }} />
            );
        },
    }),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
    __experimentalGetBorderClassesAndStyles: () => ({ className: '', style: {} }),
    __experimentalGetColorClassesAndStyles: () => ({ className: '', style: {} }),
    __experimentalGetElementClassName: () => 'wp-element-caption',
}));

import deprecated from '../deprecated';

describe('table deprecation chain', () => {
    it('has 4 historical entries', () => {
        expect(deprecated).toHaveLength(4);
    });

    it('every entry exposes a save callable', () => {
        deprecated.forEach((entry, i) => {
            expect(
                typeof (entry as { save?: unknown }).save,
                `entry ${i}`
            ).toBe('function');
        });
    });

    it('v4 (entries[0]) returns null for empty tables', () => {
        const entry = deprecated[0] as {
            save: (p: { attributes: Record<string, unknown> }) => React.ReactElement | null;
        };
        const result = entry.save({
            attributes: { hasFixedLayout: true, head: [], body: [], foot: [] },
        });
        expect(result).toBeNull();
    });

    it('v2 (entries[2]) migrates legacy backgroundColor slug to style.color.background', () => {
        const entry = deprecated[2] as {
            migrate: (a: Record<string, unknown>) => Record<string, unknown>;
            isEligible: (a: Record<string, unknown>) => boolean;
        };
        expect(entry.isEligible({ backgroundColor: 'subtle-pale-green' })).toBe(true);
        const migrated = entry.migrate({ backgroundColor: 'subtle-pale-green' });
        expect((migrated.style as { color?: { background?: string } })?.color?.background).toBe(
            '#e9fbe5'
        );
    });
});
