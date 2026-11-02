/**
 * Code block language class and highlighter loading (#1044).
 *
 * Mirrors the Blade renderer's `CodeLanguage` support class. The class is
 * what a block saves, so it matches on every renderer. `highlightCode()` runs
 * in the browser only and imports the highlighter on first use, so pages with
 * no highlighted block never download it.
 */

import { attrString } from './attributes';

export const CODE_LANGUAGES: readonly string[] = [
    'plaintext',
    'html',
    'css',
    'javascript',
    'typescript',
    'php',
    'python',
    'ruby',
    'go',
    'rust',
    'json',
    'yaml',
    'bash',
    'sql',
    'markdown',
    'blade',
];

/**
 * The `language-*` class for an attribute value, or `''` for none. Only known
 * languages pass, because the value comes from saved block data and lands in
 * a `class` attribute.
 */
export function codeLanguageClass(language: unknown): string {
    const value = attrString(language);

    return CODE_LANGUAGES.includes(value) ? `language-${value}` : '';
}

/** Whether a class from `codeLanguageClass()` has tokens to highlight. */
export function needsHighlighter(languageClass: string): boolean {
    return languageClass !== '' && languageClass !== 'language-plaintext';
}

/**
 * Highlight a `<code>` element. The code is already readable, so a highlighter
 * chunk that fails to load, as it can on a flaky network, just leaves it plain.
 */
export async function highlightCode(element: HTMLElement): Promise<void> {
    try {
        const { highlightElement } = await import('./codeHighlighter.generated.js');

        highlightElement(element);
    } catch {
        // See above.
    }
}
