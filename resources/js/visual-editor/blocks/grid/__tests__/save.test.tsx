/**
 * Tests for the `artisanpack/grid` save component + deprecation (#747).
 *
 * The block hosts inner grid-items inside a wrapper `<div>`; save must
 * reproduce that wrapper (with the `ap-grid*` layout classes) so the
 * persisted markup round-trips. Legacy content that predates those
 * classes validates through the v1 deprecation, whose save reproduces
 * the old auto-class-only wrapper.
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

import GridSave from '../save';
import deprecated from '../deprecated';

describe('GridSave', () => {
    it('renders the ap-grid wrapper with the default column + fixed-layout classes', () => {
        const html = renderToStaticMarkup(<GridSave attributes={{ numColumns: 4 }} />);
        expect(html).toContain('<div');
        expect(html).toContain('ap-grid');
        expect(html).toContain('ap-grid-has-4-base-columns');
        expect(html).toContain('ap-grid-layout-fixed');
    });

    it('reflects the configured column count', () => {
        const html = renderToStaticMarkup(<GridSave attributes={{ numColumns: 3 }} />);
        expect(html).toContain('ap-grid-has-3-base-columns');
    });

    it('emits the masonry layout class + data-ap-cols hook when masonry', () => {
        const html = renderToStaticMarkup(
            <GridSave attributes={{ numColumns: 5, layoutMode: 'masonry' }} />
        );
        expect(html).toContain('ap-grid-layout-masonry');
        expect(html).toContain('data-ap-cols="5"');
    });
});

describe('Grid v1 deprecation', () => {
    it('reproduces the legacy auto-class-only wrapper (no ap-grid classes)', () => {
        const v1 = (deprecated as ReadonlyArray<{ save: () => JSX.Element }>)[0];
        const html = renderToStaticMarkup(v1.save());
        expect(html).toContain('<div');
        // The legacy markup carried no ap-grid* layout classes — only the
        // auto wrapper class (added by real Gutenberg, not this mock).
        expect(html).not.toContain('ap-grid-has-');
        expect(html).not.toContain('ap-grid-layout-');
    });
});
