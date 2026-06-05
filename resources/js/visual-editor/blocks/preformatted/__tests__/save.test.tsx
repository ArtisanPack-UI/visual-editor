/**
 * Tests for the `artisanpack/preformatted` save component.
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

import PreformattedSave from '../save';
import metadata from '../block.json';

describe('artisanpack/preformatted block.json', () => {
    it('declares the artisanpack namespace', () => {
        expect(metadata.name).toBe('artisanpack/preformatted');
        expect(metadata.category).toBe('text');
    });

    it('content attribute uses the pre selector with preserveWhiteSpace', () => {
        expect(metadata.attributes.content.selector).toBe('pre');
        expect(metadata.attributes.content.__unstablePreserveWhiteSpace).toBe(true);
    });
});

describe('PreformattedSave', () => {
    it('renders a pre tag', () => {
        const html = renderToStaticMarkup(
            <PreformattedSave attributes={{ content: 'hello\nworld' }} />
        );
        expect(html).toContain('<pre');
        expect(html).toContain('hello');
    });
});
