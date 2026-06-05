/**
 * Tests for the `artisanpack/list` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    InnerBlocks: Object.assign(() => null, {
        Content: () => null,
    }),
}));

import ListSave from '../save';
import metadata from '../block.json';

describe('artisanpack/list block.json', () => {
    it('declares the artisanpack namespace', () => {
        expect(metadata.name).toBe('artisanpack/list');
        expect(metadata.allowedBlocks).toEqual(['artisanpack/list-item']);
    });
});

describe('ListSave', () => {
    it('renders a ul when ordered is false', () => {
        const html = renderToStaticMarkup(
            <ListSave attributes={{ ordered: false }} />
        );
        expect(html).toContain('<ul');
    });

    it('renders an ol when ordered is true', () => {
        const html = renderToStaticMarkup(
            <ListSave attributes={{ ordered: true }} />
        );
        expect(html).toContain('<ol');
    });

    it('passes reversed and start through on ordered lists', () => {
        const html = renderToStaticMarkup(
            <ListSave attributes={{ ordered: true, reversed: true, start: 5 }} />
        );
        expect(html).toContain('reversed');
        expect(html).toMatch(/start="?5/);
    });
});
