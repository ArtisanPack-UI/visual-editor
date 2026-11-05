/**
 * React and Vue renderer entry (#1044).
 *
 * Those renderers leave CSS delivery to the host, so token colours travel
 * with the code, inserted once alongside the first highlighted block.
 */

import { highlightElement as highlight } from './highlighter.js';
import css from '../code-highlight.css?raw';

const STYLE_ATTRIBUTE = 'data-ve-code-highlight';

function ensureStyles(doc) {
    if (doc.querySelector(`[${STYLE_ATTRIBUTE}]`) !== null) {
        return;
    }

    const style = doc.createElement('style');
    style.setAttribute(STYLE_ATTRIBUTE, '');
    style.textContent = css;
    doc.head.appendChild(style);
}

/**
 * Highlight one `<code>` element in place.
 *
 * @param {Element} element The `<code>` element.
 * @returns {boolean} Whether the element was highlighted.
 */
export function highlightElement(element) {
    const highlighted = highlight(element);

    if (highlighted) {
        ensureStyles(element.ownerDocument);
    }

    return highlighted;
}
