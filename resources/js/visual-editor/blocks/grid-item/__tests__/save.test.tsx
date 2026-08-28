/**
 * Tests for the `artisanpack/grid-item` save component + deprecation (#747).
 *
 * The block hosts inner blocks inside a wrapper `<div>`; save must
 * reproduce that wrapper (with the `ap-grid-item*` layout/span classes)
 * so the persisted markup round-trips. Legacy content that predates
 * those classes validates through the v1 deprecation.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props, children: null }),
        { save: (props?: Record<string, unknown>) => ({ ...props, children: null }) }
    ),
}));

import GridItemSave from '../save';
import deprecated from '../deprecated';

describe('GridItemSave', () => {
    it('renders the ap-grid-item wrapper with layout + span classes', () => {
        const html = renderToStaticMarkup(
            <GridItemSave
                attributes={{ innerLayout: 'normal', gridColumnSpan: 2, gridRowSpan: 1 }}
            />
        );
        expect(html).toContain('<div');
        expect(html).toContain('ap-grid-item');
        expect(html).toContain('ap-grid-item-layout-normal');
        expect(html).toContain('ap-grid-item-span-2-base-columns');
        expect(html).toContain('ap-grid-item-span-1-base-row');
    });

    it('falls back to the normal layout for an unknown innerLayout', () => {
        const html = renderToStaticMarkup(
            <GridItemSave
                attributes={{
                    innerLayout: 'bogus' as never,
                    gridColumnSpan: 1,
                    gridRowSpan: 1,
                }}
            />
        );
        expect(html).toContain('ap-grid-item-layout-normal');
    });

    it('clamps an out-of-range span to the 12-column ceiling', () => {
        const html = renderToStaticMarkup(
            <GridItemSave
                attributes={{ innerLayout: 'normal', gridColumnSpan: 99, gridRowSpan: 1 }}
            />
        );
        expect(html).toContain('ap-grid-item-span-12-base-columns');
    });
});

describe('Grid Item v1 deprecation', () => {
    it('reproduces the legacy auto-class-only wrapper (no ap-grid-item classes)', () => {
        const v1 = (deprecated as ReadonlyArray<{ save: () => JSX.Element }>)[0];
        const html = renderToStaticMarkup(v1.save());
        expect(html).toContain('<div');
        expect(html).not.toContain('ap-grid-item-layout-');
        expect(html).not.toContain('ap-grid-item-span-');
    });
});
