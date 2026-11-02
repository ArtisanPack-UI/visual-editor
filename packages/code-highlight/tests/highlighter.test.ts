/**
 * Shared Code block highlighter (#1044). Exercises the bundle the renderers
 * ship, not the source, so it also covers what the build script assembles.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import { highlightElement } from '../../visual-editor-renderer-react/src/support/codeHighlighter.generated.js';

const root = resolve(__dirname, '../../..');
const read = (path: string): string => readFileSync(resolve(root, path), 'utf8');

function codeElement(language: string, html: string): HTMLElement {
    const code = document.createElement('code');
    code.className = `language-${language}`;
    code.innerHTML = html;
    document.body.appendChild(code);

    return code;
}

type Rgb = [number, number, number];

const toLinear = (hex: string): Rgb =>
    [1, 3, 5]
        .map((start) => parseInt(hex.slice(start, start + 2), 16) / 255)
        .map((value) => (value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)) as Rgb;

/** Luminance contrast per WCAG 2.x. */
function contrast(a: Rgb, b: Rgb): number {
    const luminance = ([r, g, b]: Rgb): number => 0.2126 * r + 0.7152 * g + 0.0722 * b;
    const [lighter, darker] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (lighter + 0.05) / (darker + 0.05);
}

/** jsdom has no canvas, so paint with a stand-in that reads hex and `rgb()`. */
function stubCanvas(): void {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(() => {
        let fill = 'rgba(0, 0, 0, 0)';

        return {
            clearRect: () => undefined,
            fillRect: () => undefined,
            set fillStyle(value: string) {
                fill = value;
            },
            getImageData: () => {
                const parts = (fill.match(/[\d.]+/g) ?? []).map(Number);

                return { data: [parts[0] ?? 0, parts[1] ?? 0, parts[2] ?? 0, parts.length > 3 ? parts[3] * 255 : 255] };
            },
        } as unknown as CanvasRenderingContext2D;
    });
}

beforeAll(stubCanvas);

beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
});

