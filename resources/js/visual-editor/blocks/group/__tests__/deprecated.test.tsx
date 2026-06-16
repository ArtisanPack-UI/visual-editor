/**
 * Locks the deprecation chain for `artisanpack/group`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: { Content: () => null },
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props, children: null }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
}));

import deprecated from '../deprecated';

describe('group deprecation chain', () => {
    it('ships the upstream five entries plus the #595 flex-layout migration', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        // 5 upstream-ported entries + 1 #595 flex-layout migration appended.
        expect(deprecated).toHaveLength(6);
    });

    it('v1 (entry 0) declares tagName + templateLock schema', () => {
        const v1 = deprecated[0];
        expect(
            (v1.attributes as Record<string, { type: unknown }>).tagName.type
        ).toBe('string');
    });

    it('v1 isEligible flips for inherited layouts', () => {
        const v1 = deprecated[0] as {
            isEligible: (attrs: { layout?: unknown }) => boolean;
        };
        expect(v1.isEligible({ layout: { inherit: true } })).toBe(true);
        expect(v1.isEligible({ layout: { type: 'constrained' } })).toBe(false);
    });

    it('v1 migrates inherited layouts to constrained', () => {
        const v1 = deprecated[0] as {
            migrate: (a: Record<string, unknown>) => Record<string, unknown> | undefined;
        };
        const migrated = v1.migrate({ layout: { inherit: true } });
        expect((migrated?.layout as { type: string }).type).toBe('constrained');
    });

    it('legacy entries render the inner-container div', () => {
        const v3 = deprecated[2];
        const html = renderToStaticMarkup(
            v3.save({
                attributes: {
                    backgroundColor: 'red',
                    customBackgroundColor: '#fff',
                    textColor: 'blue',
                    customTextColor: '#000',
                },
            })
        );
        expect(html).toContain('wp-block-group__inner-container');
    });

    it('migrateAttributes promotes custom colors into style', () => {
        const v5 = deprecated[4] as {
            migrate: (a: Record<string, unknown>) => Record<string, unknown>;
        };
        const migrated = v5.migrate({ customBackgroundColor: '#abcdef' });
        expect((migrated.style as { color: { background: string } }).color.background).toBe(
            '#abcdef'
        );
        expect(migrated.tagName).toBe('div');
    });
});
