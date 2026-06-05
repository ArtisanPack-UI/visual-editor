/**
 * Tests for the `artisanpack/spacer` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    getSpacingPresetCssVar: (value?: string) => value,
}));

import SpacerSave from '../save';
import metadata from '../block.json';

describe('artisanpack/spacer block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/spacer');
        expect(metadata.category).toBe('design');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.height.default).toBe('100px');
        expect(metadata.attributes.height.type).toBe('string');
        expect(metadata.attributes.width.type).toBe('string');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('declares the orientation context', () => {
        expect(metadata.usesContext).toContain('orientation');
    });
});

describe('SpacerSave', () => {
    it('renders a <div> wrapper', () => {
        const html = renderToStaticMarkup(
            <SpacerSave attributes={{ height: '100px' }} />
        );
        expect(html).toContain('<div');
    });

    it('adds the wp-block-spacer class for renderer parity', () => {
        const html = renderToStaticMarkup(
            <SpacerSave attributes={{ height: '100px' }} />
        );
        expect(html).toContain('wp-block-spacer');
    });

    it('emits aria-hidden for assistive tech', () => {
        const html = renderToStaticMarkup(
            <SpacerSave attributes={{ height: '100px' }} />
        );
        expect(html).toContain('aria-hidden');
    });

    it('omits the height when selfStretch is fill', () => {
        const html = renderToStaticMarkup(
            <SpacerSave
                attributes={{
                    height: '100px',
                    style: { layout: { selfStretch: 'fill' } },
                }}
            />
        );
        // height should not appear inline because finalHeight is undefined
        expect(html).not.toMatch(/height:\s*100px/);
    });
});
