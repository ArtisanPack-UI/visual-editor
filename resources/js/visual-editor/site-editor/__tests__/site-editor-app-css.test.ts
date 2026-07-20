/**
 * Regression check for the site-editor shell CSS layout (#666).
 *
 * The three-pane shell depends on an unbroken flex chain from `<html>`
 * through the shell down to each panel's scroll container. When any
 * link in that chain drops its `flex` / `min-height: 0` / `overflow`
 * declarations, the sidebar / canvas / inspector collapse to their
 * intrinsic content size and the whole page starts scrolling instead.
 * This test asserts the presence of the layout-critical rules so a
 * regression that removes them fails loudly rather than silently
 * degrading the editor UX.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

// `package.json` declares `"type": "module"`, so `__dirname` isn't
// defined under raw Node ESM. Vitest happens to shim it, but building
// the path from `import.meta.url` keeps the test portable to other
// runners and to `node --test`.
const HERE = dirname(fileURLToPath(import.meta.url));
const CSS_PATH = resolve(HERE, '..', 'site-editor-app.css');

function readCss(): string {
    return readFileSync(CSS_PATH, 'utf8');
}

describe('site-editor-app.css layout chain (#666)', () => {
    it('anchors html and the SPA body to the viewport', () => {
        const css = readCss();

        expect(css).toMatch(
            /html,\s*\n?\s*\.ap-visual-editor-site-editor-body\s*\{[^}]*height:\s*100%/
        );
        expect(css).toMatch(
            /html,\s*\n?\s*\.ap-visual-editor-site-editor-body\s*\{[^}]*overflow:\s*hidden/
        );
    });

    it('promotes the mount div into a bounded flex column', () => {
        const css = readCss();
        const rule = css.match(
            /\.ap-visual-editor-site-editor-body\s*>\s*#ap-visual-editor-site-editor\s*\{[^}]*\}/
        );

        expect(rule).not.toBeNull();
        expect(rule?.[0]).toContain('height: 100%');
        expect(rule?.[0]).toContain('display: flex');
        expect(rule?.[0]).toContain('flex-direction: column');
        expect(rule?.[0]).toContain('min-height: 0');
        expect(rule?.[0]).toContain('overflow: hidden');
    });

    it('makes <main> a flex passthrough so the body flex chain reaches the panels', () => {
        const css = readCss();
        const rule = css.match(
            /\.ap-site-editor__shell\s*>\s*main\s*\{[^}]*\}/
        );

        expect(rule).not.toBeNull();
        expect(rule?.[0]).toContain('flex: 1 1 auto');
        expect(rule?.[0]).toContain('display: flex');
        expect(rule?.[0]).toContain('flex-direction: column');
        expect(rule?.[0]).toContain('min-height: 0');
        expect(rule?.[0]).toContain('overflow: hidden');
    });

    it('gives the canvas and inspector slots bounded flex heights so their children can scroll', () => {
        const css = readCss();
        const rule = css.match(
            /\.ap-site-editor__canvas-slot,\s*\n?\s*\.ap-site-editor__inspector-slot\s*\{[^}]*\}/
        );

        expect(rule).not.toBeNull();
        expect(rule?.[0]).toContain('flex: 1 1 auto');
        expect(rule?.[0]).toContain('display: flex');
        expect(rule?.[0]).toContain('flex-direction: column');
        expect(rule?.[0]).toContain('min-height: 0');
        expect(rule?.[0]).toContain('overflow: hidden');
    });

    it('makes the navigator slot a flex column for its own children (parent outlet is block, so fill props are omitted)', () => {
        const css = readCss();
        const rule = css.match(
            /\.ap-site-editor__navigator-slot\s*\{[^}]*\}/
        );

        expect(rule).not.toBeNull();
        expect(rule?.[0]).toContain('display: flex');
        expect(rule?.[0]).toContain('flex-direction: column');
    });
});
