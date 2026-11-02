/**
 * The Blade renderer's self-starting lightbox bundle (#1046). In its own file
 * because running the bundle leaves a click listener on the document.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { expect, it } from 'vitest';

const bundle = readFileSync(
    resolve(__dirname, '../../visual-editor-renderer-blade/resources/assets/frontend/lightbox.js'),
    'utf8'
);

const figure = (): string =>
    '<figure class="wp-lightbox-container"><img src="/a.jpg" alt=""/><button class="lightbox-trigger" style="display:none"></button></figure>';

const triggers = (): HTMLButtonElement[] =>
    Array.from(document.querySelectorAll<HTMLButtonElement>('.lightbox-trigger'));

it('reveals every trigger on the page, then new ones on ap:rebind', () => {
    document.body.innerHTML = figure();

    new Function(bundle)();

    expect(triggers()[0].style.display).toBe('');

    document.body.insertAdjacentHTML('beforeend', figure());
    window.dispatchEvent(new Event('ap:rebind'));

    expect(triggers()[1].style.display).toBe('');
});

it.each([['livewire:navigated'], ['turbo:load']])('reveals new triggers after %s', (event) => {
    document.body.insertAdjacentHTML('beforeend', figure());

    document.dispatchEvent(new Event(event));

    expect(triggers().at(-1)?.style.display).toBe('');
});

it('opens one overlay from a delegated click, even if the script runs twice', () => {
    new Function(bundle)();

    triggers()[0].click();

    expect(document.querySelectorAll('.wp-lightbox-overlay.active')).toHaveLength(1);
});