describe('highlightElement', () => {
    it.each([
        ['html', '&lt;div class="a"&gt;&lt;/div&gt;', 'ap-code-tag'],
        ['css', '.a { color: red; }', 'ap-code-selector'],
        ['javascript', 'const a = 1;', 'ap-code-keyword'],
        ['typescript', 'interface A { b: string }', 'ap-code-keyword'],
        ['php', '&lt;?php echo 1;', 'ap-code-keyword'],
        ['python', 'def a():\n    return 1', 'ap-code-keyword'],
        ['ruby', 'def a\nend', 'ap-code-keyword'],
        ['go', 'func main() {}', 'ap-code-keyword'],
        ['rust', 'fn main() {}', 'ap-code-keyword'],
        ['json', '{"a": 1}', 'ap-code-property'],
        ['yaml', 'a: 1', 'ap-code-atrule'],
        ['bash', 'echo "a"', 'ap-code-builtin'],
        ['sql', 'SELECT 1;', 'ap-code-keyword'],
        ['markdown', '# Title', 'ap-code-title'],
        ['blade', '@if ($a)\n&lt;p&gt;{{ $a }}&lt;/p&gt;\n@endif', 'ap-code-directive'],
    ])('tokenizes %s', (language, source, tokenClass) => {
        const code = codeElement(language, source);
        const text = code.textContent;

        expect(highlightElement(code)).toBe(true);
        expect(code.querySelector(`.${tokenClass}`)).not.toBeNull();
        expect(code.textContent).toBe(text);
    });

    it('reads Blade echoes as PHP, and leaves the HTML around them to the markup grammar', () => {
        const code = codeElement('blade', '&lt;p class="a"&gt;{{ strtoupper($b) }}&lt;/p&gt;{{-- note --}}');

        highlightElement(code);

        expect(code.querySelector('.ap-code-tag')?.textContent).toContain('p');
        expect(code.querySelector('.ap-code-function')?.textContent).toBe('strtoupper');
        expect(code.querySelector('.ap-code-comment')?.textContent).toBe('{{-- note --}}');
    });

    it('skips Blade escapes and email addresses, and stays fast on an unbalanced directive', () => {
        const code = codeElement('blade', '@@if me@example.com @endif');

        highlightElement(code);

        expect(Array.from(code.querySelectorAll('.ap-code-directive')).map((token) => token.textContent)).toEqual(['@endif']);

        const unbalanced = codeElement('blade', `@if${'(a'.repeat(20000)}`);
        const start = performance.now();

        highlightElement(unbalanced);

        expect(performance.now() - start).toBeLessThan(1000);
    });

    it('leaves plain text, unknown languages and Prism API names untouched', () => {
        for (const language of ['plaintext', 'cobol', 'extend', 'insertBefore', 'constructor']) {
            const code = codeElement(language, 'const a = 1;');

            expect(highlightElement(code)).toBe(false);
            expect(code.innerHTML).toBe('const a = 1;');
        }
    });

    it('leaves a block with inline formatting as the author wrote it', () => {
        for (const html of ['const <strong>a</strong> = 1;', 'one<br><a href="/x">two</a>']) {
            const code = codeElement('javascript', html);

            expect(highlightElement(code)).toBe(false);
            expect(code.innerHTML).toBe(html);
        }
    });

    it('highlights code saved the way the editor saves it, with <br> line breaks', () => {
        const code = codeElement(
            'php',
            "&lt;?php<br>function a( array $b ): array<br>    {<br>        return $b; // 'c'<br>    }"
        );

        expect(highlightElement(code)).toBe(true);
        expect(code.querySelector('br')).toBeNull();
        expect(code.textContent).toBe("<?php\nfunction a( array $b ): array\n    {\n        return $b; // 'c'\n    }");
        expect(code.querySelector('.ap-code-keyword')?.textContent).toBe('function');
        expect(code.querySelector('.ap-code-comment')?.textContent).toBe("// 'c'");
    });

    it('ends a line comment at a <br>, rather than running on through the block', () => {
        const code = codeElement('javascript', '// note<br>const a = 1;');

        highlightElement(code);

        expect(code.querySelector('.ap-code-comment')?.textContent).toBe('// note');
        expect(code.querySelector('.ap-code-keyword')?.textContent).toBe('const');
    });

    it('is a no-op on a block it already highlighted', () => {
        const code = codeElement('javascript', 'const a = 1;');

        highlightElement(code);
        const once = code.innerHTML;

        expect(highlightElement(code)).toBe(false);
        expect(code.innerHTML).toBe(once);
    });

    it('decodes what the block saved before tokenizing', () => {
        const code = codeElement('javascript', 'a&#91;0] = "https:&#47;&#47;example.com";');

        highlightElement(code);

        expect(code.textContent).toBe('a[0] = "https://example.com";');
    });

    it.each([
        ['html', '&lt;img src=x onerror=alert(1)&gt;'],
        ['html', '&lt;/code&gt;&lt;script&gt;alert(1)&lt;/script&gt;'],
        ['html', '&amp;lt;img src=x onerror=alert(1)&amp;gt;'],
        ['javascript', '"&lt;img src=x onerror=alert(1)&gt;"'],
        ['php', '&lt;?php echo "&lt;script&gt;alert(1)&lt;/script&gt;";'],
        ['blade', '{!! "&lt;img src=x onerror=alert(1)&gt;" !!}'],
        ['markdown', '```html\n&lt;img src=x onerror=alert(1)&gt;\n```'],
        ['markdown', '```bogus"&gt;&lt;img src=x onerror=alert(1)&gt;\ncode\n```'],
        ['markdown', '[a](javascript:alert(1)) &amp;quot;&gt;&lt;img src=x onerror=1&gt;'],
    ])('never turns %s block content into elements: %s', (language, source) => {
        const code = codeElement(language, source);
        const text = code.textContent;

        highlightElement(code);

        for (const element of Array.from(code.querySelectorAll('*'))) {
            expect(element.tagName).toBe('SPAN');
            expect(element.getAttributeNames().filter((name) => name !== 'class' && name !== 'title')).toEqual([]);
        }
        expect(code.textContent).toBe(text);
    });

    it('prefixes every token class, so host CSS such as daisyUI `.table` cannot reach it', () => {
        const code = codeElement('markdown', '| a |\n|---|\n| b |\n\n**bold** `x`');

        highlightElement(code);

        const classes = Array.from(code.querySelectorAll('*')).flatMap((element) => Array.from(element.classList));

        expect(classes.length).toBeGreaterThan(0);
        expect(classes.filter((name) => !name.startsWith('ap-code-'))).toEqual([]);
    });

    it('leaves no global Prism behind and does not run by itself', () => {
        const untouched = document.createElement('code');
        untouched.className = 'language-javascript';
        untouched.textContent = 'const a = 1;';
        document.body.appendChild(untouched);

        expect((globalThis as { Prism?: unknown }).Prism).toBeUndefined();
        expect(untouched.childElementCount).toBe(0);
    });

    it('inserts the token colours once, with the first highlighted block', () => {
        expect(highlightElement(codeElement('plaintext', 'a'))).toBe(false);
        expect(document.querySelector('style[data-ve-code-highlight]')).toBeNull();

        highlightElement(codeElement('javascript', 'const a = 1;'));
        highlightElement(codeElement('javascript', 'const b = 2;'));

        const styles = document.querySelectorAll('style[data-ve-code-highlight]');

        expect(styles).toHaveLength(1);
        expect(styles[0].textContent).toContain('--ap-code-keyword');
    });
});

