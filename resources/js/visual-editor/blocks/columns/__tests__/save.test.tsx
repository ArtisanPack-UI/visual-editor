/**
 * Tests for the `artisanpack/columns` save component + block.json.
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

import ColumnsSave from '../save';
import metadata from '../block.json';
import variations from '../variations';

describe('artisanpack/columns block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/columns');
        expect(metadata.category).toBe('design');
    });

    it('allowedBlocks targets the artisanpack/column fork', () => {
        expect(metadata.allowedBlocks).toEqual(['artisanpack/column']);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('keeps the upstream isStackedOnMobile default', () => {
        expect(metadata.attributes.isStackedOnMobile.default).toBe(true);
    });
});

describe('ColumnsSave', () => {
    it('renders a <div> wrapper', () => {
        const html = renderToStaticMarkup(
            <ColumnsSave
                attributes={{ isStackedOnMobile: true }}
            />
        );
        expect(html).toContain('<div');
    });

    it('adds the wp-block-columns class for renderer parity', () => {
        const html = renderToStaticMarkup(
            <ColumnsSave attributes={{ isStackedOnMobile: true }} />
        );
        expect(html).toContain('wp-block-columns');
    });

    it('emits is-not-stacked-on-mobile when isStackedOnMobile is false', () => {
        const html = renderToStaticMarkup(
            <ColumnsSave attributes={{ isStackedOnMobile: false }} />
        );
        expect(html).toContain('is-not-stacked-on-mobile');
    });

    it('emits are-vertically-aligned class when verticalAlignment is set', () => {
        const html = renderToStaticMarkup(
            <ColumnsSave
                attributes={{
                    isStackedOnMobile: true,
                    verticalAlignment: 'center',
                }}
            />
        );
        expect(html).toContain('are-vertically-aligned-center');
    });
});

describe('artisanpack/columns variations', () => {
    it('exports variations array with the upstream six layouts', () => {
        expect(Array.isArray(variations)).toBe(true);
        expect(variations).toHaveLength(6);
    });

    it('marks two-columns-equal as the default variation', () => {
        const defaultVar = variations.find((v) => v.isDefault);
        expect(defaultVar?.name).toBe('two-columns-equal');
    });

    it('every variation inner block targets artisanpack/column', () => {
        for (const variation of variations) {
            for (const innerBlock of variation.innerBlocks) {
                expect(innerBlock[0]).toBe('artisanpack/column');
            }
        }
    });
});
