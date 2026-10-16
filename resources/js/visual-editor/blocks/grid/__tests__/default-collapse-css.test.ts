/**
 * #901 — a Grid with no Tablet / Mobile column count collapses on small
 * screens: at most two columns on Tablet, one on Mobile. The rules live
 * in grid.css (shared by the canvas and, as an identical copy, the Blade
 * front end; React and Vue emit the same classes). jsdom can't evaluate
 * media queries, so these pull each collapse rule's selector out of its
 * `@media` block and check which grids it matches.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { afterEach, describe, expect, it } from 'vitest';

function readCss(relative: string): string {
    return readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');
}

const css = readCss('../grid.css');

/** The body of the `@media (max-width: …px)` block. */
function mediaBlock(maxWidth: number): string {
    const start = css.indexOf(`@media (max-width: ${maxWidth}px) {`);
    const end = css.indexOf('\n}', start);

    return css.slice(start, end);
}

/** Selectors of the rules in a block that set the given declaration. */
function selectorsFor(block: string, declaration: string): string[] {
    return block
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.endsWith(`{ ${declaration} }`))
        .map((line) => line.slice(0, line.indexOf(' {')));
}

function grid(classes: string): HTMLElement {
    const el = document.createElement('div');
    el.className = `ap-grid ${classes}`;
    document.body.appendChild(el);

    return el;
}

function item(parent: HTMLElement, classes: string): HTMLElement {
    const el = document.createElement('div');
    el.className = `ap-grid-item ${classes}`;
    parent.appendChild(el);

    return el;
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('Grid default collapse (#901)', () => {
    it('ships the same rules on the front end', () => {
        expect(readCss('../../../../../../packages/visual-editor-renderer-blade/resources/assets/frontend/grid.css')).toBe(css);
    });

    describe('Tablet', () => {
        const [columns] = selectorsFor(mediaBlock(1023), 'grid-template-columns: repeat(2, minmax(0, 1fr));').filter((s) => s.includes('base-columns'));
        const [spans] = selectorsFor(mediaBlock(1023), 'grid-column: span 2;');

        it('drops a three-or-more column grid with no Tablet value to two columns', () => {
            expect(grid('ap-grid-has-3-base-columns').matches(columns)).toBe(true);
            expect(grid('ap-grid-has-12-base-columns').matches(columns)).toBe(true);
        });

        it('leaves one- and two-column grids alone', () => {
            expect(grid('ap-grid-has-1-base-columns').matches(columns)).toBe(false);
            expect(grid('ap-grid-has-2-base-columns').matches(columns)).toBe(false);
        });

        it('keeps an explicit Tablet or legacy value', () => {
            expect(grid('ap-grid-has-4-base-columns ap-grid-has-4-tablet-columns').matches(columns)).toBe(false);
            expect(grid('ap-grid-has-4-base-columns ap-grid-has-4-md-columns').matches(columns)).toBe(false);
            expect(grid('ap-grid-has-4-base-columns ap-grid-has-4-2xl-columns').matches(columns)).toBe(false);
        });

        it('collapses on Tablet when only Mobile is set', () => {
            expect(grid('ap-grid-has-4-base-columns ap-grid-has-1-mobile-columns').matches(columns)).toBe(true);
        });

        it('clamps wide items in a collapsed grid to a span of two', () => {
            const parent = grid('ap-grid-has-4-base-columns');

            expect(item(parent, 'ap-grid-item-span-3-base-columns').matches(spans)).toBe(true);
            expect(item(parent, 'ap-grid-item-span-4-tablet-columns').matches(spans)).toBe(true);
            expect(item(parent, 'ap-grid-item-span-2-base-columns').matches(spans)).toBe(false);
            expect(item(grid('ap-grid-has-4-base-columns ap-grid-has-4-tablet-columns'), 'ap-grid-item-span-3-base-columns').matches(spans)).toBe(false);
        });
    });

    describe('Mobile', () => {
        const [columns] = selectorsFor(mediaBlock(767), 'grid-template-columns: repeat(1, minmax(0, 1fr));').filter((s) => s.includes('base-columns'));
        const [spans] = selectorsFor(mediaBlock(767), 'grid-column: auto;');

        it('stacks a two-or-more column grid with no Mobile value', () => {
            expect(grid('ap-grid-has-2-base-columns').matches(columns)).toBe(true);
            expect(grid('ap-grid-has-3-base-columns ap-grid-has-3-tablet-columns').matches(columns)).toBe(true);
        });

        it('keeps an explicit Mobile or legacy value', () => {
            expect(grid('ap-grid-has-3-base-columns ap-grid-has-2-mobile-columns').matches(columns)).toBe(false);
            expect(grid('ap-grid-has-3-base-columns ap-grid-has-1-sm-columns').matches(columns)).toBe(false);
            expect(grid('ap-grid-has-1-base-columns').matches(columns)).toBe(false);
        });

        it('drops item spans in a stacked grid', () => {
            expect(item(grid('ap-grid-has-3-base-columns'), 'ap-grid-item-span-2-base-columns').matches(spans)).toBe(true);
            expect(item(grid('ap-grid-has-3-base-columns ap-grid-has-3-mobile-columns'), 'ap-grid-item-span-2-base-columns').matches(spans)).toBe(false);
        });
    });
});
