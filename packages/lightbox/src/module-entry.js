/**
 * React and Vue renderer entry (#1046).
 *
 * Those renderers leave CSS delivery to the host, so the overlay's styles
 * travel with it, inserted once alongside the first lightbox.
 */

import { bindLightbox as bind } from './lightbox.js';
import css from '../lightbox.css?raw';

const STYLE_ATTRIBUTE = 'data-ve-lightbox';

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
 * Set up one lightbox figure.
 *
 * @param {Element} element The `.wp-lightbox-container` figure.
 */
export function bindLightbox(element) {
    ensureStyles(element.ownerDocument);
    bind(element);
}
