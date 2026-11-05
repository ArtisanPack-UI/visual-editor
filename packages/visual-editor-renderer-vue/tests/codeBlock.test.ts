/**
 * Code block language class and highlighting (#1044).
 */

import { mount, flushPromises } from '@vue/test-utils';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it, vi } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { codeLanguageClass, needsHighlighter } from '../src/support/codeLanguage';
import { makeBlock } from './helpers';
import type { Block } from '../src/types';

const { highlightElement } = vi.hoisted(() => ({ highlightElement: vi.fn() }));

vi.mock('../src/support/codeHighlighter.generated.js', async (importOriginal) => {
    const actual = await importOriginal<{ highlightElement: (element: Element) => boolean }>();

    highlightElement.mockImplementation(actual.highlightElement);

    return { highlightElement };
});

function tree(attributes: Record<string, unknown>): Block[] {
    return [makeBlock('artisanpack/code', attributes)] as Block[];
}

const ssr = (attributes: Record<string, unknown>): Promise<string> =>
    renderToString(createSSRApp({ render: () => h(BlockTree, { tree: tree(attributes) }) }));

async function settle(): Promise<void> {
    await flushPromises();
    await vi.dynamicImportSettled();
    await flushPromises();
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
    it('renders the same markup as before with no language', async () => {
        expect(await ssr({ content: 'a' })).toContain('<pre class="wp-block-code"><code>a</code></pre>');
    });

    it('server-renders the language class around plain, escaped code', async () => {
        expect(await ssr({ content: '&lt;?php echo 1;', language: 'php' })).toContain(
            '<code class="language-php">&lt;?php echo 1;</code>'
        );
    });

    it('highlights in the browser, and again when the content changes', async () => {
        const wrapper = mount(BlockTree, { props: { tree: tree({ content: 'const a = 1;', language: 'javascript' }) } });

        await settle();
        expect(wrapper.find('.ap-code-keyword').text()).toBe('const');

        await wrapper.setProps({ tree: tree({ content: 'let b = 2;', language: 'javascript' }) });
        await settle();
        expect(wrapper.find('.ap-code-keyword').text()).toBe('let');
    });

    it('never loads the highlighter without a language to highlight', async () => {
        highlightElement.mockClear();

        mount(BlockTree, { props: { tree: tree({ content: 'a' }) } });
        mount(BlockTree, { props: { tree: tree({ content: 'a', language: 'plaintext' }) } });
        mount(BlockTree, { props: { tree: tree({ content: 'a', language: 'cobol' }) } });
        await settle();

        expect(highlightElement).not.toHaveBeenCalled();
    });
});
