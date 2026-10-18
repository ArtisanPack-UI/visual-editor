/**
 * Copy-link chip behaviour of the Blade package's front-end
 * `interactivity.js` (#963).
 *
 * The renderers emit the Social Share Content copy-link chip as a plain
 * `<a href="{permalink}" data-ap-copy-link>` so it works without
 * JavaScript. The script upgrades it to a `<button type="button">` that
 * writes the permalink to the clipboard and announces the result in a
 * polite live region.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const here = dirname(fileURLToPath(import.meta.url));
const script = readFileSync(
    resolve(here, '../../resources/assets/frontend/interactivity.js'),
    'utf8'
);

const PERMALINK = 'https://example.test/posts/hello';

function renderChip(): void {
    document.body.innerHTML = `
        <div class="wp-block-artisanpack-social-share-content ap-social-share-content">
            <div class="ap-social-share-content__item">
                <a class="ap-social-share-content__chip copyLink" href="${PERMALINK}" style="--ap-social-color: #112233" aria-label="Copy link" data-ap-copy-link="${PERMALINK}">
                    <svg aria-hidden="true" class="ap-social-share-content__icon"><path d="M0 0"></path></svg>
                    <span class="ap-social-share-content__label">Copy link</span>
                </a>
            </div>
        </div>`;
}

function runScript(): void {
    // eslint-disable-next-line no-new-func
    new Function(script)();
}

function setClipboard(writeText: (text: string) => Promise<void>): void {
    Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: { writeText },
    });
}

async function flush(): Promise<void> {
    await Promise.resolve();
    await Promise.resolve();
    vi.advanceTimersByTime(60);
}

describe('interactivity.js copy-link chip', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        renderChip();
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
        Reflect.deleteProperty(navigator, 'clipboard');
    });

    it('upgrades the fallback link to a button that keeps the chip attributes', () => {
        runScript();

        const chip = document.querySelector('.ap-social-share-content__chip');

        expect(chip?.tagName).toBe('BUTTON');
        expect(chip?.getAttribute('type')).toBe('button');
        expect(chip?.hasAttribute('href')).toBe(false);
        expect(chip?.getAttribute('aria-label')).toBe('Copy link');
        expect(chip?.getAttribute('data-ap-copy-link')).toBe(PERMALINK);
        expect(chip?.getAttribute('class')).toBe('ap-social-share-content__chip copyLink');
        expect(chip?.getAttribute('style')).toContain('--ap-social-color');
        expect(chip?.querySelector('.ap-social-share-content__label')?.textContent).toBe('Copy link');

        const status = chip?.nextElementSibling;
        expect(status?.getAttribute('role')).toBe('status');
        expect(status?.getAttribute('aria-live')).toBe('polite');
        expect(status?.textContent).toBe('');
    });

    it('is idempotent across re-runs', () => {
        runScript();
        window.dispatchEvent(new Event('ap:rebind'));

        expect(document.querySelectorAll('button[data-ap-copy-link]')).toHaveLength(1);
        expect(document.querySelectorAll('[role="status"]')).toHaveLength(1);
    });

    it('copies the permalink and announces "Link copied"', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        setClipboard(writeText);
        runScript();

        const button = document.querySelector<HTMLButtonElement>('button[data-ap-copy-link]');
        button?.click();
        await flush();

        expect(writeText).toHaveBeenCalledWith(PERMALINK);
        expect(document.querySelector('[role="status"]')?.textContent).toBe('Link copied');
        expect(button?.classList.contains('is-copied')).toBe(true);

        vi.advanceTimersByTime(4000);

        expect(document.querySelector('[role="status"]')?.textContent).toBe('');
        expect(button?.classList.contains('is-copied')).toBe(false);
    });

    it('announces a failure when the clipboard cannot be written', async () => {
        setClipboard(() => Promise.reject(new Error('denied')));
        document.execCommand = vi.fn(() => false);
        runScript();

        document.querySelector<HTMLButtonElement>('button[data-ap-copy-link]')?.click();
        await flush();

        expect(document.querySelector('[role="status"]')?.textContent).toBe('Could not copy link');
    });
});
