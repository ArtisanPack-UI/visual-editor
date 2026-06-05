/**
 * Tests for the `artisanpack/pullquote` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        isEmpty: (v?: string) => !v || v.length === 0,
        Content: ({ value, tagName }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
}));

import PullquoteSave from '../save';
import metadata from '../block.json';

describe('artisanpack/pullquote block.json', () => {
    it('declares the artisanpack namespace', () => {
        expect(metadata.name).toBe('artisanpack/pullquote');
        expect(metadata.category).toBe('text');
    });
});

describe('PullquoteSave', () => {
    it('renders a figure > blockquote > p with value', () => {
        const html = renderToStaticMarkup(
            <PullquoteSave attributes={{ value: 'Hi' }} />
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<blockquote');
        expect(html).toContain('<p');
    });

    it('renders citation when present', () => {
        const html = renderToStaticMarkup(
            <PullquoteSave attributes={{ value: 'Hi', citation: 'Author' }} />
        );
        expect(html).toContain('<cite');
    });

    it('adds has-text-align-* class when textAlign is set', () => {
        const html = renderToStaticMarkup(
            <PullquoteSave attributes={{ value: 'Hi', textAlign: 'right' }} />
        );
        expect(html).toContain('has-text-align-right');
    });
});
