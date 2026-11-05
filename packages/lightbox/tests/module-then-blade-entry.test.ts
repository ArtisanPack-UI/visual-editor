/**
 * The React / Vue lightbox copy loaded before the Blade one on the same page
 * (#1046). In its own file because each copy leaves a click listener on the
 * document; `blade-entry.test.ts` covers the other order.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { expect, it } from 'vitest';

import { bindLightbox } from '../../visual-editor-renderer-react/src/support/lightboxOverlay.generated.js';

const bundle = readFileSync(
    resolve(__dirname, '../../visual-editor-renderer-blade/resources/assets/frontend/lightbox.js'),
    'utf8'
);

it('keeps the module copy as the only overlay when the Blade copy runs after it', () => {
    document.body.innerHTML =
        '<figure class="wp-lightbox-container"><img src="/a.jpg" alt=""/><button class="lightbox-trigger" style="display:none"></button></figure>';

    bindLightbox(document.querySelector('.wp-lightbox-container') as HTMLElement);
    new Function(bundle)();

    document.querySelector<HTMLButtonElement>('.lightbox-trigger')?.click();

    expect(document.querySelectorAll('.wp-lightbox-overlay.active')).toHaveLength(1);
    expect(document.querySelectorAll('.wp-lightbox-overlay')).toHaveLength(1);
});

it('keeps binding more figures from the module copy once it owns the document', () => {
    window.dispatchEvent(new PopStateEvent('popstate'));
    document.body.insertAdjacentHTML(
        'beforeend',
        '<figure class="wp-lightbox-container" id="second"><img src="/b.jpg" alt=""/><button class="lightbox-trigger" style="display:none"></button></figure>'
    );

    bindLightbox(document.getElementById('second') as HTMLElement);
    document.querySelector<HTMLButtonElement>('#second .lightbox-trigger')?.click();

    expect(document.querySelectorAll('.wp-lightbox-overlay.active')).toHaveLength(1);
    expect(document.querySelectorAll('.wp-lightbox-overlay')).toHaveLength(1);
});
