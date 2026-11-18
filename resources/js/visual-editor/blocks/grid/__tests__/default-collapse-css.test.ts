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

/**
 * Specificity of a selector as [ids, classes, types], enough for these
 * rules: classes, attribute selectors, `:is()` (its most specific
 * argument) and `:not()` (its argument), joined by combinators.
 */
function specificity(selector: string): [number, number, number] {
    const total: [number, number, number] = [0, 0, 0];
    let rest = selector;
    const pseudo = /:(is|not)\(/;
    for (let m = pseudo.exec(rest); m; m = pseudo.exec(rest)) {
        let depth = 1;
        let i = m.index + m[0].length;
        while (depth > 0) {
            if (rest[i] === '(') depth++;
            if (rest[i] === ')') depth--;
            i++;
        }
        const inner = rest.slice(m.index + m[0].length, i - 1);
        const args = splitTop(inner).map(specificity);
        const best = args.reduce((a, b) => (compare(a, b) >= 0 ? a : b));
        total[0] += best[0];
        total[1] += best[1];
        total[2] += best[2];
        rest = rest.slice(0, m.index) + rest.slice(i);
    }
    total[0] += (rest.match(/#[\w-]+/g) || []).length;
    total[1] += (rest.match(/\.[\w-]+/g) || []).length + (rest.match(/\[[^\]]*\]/g) || []).length;
    return total;
}

function splitTop(list: string): string[] {
    const out: string[] = [];
    let depth = 0;
    let start = 0;
    for (let i = 0; i < list.length; i++) {
        if (list[i] === '(') depth++;
        if (list[i] === ')') depth--;
        if (list[i] === ',' && depth === 0) {
            out.push(list.slice(start, i).trim());
            start = i + 1;
        }
    }
    out.push(list.slice(start).trim());
    return out;
}

function compare(a: [number, number, number], b: [number, number, number]): number {
    return a[0] - b[0] || a[1] - b[1] || a[2] - b[2];
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

        // #1128 — a wide item also matches the Tablet rule's `span 2`, whose
        // selector is more specific than the plain reset. A reset at least as
        // specific, later in the file, must match it, or the one-column grid
        // gains a phantom second column on phones.
        it('drops the Tablet span of wide items, with a selector that outranks it', () => {
            const resets = selectorsFor(mediaBlock(767), 'grid-column: auto;');
            const [tabletSpan] = selectorsFor(mediaBlock(1023), 'grid-column: span 2;');
            const parent = grid('ap-grid-has-6-base-columns');
            const wide = item(parent, 'ap-grid-item-span-4-base-columns');

            expect(wide.matches(tabletSpan)).toBe(true);
            const winning = resets.filter((s) => wide.matches(s) && compare(specificity(s), specificity(tabletSpan)) >= 0);
            expect(winning.length).toBeGreaterThan(0);
            expect(css.indexOf(winning[0])).toBeGreaterThan(css.indexOf(tabletSpan));

            const narrow = item(parent, 'ap-grid-item-span-2-base-columns');
            expect(narrow.matches(spans)).toBe(true);
            expect(item(grid('ap-grid-has-6-base-columns ap-grid-has-2-mobile-columns'), 'ap-grid-item-span-4-base-columns').matches(winning[0])).toBe(false);
        });
    });
});
