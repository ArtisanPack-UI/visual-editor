/**
 * Categories dropdown behaviour of the Blade package's front-end
 * `interactivity.js` (#1140).
 *
 * `CategoriesBlock` renders Display as dropdown as a
 * `<select data-ap-categories-dropdown>` whose option values are the
 * terms' links. Choosing one goes there; the "— Choose —" prompt and
 * unsafe links stay put.
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

const originalLocation = window.location;
let assign: ReturnType<typeof vi.fn>;

function renderDropdown(): HTMLSelectElement {
    document.body.innerHTML = `
        <div class="wp-block-categories wp-block-categories-dropdown">
            <label class="wp-block-categories__label" for="wp-block-categories-1">Select a Playlist</label>
            <select id="wp-block-categories-1" name="video-playlist" class="postform" data-ap-categories-dropdown>
                <option value="">— Choose —</option>
                <option class="level-0" value="/videos/playlist/tutorials">Tutorials</option>
                <option class="level-0" value="https://example.test/videos/playlist/tips">Tips</option>
                <option class="level-0" value="javascript:alert(1)">Bad</option>
            </select>
        </div>`;

    return document.querySelector('select') as HTMLSelectElement;
}

function runScript(): void {
    // eslint-disable-next-line no-new-func
    new Function(script)();
}

function choose(select: HTMLSelectElement, value: string): void {
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

describe('interactivity.js categories dropdown', () => {
    beforeEach(() => {
        assign = vi.fn();
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { ...originalLocation, assign },
        });
    });

    afterEach(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: originalLocation });
        document.body.innerHTML = '';
    });

    it('goes to the chosen term link', () => {
        const select = renderDropdown();
        runScript();

        choose(select, '/videos/playlist/tutorials');
        expect(assign).toHaveBeenCalledWith('/videos/playlist/tutorials');

        choose(select, 'https://example.test/videos/playlist/tips');
        expect(assign).toHaveBeenLastCalledWith('https://example.test/videos/playlist/tips');
    });

    it('stays put on the prompt and on unsafe links', () => {
        const select = renderDropdown();
        runScript();

        choose(select, '');
        choose(select, 'javascript:alert(1)');

        expect(assign).not.toHaveBeenCalled();
    });

    it('binds each select once when the script runs again', () => {
        const select = renderDropdown();
        runScript();
        runScript();

        choose(select, '/videos/playlist/tutorials');

        expect(assign).toHaveBeenCalledTimes(1);
    });
});
