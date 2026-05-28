/**
 * Tests for the `artisanpack/buttons` save component.
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

import ButtonsSave from '../save';
import metadata from '../block.json';

describe('artisanpack/buttons block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/buttons');
        expect(metadata.category).toBe('design');
    });

    it('restricts allowedBlocks to the forked button child', () => {
        expect(metadata.allowedBlocks).toEqual(['artisanpack/button']);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('defaults to a flex layout', () => {
        expect(metadata.supports.layout.default.type).toBe('flex');
    });
});

describe('ButtonsSave', () => {
    it('renders a <div> wrapper', () => {
        const html = renderToStaticMarkup(
            <ButtonsSave attributes={{}} />
        );
        expect(html).toContain('<div');
    });

    it('adds the wp-block-buttons class for renderer parity', () => {
        const html = renderToStaticMarkup(
            <ButtonsSave attributes={{}} />
        );
        expect(html).toContain('wp-block-buttons');
    });

    it('emits has-custom-font-size when a font size is set', () => {
        const html = renderToStaticMarkup(
            <ButtonsSave attributes={{ fontSize: 'large' }} />
        );
        expect(html).toContain('has-custom-font-size');
    });
});
