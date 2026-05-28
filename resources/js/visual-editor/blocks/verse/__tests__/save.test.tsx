/**
 * Tests for the `artisanpack/verse` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        Content: ({ value }: { value?: string }) =>
            value !== undefined && value !== ''
                ? <span dangerouslySetInnerHTML={{ __html: value }} />
                : null,
    }),
}));

import VerseSave from '../save';
import metadata from '../block.json';

describe('artisanpack/verse block.json', () => {
    it('declares the artisanpack namespace', () => {
        expect(metadata.name).toBe('artisanpack/verse');
        expect(metadata.category).toBe('text');
    });
});

describe('VerseSave', () => {
    it('renders a pre tag with content', () => {
        const html = renderToStaticMarkup(
            <VerseSave attributes={{ content: 'Roses are red' }} />
        );
        expect(html).toContain('<pre');
        expect(html).toContain('Roses are red');
    });
});
