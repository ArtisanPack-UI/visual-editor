/**
 * Issue #593 — masonry layout option for post-template + grid blocks.
 *
 * Covers:
 *   - block.json shape: post-template adds `masonry` to its layout enum,
 *     grid adds `layoutMode` (`fixed` | `masonry`)
 *   - default behavior unchanged: post-template defaults to `list`,
 *     grid defaults to `layoutMode: fixed`
 *   - grid passes the layout mode down through `providesContext`
 */

import { describe, expect, it } from 'vitest';

import postTemplateMeta from '../post-template/block.json';
import gridMeta from '../grid/block.json';
import gridItemMeta from '../grid-item/block.json';

interface AttributeDefinition {
    type?: string;
    enum?: readonly string[];
    default?: unknown;
}

describe('artisanpack/post-template — masonry layout (#593)', () => {
    const attributes = postTemplateMeta.attributes as Record<string, AttributeDefinition>;

    it('exposes layout as a string enum that includes masonry', () => {
        expect(attributes.layout.type).toBe('string');
        expect(attributes.layout.enum).toEqual(['list', 'grid', 'masonry']);
    });

    it('defaults layout to `list` so existing posts keep their flow-layout rendering', () => {
        expect(attributes.layout.default).toBe('list');
    });
});

describe('artisanpack/grid — layoutMode attribute (#593)', () => {
    const attributes = gridMeta.attributes as Record<string, AttributeDefinition>;
    const providesContext = (gridMeta as { providesContext?: Record<string, string> })
        .providesContext ?? {};

    it('declares layoutMode as a `fixed` | `masonry` string enum', () => {
        expect(attributes.layoutMode.type).toBe('string');
        expect(attributes.layoutMode.enum).toEqual(['fixed', 'masonry']);
    });

    it('defaults layoutMode to `fixed` so existing grids keep their fixed-row rendering', () => {
        expect(attributes.layoutMode.default).toBe('fixed');
    });

    it('publishes the layoutMode value to grid-item children via block context', () => {
        expect(providesContext['artisanpack/gridLayoutMode']).toBe('layoutMode');
    });
});

describe('artisanpack/grid-item — masonry-aware row-span control (#593)', () => {
    it('subscribes to the parent grid layoutMode context so inspectors can disable row-span in masonry mode', () => {
        const usesContext = (gridItemMeta as { usesContext?: readonly string[] }).usesContext ?? [];
        expect(usesContext).toContain('artisanpack/gridLayoutMode');
    });
});
