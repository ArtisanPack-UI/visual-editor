/**
 * Shared lightbox overlay (#1046). Exercises the bundle the renderers ship,
 * not the source, so it also covers what the build script assembles.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { afterEach, describe, expect, it } from 'vitest';

import { bindLightbox } from '../../visual-editor-renderer-react/src/support/lightboxOverlay.generated.js';

const root = resolve(__dirname, '../../..');
const read = (path: string): string => readFileSync(resolve(root, path), 'utf8');

const LABELS = JSON.stringify({ dialog: 'Vergrößert', close: 'Schließen', previous: 'Zurück', next: 'Weiter' });

function figure(name: string, extra = ''): string {
    return `<figure class="wp-block-image wp-lightbox-container" data-ap-lightbox-labels='${LABELS}'>
        <img src="/${name}.jpg" alt="${name} alt"/>
        <button type="button" class="lightbox-trigger" aria-label="Enlarge image: ${name}" style="display:none"></button>
        ${extra}
    </figure>`;
}

function page(html: string): void {
    document.body.innerHTML = html;
    document.querySelectorAll<HTMLElement>('.wp-lightbox-container').forEach((element) => bindLightbox(element));
}

const trigger = (index = 0): HTMLButtonElement =>
    document.querySelectorAll<HTMLButtonElement>('.lightbox-trigger')[index];
const overlay = (): HTMLElement => document.querySelector<HTMLElement>('.wp-lightbox-overlay') as HTMLElement;
const overlayImage = (): HTMLImageElement => overlay().querySelector('img') as HTMLImageElement;
const overlaySrc = (): string => new URL(overlayImage().src).pathname;
const isOpen = (): boolean => overlay()?.classList.contains('active') ?? false;
const button = (selector: string): HTMLButtonElement => overlay().querySelector(selector) as HTMLButtonElement;
const previous = (): HTMLButtonElement => button('.wp-lightbox-navigation-button-prev');
const next = (): HTMLButtonElement => button('.wp-lightbox-navigation-button-next');
const close = (): HTMLButtonElement => button('.wp-lightbox-close-button');

function press(key: string, init: KeyboardEventInit = {}): KeyboardEvent {
    const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init });

    (document.activeElement ?? document.body).dispatchEvent(event);

    return event;
}

afterEach(() => {
    window.dispatchEvent(new PopStateEvent('popstate'));
    document.body.innerHTML = '';
    document.documentElement.style.overflow = '';
});

describe('triggers', () => {
    it('marks the document, which is what reveals the triggers', () => {
        page(figure('a'));

        expect(document.documentElement.classList.contains('ap-lightbox-ready')).toBe(true);
    });

    it('leaves the inline style for the stylesheet to override, so a morph cannot undo it', () => {
        page(figure('a'));

        expect(trigger().style.display).toBe('none');
    });
});

describe('opening', () => {
    it('opens from the trigger', () => {
        page(figure('a'));

        trigger().click();

        expect(isOpen()).toBe(true);
        expect(overlaySrc()).toBe('/a.jpg');
    });

    it('opens from the image', () => {
        page(figure('a'));

        (document.querySelector('figure img') as HTMLElement).click();

        expect(isOpen()).toBe(true);
    });

    it('opens from an image inside a <picture>', () => {
        page(`<figure class="wp-lightbox-container"><picture><img src="/p.jpg" alt=""/></picture></figure>`);

        (document.querySelector('figure img') as HTMLElement).click();

        expect(isOpen()).toBe(true);
    });

    it('ignores other clicks in the figure, such as a caption link', () => {
        page(figure('a', '<figcaption><a href="#x">More</a></figcaption>'));

        (document.querySelector('figcaption a') as HTMLElement).click();

        expect(overlay()).toBeNull();
    });

    it('ignores a click outside any lightbox', () => {
        page(`${figure('a')}<p id="other">Text</p>`);

        (document.getElementById('other') as HTMLElement).click();

        expect(overlay()).toBeNull();
    });

    it('carries the alt text and caption through', () => {
        page(figure('a', '<figcaption>A <em>fine</em> caption</figcaption>'));

        trigger().click();

        expect(overlayImage().alt).toBe('a alt');
        expect(overlay().querySelector('figcaption')?.innerHTML).toBe('A <em>fine</em> caption');
    });

    it('drops the previous image\'s caption', () => {
        page(figure('a', '<figcaption>First</figcaption>') + figure('b'));

        trigger(0).click();
        close().click();
        trigger(1).click();

        expect(overlay().querySelector('figcaption')).toBeNull();
    });

    it('keeps srcset so the enlarged image is sharp at full width', () => {
        page(figure('a').replace('<img', '<img srcset="/a-2x.jpg 2000w" sizes="600px"'));

        trigger().click();

        expect(overlayImage().getAttribute('srcset')).toBe('/a-2x.jpg 2000w');
        expect(overlayImage().getAttribute('sizes')).toBe('100vw');
    });

    it('is a labelled modal dialog using the labels the renderer printed', () => {
        page(figure('a'));

        trigger().click();

        expect(overlay().getAttribute('role')).toBe('dialog');
        expect(overlay().getAttribute('aria-modal')).toBe('true');
        expect(overlay().getAttribute('aria-label')).toBe('Vergrößert');
        expect(close().getAttribute('aria-label')).toBe('Schließen');
    });

    it('falls back to English when the labels are unreadable', () => {
        page(`<figure class="wp-lightbox-container" data-ap-lightbox-labels="not json"><img src="/a.jpg" alt=""/></figure>`);

        (document.querySelector('figure img') as HTMLElement).click();

        expect(close().getAttribute('aria-label')).toBe('Close');
    });

    it('stops the page scrolling while open, and restores it', () => {
        document.documentElement.style.overflow = 'scroll';
        page(figure('a'));

        trigger().click();
        expect(document.documentElement.style.overflow).toBe('hidden');

        close().click();
        expect(document.documentElement.style.overflow).toBe('scroll');
    });

    it('rebuilds the overlay after the page body is replaced', () => {
        page(figure('a'));
        trigger().click();

        page(figure('b'));
        trigger().click();

        expect(isOpen()).toBe(true);
        expect(overlaySrc()).toBe('/b.jpg');
    });

    it('releases the scroll lock when navigation replaces the page while open', () => {
        document.documentElement.style.overflow = 'scroll';
        page(figure('a'));
        trigger().click();

        page(figure('b'));
        trigger().click();
        close().click();

        expect(document.documentElement.style.overflow).toBe('scroll');
    });

    it('closes when a caption link is followed', () => {
        page(figure('a', '<figcaption><a href="#x">More</a></figcaption>'));
        trigger().click();

        (overlay().querySelector('figcaption a') as HTMLElement).click();

        expect(isOpen()).toBe(false);
    });

    it('closes before Turbo snapshots the page', () => {
        page(figure('a'));
        trigger().click();

        document.dispatchEvent(new Event('turbo:before-cache'));

        expect(isOpen()).toBe(false);
    });

    it('closes on browser back', () => {
        page(figure('a'));
        trigger().click();

        window.dispatchEvent(new PopStateEvent('popstate'));

        expect(isOpen()).toBe(false);
        expect(document.documentElement.style.overflow).toBe('');
    });
});

describe('focus and keyboard', () => {
    it('moves focus into the overlay', () => {
        page(figure('a'));

        trigger().click();

        expect(document.activeElement).toBe(close());
    });

    it.each([
        ['Escape', () => press('Escape')],
        ['the close button', () => close().click()],
        ['the backdrop', () => (overlay().querySelector('.scrim') as HTMLElement).click()],
    ])('closes with %s and returns focus to the trigger', (_label, action) => {
        page(figure('a'));
        trigger().click();

        action();

        expect(isOpen()).toBe(false);
        expect(document.activeElement).toBe(trigger());
    });

    it('returns focus to the trigger that opened it after browsing a gallery', () => {
        page(`<figure class="wp-block-gallery">${figure('a')}${figure('b')}</figure>`);
        trigger(0).click();

        press('ArrowRight');
        press('Escape');

        expect(document.activeElement).toBe(trigger(0));
    });

    it('keeps Tab inside the overlay in both directions', () => {
        page(`<figure class="wp-block-gallery">${figure('a')}${figure('b')}</figure>`);
        trigger(0).click();

        const focusable = Array.from(overlay().querySelectorAll<HTMLElement>('button:not([hidden])'));
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        last.focus();
        expect(press('Tab').defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(first);

        press('Tab', { shiftKey: true });
        expect(document.activeElement).toBe(last);
    });

    it('leaves Tab alone between the first and last controls', () => {
        page(`<figure class="wp-block-gallery">${figure('a')}${figure('b')}</figure>`);
        trigger(0).click();

        next().focus();

        expect(press('Tab').defaultPrevented).toBe(false);
    });

    it('includes a caption link in the Tab order', () => {
        page(figure('a', '<figcaption><a href="#x">More</a></figcaption>'));
        trigger().click();

        close().focus();
        press('Tab');

        expect(document.activeElement).toBe(overlay().querySelector('figcaption a'));
    });
});

describe('gallery navigation', () => {
    const gallery = (): string =>
        `<figure class="wp-block-gallery">${figure('a')}${figure('b')}${figure('c')}</figure>`;

    it('steps with the buttons and wraps at both ends', () => {
        page(gallery());
        trigger(0).click();

        previous().click();
        expect(overlaySrc()).toBe('/c.jpg');

        next().click();
        expect(overlaySrc()).toBe('/a.jpg');
    });

    it('steps with the arrow keys', () => {
        page(gallery());
        trigger(1).click();

        press('ArrowRight');
        expect(overlaySrc()).toBe('/c.jpg');

        press('ArrowLeft');
        press('ArrowLeft');
        expect(overlaySrc()).toBe('/a.jpg');
    });

    it('announces the new image', () => {
        page(gallery());
        trigger(0).click();

        press('ArrowRight');

        expect(overlay().querySelector('[aria-live="polite"]')?.textContent).toBe('b alt');
    });

    it('skips a linked gallery image, which prints no lightbox', () => {
        page(
            `<figure class="wp-block-gallery">${figure('a')}<figure class="wp-block-image"><a href="/x"><img src="/x.jpg" alt=""/></a></figure>${figure('b')}</figure>`
        );
        trigger(0).click();

        press('ArrowRight');

        expect(overlaySrc()).toBe('/b.jpg');
    });

    it('has no navigation for a standalone image, even next to another', () => {
        page(figure('a') + figure('b'));
        trigger(0).click();

        press('ArrowRight');

        expect(previous().hidden).toBe(true);
        expect(next().hidden).toBe(true);
        expect(overlaySrc()).toBe('/a.jpg');
    });

    it('has no navigation for a gallery of one', () => {
        page(`<figure class="wp-block-gallery">${figure('a')}</figure>`);
        trigger(0).click();

        expect(next().hidden).toBe(true);
    });

    it.each([
        [undefined, false, true],
        ['text', true, false],
        ['both', false, false],
    ])('honors a %s navigation button type', (type, iconHidden, textHidden) => {
        page(
            `<figure class="wp-block-gallery"${type === undefined ? '' : ` data-ap-lightbox-navigation="${type}"`}>${figure('a')}${figure('b')}</figure>`
        );
        trigger(0).click();

        expect((next().querySelector('.wp-lightbox-navigation-icon') as HTMLElement).hidden).toBe(iconHidden);
        expect((next().querySelector('.wp-lightbox-navigation-text') as HTMLElement).hidden).toBe(textHidden);
        expect(next().getAttribute('aria-label')).toBe('Weiter');
    });
});

describe('styles', () => {
    const css = read('packages/lightbox/lightbox.css');

    it('only animates when motion is allowed', () => {
        const [outside] = css.split('@media not (prefers-reduced-motion)');

        expect(outside).not.toMatch(/\b(animation|transition):/);
    });

    it('gives the trigger a target of at least 24px', () => {
        expect(css).toMatch(/\.lightbox-trigger \{[^}]*width: 32px;[^}]*height: 32px;/);
    });

    it('leaves the image at its normal size in a block figure', () => {
        expect(css).toMatch(/\.wp-lightbox-container \{[^}]*display: block;/);
    });

    it('reveals triggers over their inline style once the script has run', () => {
        expect(css).toMatch(/\.ap-lightbox-ready [^{]*\.lightbox-trigger \{\s*display: flex !important;/);
    });

    it('shows captions, which the block library hides', () => {
        expect(css).toMatch(/\.wp-lightbox-overlay \.wp-block-image figcaption \{\s*display: block;/);
    });

    it('ships the same CSS to the Blade renderer', () => {
        expect(read('packages/visual-editor-renderer-blade/resources/assets/frontend/lightbox.css')).toBe(css);
    });
});
