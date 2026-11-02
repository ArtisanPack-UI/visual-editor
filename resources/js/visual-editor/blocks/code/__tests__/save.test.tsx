/**
 * Tests for the `artisanpack/code` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        Content: ({
            value,
            tagName,
            className,
        }: { value?: string; tagName?: string; className?: string }) => {
            const Tag = (tagName ?? 'code') as keyof JSX.IntrinsicElements;
            return (
                <Tag className={className} dangerouslySetInnerHTML={{ __html: value ?? '' }} />
            );
        },
    }),
}));

import CodeSave from '../save';
import metadata from '../block.json';
import { escape } from '../utils';

describe('artisanpack/code block.json', () => {
    it('declares the artisanpack namespace and text category', () => {
        expect(metadata.name).toBe('artisanpack/code');
        expect(metadata.category).toBe('text');
    });

    it('content attribute uses the code selector and preserveWhiteSpace flag', () => {
        expect(metadata.attributes.content.selector).toBe('code');
        expect(metadata.attributes.content.__unstablePreserveWhiteSpace).toBe(true);
    });
});

describe('language attribute', () => {
    it('has no default, so existing blocks serialize as before', () => {
        expect(metadata.attributes.language).not.toHaveProperty('default');
    });

    it('offers plain text and Blade alongside the older manifest list', () => {
        expect(metadata.attributes.language.enum).toContain('plaintext');
        expect(metadata.attributes.language.enum).toContain('blade');
    });
});

describe('escape helper', () => {
    it('escapes opening square brackets', () => {
        expect(escape('[embed]')).toBe('&#91;embed]');
    });

    it('escapes isolated URL protocol slashes', () => {
        expect(escape('https://example.com/x')).toBe('https:&#47;&#47;example.com/x');
    });

    it('leaves arbitrary text alone', () => {
        expect(escape('hello world')).toBe('hello world');
    });
});

describe('CodeSave', () => {
    it('renders a pre wrapping a code tag', () => {
        const html = renderToStaticMarkup(
            <CodeSave attributes={{ content: 'console.log(1)' }} />
        );
        expect(html).toContain('<pre');
        expect(html).toContain('<code');
        expect(html).toContain('console.log(1)');
    });

    it('escapes brackets in saved markup', () => {
        // dangerouslySetInnerHTML passes characters through verbatim; the &
        // ends up as `&amp;` when serialized by renderToStaticMarkup, so the
        // expected literal in the output is `&amp;#91;`.
        const html = renderToStaticMarkup(
            <CodeSave attributes={{ content: '[shortcode]' }} />
        );
        expect(html).toContain('#91;shortcode');
    });

    it('saves a bare code element when no language is set', () => {
        const html = renderToStaticMarkup(
            <CodeSave attributes={{ content: 'a' }} />
        );
        expect(html).toBe('<pre><code>a</code></pre>');
    });

    it('prints the language class on the code element', () => {
        const html = renderToStaticMarkup(
            <CodeSave attributes={{ content: 'a', language: 'php' }} />
        );
        expect(html).toBe('<pre><code class="language-php">a</code></pre>');
    });
});
