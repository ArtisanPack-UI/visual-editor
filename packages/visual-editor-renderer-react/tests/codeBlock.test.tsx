/**
 * Code block language class and highlighting (#1044).
 */

import { render, waitFor } from '@testing-library/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { codeLanguageClass, needsHighlighter } from '../src/support/codeLanguage';
import { makeBlock } from './helpers';

const { highlightElement } = vi.hoisted(() => ({ highlightElement: vi.fn() }));

vi.mock('../src/support/codeHighlighter.generated.js', async (importOriginal) => {
    const actual = await importOriginal<{ highlightElement: (element: Element) => boolean }>();

    highlightElement.mockImplementation(actual.highlightElement);

    return { highlightElement };
});

function tree(attributes: Record<string, unknown>) {
    return [makeBlock('artisanpack/code', attributes)] as Parameters<typeof BlockTree>[0]['tree'];
}

describe('codeLanguageClass', () => {
    it('prints a class for a known language', () => {
        expect(codeLanguageClass('php')).toBe('language-php');
    });

    it.each([[undefined], [''], ['cobol'], ['PHP'], ['php" onclick="alert(1)'], [['php']]])(
        'prints nothing for %j',
        (value) => {
            expect(codeLanguageClass(value)).toBe('');
        }
    );

    it('does not load the highlighter for plain text', () => {
        expect(needsHighlighter('language-plaintext')).toBe(false);
        expect(needsHighlighter('')).toBe(false);
        expect(needsHighlighter('language-php')).toBe(true);
    });
});

describe('CodeBlock', () => {
    it('renders the same markup as before with no language', () => {
        const html = renderToStaticMarkup(<BlockTree tree={tree({ content: 'a' })} />);

        expect(html).toContain('<pre class="wp-block-code"><code>a</code></pre>');
    });

    it('server-renders the language class around plain, escaped code', () => {
        const html = renderToStaticMarkup(
            <BlockTree tree={tree({ content: '&lt;?php echo 1;', language: 'php' })} />
        );

        expect(html).toContain('<code class="language-php">&lt;?php echo 1;</code>');
    });

    it('highlights in the browser, and again when the content changes', async () => {
        const { container, rerender } = render(<BlockTree tree={tree({ content: 'const a = 1;', language: 'javascript' })} />);

        await waitFor(() => expect(container.querySelector('.ap-code-keyword')?.textContent).toBe('const'));

        rerender(<BlockTree tree={tree({ content: 'let b = 2;', language: 'javascript' })} />);

        await waitFor(() => expect(container.querySelector('.ap-code-keyword')?.textContent).toBe('let'));
    });

    it('never loads the highlighter without a language to highlight', async () => {
        highlightElement.mockClear();

        render(<BlockTree tree={tree({ content: 'a' })} />);
        render(<BlockTree tree={tree({ content: 'a', language: 'plaintext' })} />);
        render(<BlockTree tree={tree({ content: 'a', language: 'cobol' })} />);
        await new Promise((resolve) => setTimeout(resolve));

        expect(highlightElement).not.toHaveBeenCalled();
    });
});