describe('Blade bundle', () => {
    const source = read('packages/visual-editor-renderer-blade/resources/assets/frontend/code-highlight.js');

    function runBundle(): void {
        new Function(source)();
    }

    it('highlights Code blocks by itself, and only those', () => {
        document.body.innerHTML =
            '<pre class="wp-block-code"><code class="language-javascript">const a = 1;</code></pre>' +
            '<pre class="other"><code class="language-javascript">const b = 2;</code></pre>';

        runBundle();

        expect(document.querySelectorAll('.ap-code-keyword')).toHaveLength(1);
        expect(document.querySelector('.other code')?.childElementCount).toBe(0);
    });

    it('picks up blocks added later on ap:rebind', () => {
        runBundle();
        document.body.innerHTML = '<pre class="wp-block-code"><code class="language-php">&lt;?php echo 1;</code></pre>';

        window.dispatchEvent(new Event('ap:rebind'));

        expect(document.querySelector('.ap-code-keyword')).not.toBeNull();
    });

    it('sticks to syntax every supported browser parses', () => {
        expect(source).not.toMatch(/\(\?<[=!]/);
    });
});

describe('generated output', () => {
    const generated = 'src/support/codeHighlighter.generated.js';

    it('is the same bundle in React and Vue', () => {
        expect(read(`packages/visual-editor-renderer-react/${generated}`)).toBe(
            read(`packages/visual-editor-renderer-vue/${generated}`)
        );
    });

    it('ships the same stylesheet to Blade', () => {
        expect(read('packages/visual-editor-renderer-blade/resources/assets/frontend/code-highlight.css')).toBe(
            read('packages/code-highlight/code-highlight.css')
        );
    });
});

describe('scheme', () => {
    function blockOn(background: string, text: string, extra = ''): HTMLElement {
        const section = document.createElement('section');
        section.setAttribute('style', `background-color: ${background}; color: ${text}; ${extra}`);
        const code = document.createElement('code');
        code.className = 'language-javascript';
        code.textContent = 'const a = 1;';
        section.appendChild(code);
        document.body.appendChild(section);
        highlightElement(code);

        return code;
    }

    it('uses the light palette on a light background', () => {
        const code = blockOn('rgb(255, 255, 255)', 'rgb(31, 41, 55)');

        expect(code.getAttribute('data-ap-code-scheme')).toBe('light');
        expect(code.hasAttribute('data-ap-code-contrast')).toBe(false);
    });

    it('uses the dark palette on a dark section, even with dark text', () => {
        const code = blockOn('rgb(36, 41, 51)', 'rgb(15, 23, 42)');

        expect(code.getAttribute('data-ap-code-scheme')).toBe('dark');
        expect(code.getAttribute('data-ap-code-contrast')).toBe('low');
    });

    it('leaves readable text on a dark section alone', () => {
        const code = blockOn('rgb(36, 41, 51)', 'rgb(229, 231, 235)');

        expect(code.getAttribute('data-ap-code-scheme')).toBe('dark');
        expect(code.hasAttribute('data-ap-code-contrast')).toBe(false);
    });

    it('sees through a transparent parent to the background behind it', () => {
        const code = blockOn('rgba(0, 0, 0, 0)', 'rgb(15, 23, 42)');

        document.body.style.backgroundColor = 'rgb(36, 41, 51)';
        code.textContent = 'const a = 1;';
        highlightElement(code);
        document.body.style.backgroundColor = '';

        expect(code.getAttribute('data-ap-code-scheme')).toBe('dark');
    });

    it('falls back to the text colour behind an image, and flags nothing', () => {
        const code = blockOn('rgb(255, 255, 255)', 'rgb(229, 231, 235)', 'background-image: url(a.png);');

        expect(code.getAttribute('data-ap-code-scheme')).toBe('dark');
        expect(code.hasAttribute('data-ap-code-contrast')).toBe(false);
    });

    it('keeps replacing unreadable text however many times it re-measures', async () => {
        const style = document.createElement('style');
        style.textContent = "code[data-ap-code-contrast='low'] { color: rgb(230, 237, 243); }";
        document.head.appendChild(style);

        const code = blockOn('rgb(36, 41, 51)', 'rgb(15, 23, 42)');

        expect(code.getAttribute('data-ap-code-contrast')).toBe('low');

        for (const value of ['a', 'b', 'c']) {
            document.body.setAttribute('data-theme', value);
            await new Promise((resolve) => setTimeout(resolve));

            expect(code.getAttribute('data-ap-code-contrast')).toBe('low');
        }

        document.body.removeAttribute('data-theme');
    });

    it('re-measures when the theme changes', async () => {
        const code = blockOn('rgba(0, 0, 0, 0)', 'rgb(15, 23, 42)');

        document.body.style.backgroundColor = 'rgb(255, 255, 255)';
        await vi.waitFor(() => expect(code.getAttribute('data-ap-code-scheme')).toBe('light'));

        document.body.style.backgroundColor = 'rgb(36, 41, 51)';
        await vi.waitFor(() => expect(code.getAttribute('data-ap-code-scheme')).toBe('dark'));

        document.body.style.backgroundColor = '';
    });
});

describe('token colours', () => {
    const css = read('packages/code-highlight/code-highlight.css');
    const palette = (scheme: string): Array<[string, string]> => {
        const body = new RegExp(`code\\[data-ap-code-scheme='${scheme}'\\] \\{([^}]*)\\}`).exec(css)?.[1] ?? '';

        return [...body.matchAll(/--ap-code-default-([a-z]+): (#[0-9a-f]{6});/g)].map((match) => [match[1], match[2]]);
    };

    it('defines every token for both palettes', () => {
        expect(palette('light').map(([name]) => name)).toEqual(palette('dark').map(([name]) => name));
        expect(palette('light')).toHaveLength(10);
    });

    it('lets a theme override every token', () => {
        for (const [name] of palette('light')) {
            expect(css).toContain(`var(--ap-code-${name}, var(--ap-code-default-${name}))`);
        }
    });

    it.each([
        ['light', '#ffffff'],
        ['light', '#f3f4f6'],
        ['dark', '#0f172a'],
        ['dark', '#111827'],
        ['dark', '#1d232a'],
        ['dark', '#232933'],
        ['dark', '#000000'],
    ])('the %s palette meets WCAG AA on %s', (scheme, background) => {
        for (const [, colour] of palette(scheme)) {
            expect(contrast(toLinear(colour), toLinear(background))).toBeGreaterThanOrEqual(4.5);
        }
    });
});
