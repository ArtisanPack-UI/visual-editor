/**
 * #748 — the columns and grid blocks declare `spacing.blockGap` support
 * but the editor stylesheets never turned that value into a real `gap`,
 * so both rendered flush. These guards assert each block's stylesheet
 * emits a `gap: var(--wp--style--block-gap, <default>)` rule whose
 * fallback matches the block's `__experimentalDefault`. The Blade
 * frontend copies are covered by AssetRouteTest.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { describe, it, expect } from 'vitest';

function readCss(relative: string): string {
    return readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');
}

describe('blockGap gap rules', () => {
    it('columns.css materializes blockGap as a gap with the 2em default', () => {
        const css = readCss('../columns/columns.css');
        expect(css).toContain('.wp-block-columns');
        expect(css).toContain('gap: var(--wp--style--block-gap, 2em)');
    });

    it('grid.css materializes blockGap as a gap with the 1.5rem default', () => {
        const css = readCss('../grid/grid.css');
        expect(css).toContain('.ap-grid');
        expect(css).toContain('gap: var(--wp--style--block-gap, 1.5rem)');
    });
});
