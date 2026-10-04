/**
 * #821 — on a theme-less install any `var(--wp--…)` / `var(--ap-…)` with
 * no fallback and no declaration collapses to nothing. Every reference in
 * the package's block and frontend stylesheets must either carry a
 * fallback or be declared in the same stylesheet (e.g. the query
 * pagination block declares its own `--ap-query-pagination-*` tokens on
 * the block wrapper).
 */

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

import { describe, expect, it } from 'vitest';

const ROOT = process.cwd();

const DIRECTORIES = [
    'resources/js/visual-editor/blocks',
    'resources/css',
    'packages/visual-editor-renderer-blade/resources/assets/frontend',
];

function cssFiles(directory: string): string[] {
    return readdirSync(directory).flatMap((entry) => {
        const path = join(directory, entry);

        if (statSync(path).isDirectory()) {
            return cssFiles(path);
        }

        return path.endsWith('.css') ? [path] : [];
    });
}

/** `var(--x)` references with no fallback that the stylesheet never declares. */
export function unguardedVars(css: string): string[] {
    const code = css.replace(/\/\*[\s\S]*?\*\//g, '');
    const declared = new Set(Array.from(code.matchAll(/(--[\w-]+)\s*:/g), ([, name]) => name));

    return Array.from(code.matchAll(/var\(\s*(--(?:wp|ap)-[\w-]+)\s*\)/g), ([, name]) => name).filter(
        (name) => !declared.has(name)
    );
}

describe('var() fallbacks in package CSS (#821)', () => {
    it('flags an unguarded reference and accepts guarded or locally declared ones', () => {
        expect(unguardedVars('a { gap: var(--wp--style--block-gap); }')).toEqual(['--wp--style--block-gap']);
        expect(unguardedVars('a { gap: var(--wp--style--block-gap, 1rem); }')).toEqual([]);
        expect(unguardedVars('a { --ap-x: 1px; gap: var( --ap-x ); }')).toEqual([]);
        expect(unguardedVars('/* var(--wp--a) */ a {}')).toEqual([]);
    });

    const files = DIRECTORIES.flatMap((directory) => cssFiles(join(ROOT, directory)));

    it.each(files.map((file) => [relative(ROOT, file), file]))('%s has no unguarded var()', (_, file) => {
        expect(unguardedVars(readFileSync(file, 'utf8'))).toEqual([]);
    });
});
