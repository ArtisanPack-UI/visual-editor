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

/** Documents this copy handles the clicks of. */
const boundHere = new WeakSet();

/**
 * Set up one lightbox figure.
 *
 * The Blade renderer's `frontend/lightbox.js` may already handle this
 * document's clicks, as on an Inertia page whose root view loads the block
 * styles. A second copy would open a second overlay, so whichever copy runs
 * first owns the document: both read the same `data-ap-lightbox-labels`.
 *
 * @param {Element} element The `.wp-lightbox-container` figure.
 */
export function bindLightbox(element) {
    const doc = element.ownerDocument;
    const view = doc.defaultView;

    ensureStyles(doc);

    if (view?.apLightboxBound === true && !boundHere.has(doc)) {
        return;
    }

    boundHere.add(doc);

    if (view !== null) {
        view.apLightboxBound = true;
    }

    bind(element);
}
