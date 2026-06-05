/**
 * Tests for the `artisanpack/group` save component.
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
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
}));

import GroupSave from '../save';
import metadata from '../block.json';

describe('artisanpack/group block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/group');
        expect(metadata.category).toBe('design');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('defaults tagName to div', () => {
        expect(metadata.attributes.tagName.default).toBe('div');
    });

    it('declares wp-block-group styles slugs', () => {
        expect(metadata.style).toBe('wp-block-group');
        expect(metadata.editorStyle).toBe('wp-block-group-editor');
    });
});

describe('GroupSave', () => {
    it('renders a <div> by default', () => {
        const html = renderToStaticMarkup(<GroupSave attributes={{}} />);
        expect(html).toContain('<div');
    });

    it('renders the configured tagName', () => {
        const html = renderToStaticMarkup(
            <GroupSave attributes={{ tagName: 'section' }} />
        );
        expect(html).toContain('<section');
    });

    it('adds the wp-block-group class for renderer parity', () => {
        const html = renderToStaticMarkup(<GroupSave attributes={{}} />);
        expect(html).toContain('wp-block-group');
    });
});
