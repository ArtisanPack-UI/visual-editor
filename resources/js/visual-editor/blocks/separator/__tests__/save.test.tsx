/**
 * Tests for the `artisanpack/separator` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
    __experimentalGetColorClassesAndStyles: () => ({
        className: undefined,
        style: {},
    }),
}));

import SeparatorSave from '../save';
import metadata from '../block.json';

describe('artisanpack/separator block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/separator');
        expect(metadata.category).toBe('design');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.opacity.default).toBe('alpha-channel');
        expect(metadata.attributes.tagName.default).toBe('hr');
        expect(metadata.attributes.tagName.enum).toEqual(['hr', 'div']);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('declares three styles including dots and wide', () => {
        expect(metadata.styles).toHaveLength(3);
        const names = metadata.styles.map((s) => s.name);
        expect(names).toContain('default');
        expect(names).toContain('wide');
        expect(names).toContain('dots');
    });
});

describe('SeparatorSave', () => {
    it('renders an <hr> by default', () => {
        const html = renderToStaticMarkup(
            <SeparatorSave attributes={{ opacity: 'alpha-channel' }} />
        );
        expect(html).toContain('<hr');
    });

    it('renders a <div> when tagName is div', () => {
        const html = renderToStaticMarkup(
            <SeparatorSave
                attributes={{ tagName: 'div', opacity: 'alpha-channel' }}
            />
        );
        expect(html).toContain('<div');
    });

    it('adds the wp-block-separator class for renderer parity', () => {
        const html = renderToStaticMarkup(
            <SeparatorSave attributes={{ opacity: 'alpha-channel' }} />
        );
        expect(html).toContain('wp-block-separator');
    });

    it('emits the has-alpha-channel-opacity class for the default opacity', () => {
        const html = renderToStaticMarkup(
            <SeparatorSave attributes={{ opacity: 'alpha-channel' }} />
        );
        expect(html).toContain('has-alpha-channel-opacity');
    });

    it('emits the has-css-opacity class for legacy opacity', () => {
        const html = renderToStaticMarkup(
            <SeparatorSave attributes={{ opacity: 'css' }} />
        );
        expect(html).toContain('has-css-opacity');
    });
});
