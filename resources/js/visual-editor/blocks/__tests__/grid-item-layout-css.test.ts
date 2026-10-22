/**
 * #930 — the Grid Item inner-layout presets used to sit at (0,2,0),
 * so the Flex panel's `ap-items-*` / `ap-justify-*` / `ap-flex-col`
 * utilities (0,1,0) on a Grid Item never applied. These guards pin the
 * presets at zero specificity in the editor stylesheet and its Blade
 * frontend copy.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { describe, it, expect } from 'vitest';

function readCss(relative: string): string {
    return readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');
}

const LAYOUTS = ['normal', 'equal', 'center', 'bottom', 'last-bottom'];

const SHEETS = {
    'editor grid.css': '../grid/grid.css',
    'Blade frontend grid.css': '../../../../../packages/visual-editor-renderer-blade/resources/assets/frontend/grid.css',
};

describe('Grid Item inner-layout specificity', () => {
    for (const [label, path] of Object.entries(SHEETS)) {
        it(`${label} wraps every inner-layout preset in :where()`, () => {
            const css = readCss(path);

            for (const layout of LAYOUTS) {
                expect(css).toContain(`:where(.ap-grid-item.ap-grid-item-layout-${layout}) {`);
                expect(css).not.toMatch(new RegExp(`^\\.ap-grid-item\\.ap-grid-item-layout-${layout} \\{`, 'm'));
            }
        });
    }
});
