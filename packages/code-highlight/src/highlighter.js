/**
 * Shared Code block highlighter (#1044).
 *
 * The one implementation behind the Blade, React and Vue renderers.
 * `scripts/build-code-highlight.mjs` bundles it with Prism into the files
 * each renderer ships. Regenerate them with `npm run build:code-highlight`
 * after changing anything in this directory.
 *
 * Prism comes from `virtual:prism`, which that script assembles so the
 * library stays out of the global scope and can't collide with a host's own
 * copy.
 */

import Prism from 'virtual:prism';

import { registerBlade } from './blade-grammar.js';
import { applyScheme, watchScheme } from './scheme.js';

registerBlade(Prism);

/** Languages with a grammar. `plaintext` is left out: it has no tokens. */
const SUPPORTED_LANGUAGES = [
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

/** Prism's name for a language, where it differs from the block's. */
const GRAMMAR_NAMES = { html: 'markup' };

/** Longer code stays plain: highlighting it could stall the page. */
const MAX_HIGHLIGHT_LENGTH = 50000;

/**
 * Code with a longer unbroken word stays plain too: Prism's identifier
 * patterns rescan a word from each of its characters, so one 50,000-character
 * word takes seconds.
 */
const MAX_WORD_LENGTH = 2000;

/** Whether the text has a run of word characters longer than the limit. */
function hasLongWord(text) {
    let run = 0;

    for (let index = 0; index < text.length; index++) {
        const code = text.charCodeAt(index);
        const isWord =
            (code >= 48 && code <= 57) ||
            (code >= 65 && code <= 90) ||
            (code >= 97 && code <= 122) ||
            code === 95 ||
            code === 36 ||
            code >= 0xa0;

        run = isWord ? run + 1 : 0;

        if (run > MAX_WORD_LENGTH) {
            return true;
        }
    }

    return false;
}

// Prism's bare class names (`table`, `tag`, `number`) are also Tailwind and
// daisyUI classes, so an unprefixed token picks up the host's component CSS.
// Registered last, so language hooks still see the original names.
Prism.hooks.add('wrap', (env) => {
    env.classes = env.classes.map((name) => `ap-code-${name}`);
});

/** The block's language, read from the class the renderers print. */
function languageOf(element) {
    const match = /(?:^|\s)language-([a-z]+)(?:\s|$)/.exec(element.className);

    return match !== null && SUPPORTED_LANGUAGES.includes(match[1]) ? match[1] : null;
}

/**
 * The element's code as plain text, or `null` if it holds anything but text
 * and line breaks.
 *
 * The editor saves line breaks as `<br>`, so nearly every multi-line block has
 * them, and `textContent` alone would run its lines together.
 */
function plainTextOf(element) {
    const nodes = Array.from(element.childNodes);

    if (nodes.some((node) => node.nodeType === Node.ELEMENT_NODE && node.nodeName !== 'BR')) {
        return null;
    }

    return nodes
        .map((node) => {
            if (node.nodeName === 'BR') {
                return '\n';
            }

            return node.nodeType === Node.TEXT_NODE ? node.nodeValue : '';
        })
        .join('');
}

/**
 * Highlight one `<code>` element in place.
 *
 * Works from text, so block content can't become markup: Prism encodes
 * everything it receives, and only its `<span>`s reach the DOM. An element
 * with other child elements is left alone. That covers links or bold added in
 * the editor, which highlighting would drop, and makes a second call a no-op.
 *
 * @param {Element} element The `<code>` element.
 * @returns {boolean} Whether the element was highlighted.
 */
export function highlightElement(element) {
    const language = languageOf(element);
    const text = language === null ? null : plainTextOf(element);

    if (text === null) {
        return false;
    }

    // Too long to highlight, but the text colour still needs its
    // contrast check.
    if (text.length > MAX_HIGHLIGHT_LENGTH || hasLongWord(text)) {
        applyScheme(element);
        watchScheme();

        return false;
    }

    const name = GRAMMAR_NAMES[language] ?? language;

    element.innerHTML = Prism.highlight(text, Prism.languages[name], name);
    applyScheme(element);
    watchScheme();

    return true;
}
