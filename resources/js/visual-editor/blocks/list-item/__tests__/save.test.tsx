/**
 * Tests for the `artisanpack/list-item` save component.
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
    RichText: Object.assign(() => null, {
        Content: ({ value }: { value?: string }) =>
            value !== undefined ? (
                <span dangerouslySetInnerHTML={{ __html: value }} />
            ) : null,
    }),
}));

import ListItemSave from '../save';
import metadata from '../block.json';

describe('artisanpack/list-item block.json', () => {
    it('parent is artisanpack/list', () => {
        expect(metadata.parent).toEqual(['artisanpack/list']);
    });

    it('allowedBlocks is artisanpack/list (for nesting)', () => {
        expect(metadata.allowedBlocks).toEqual(['artisanpack/list']);
    });
});

describe('ListItemSave', () => {
    it('renders an li with content', () => {
        const html = renderToStaticMarkup(
            <ListItemSave attributes={{ content: 'Item text' }} />
        );
        expect(html).toContain('<li');
        expect(html).toContain('Item text');
    });
});
