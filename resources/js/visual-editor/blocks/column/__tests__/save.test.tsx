/**
 * Tests for the `artisanpack/column` save component + block.json.
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
        {
            save: (props?: Record<string, unknown>) => ({
                ...props,
                children: null,
            }),
        }
    ),
}));

import ColumnSave from '../save';
import metadata from '../block.json';

describe('artisanpack/column block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/column');
        expect(metadata.category).toBe('design');
    });

    it('parent is rewired to artisanpack/columns', () => {
        expect(metadata.parent).toEqual(['artisanpack/columns']);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });
});

describe('ColumnSave', () => {
    it('renders a <div> wrapper', () => {
        const html = renderToStaticMarkup(
            <ColumnSave attributes={{}} />
        );
        expect(html).toContain('<div');
    });

    it('adds the wp-block-column class for renderer parity', () => {
        const html = renderToStaticMarkup(
            <ColumnSave attributes={{}} />
        );
        expect(html).toContain('wp-block-column');
    });

    it('emits is-vertically-aligned class when verticalAlignment is set', () => {
        const html = renderToStaticMarkup(
            <ColumnSave attributes={{ verticalAlignment: 'top' }} />
        );
        expect(html).toContain('is-vertically-aligned-top');
    });

    it('serializes percent width as a flex-basis style', () => {
        const html = renderToStaticMarkup(
            <ColumnSave attributes={{ width: '33.33%' }} />
        );
        expect(html).toContain('flex-basis');
        expect(html).toContain('33.33%');
    });
});
