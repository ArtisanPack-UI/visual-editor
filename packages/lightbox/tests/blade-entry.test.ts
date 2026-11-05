/**
 * The Blade renderer's self-starting lightbox bundle (#1046). In its own file
 * because running the bundle leaves a click listener on the document.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { expect, it } from 'vitest';

import { bindLightbox } from '../../visual-editor-renderer-react/src/support/lightboxOverlay.generated.js';

const bundle = readFileSync(
    resolve(__dirname, '../../visual-editor-renderer-blade/resources/assets/frontend/lightbox.js'),
    'utf8'
);

it('marks the document as ready', () => {
    document.body.innerHTML =
        '<figure class="wp-lightbox-container"><img src="/a.jpg" alt=""/><button class="lightbox-trigger" style="display:none"></button></figure>';

    new Function(bundle)();

    expect(document.documentElement.classList.contains('ap-lightbox-ready')).toBe(true);
});

it('opens one overlay from a delegated click, even if the script runs twice', () => {
    new Function(bundle)();

    document.querySelector<HTMLButtonElement>('.lightbox-trigger')?.click();

    expect(document.querySelectorAll('.wp-lightbox-overlay.active')).toHaveLength(1);
});

it('leaves clicks to the Blade copy when the module copy binds too', () => {
    window.dispatchEvent(new PopStateEvent('popstate'));

    const figure = document.querySelector<HTMLElement>('.wp-lightbox-container') as HTMLElement;

    bindLightbox(figure);
    document.querySelector<HTMLButtonElement>('.lightbox-trigger')?.click();

    expect(document.querySelectorAll('.wp-lightbox-overlay.active')).toHaveLength(1);
    expect(document.querySelectorAll('.wp-lightbox-overlay')).toHaveLength(1);
});
