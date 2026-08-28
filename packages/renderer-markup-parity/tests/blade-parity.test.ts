/**
 * Blade-vs-JS markup parity test (#704).
 *
 * Renders every shared fixture in `../fixtures.json` through the React and
 * Vue renderers and asserts the canonicalized markup matches the golden
 * file the Blade suite produces
 * (`packages/visual-editor-renderer-blade/tests/Feature/RendererMarkupParityTest.php`).
 *
 * When this fails, one of the three renderers has drifted from the shared
 * Blade partial contract. Fix the renderer that diverged; only regenerate
 * the goldens (`composer test:update-markup-goldens`) when the markup
 * change is intentional and the diff has been reviewed.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSSRApp, h as vueH } from 'vue';
import { renderToString as vueRenderToString } from '@vue/server-renderer';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import '../../visual-editor-renderer-vue/src/index';
import { BlockTree as VueBlockTree } from '../../visual-editor-renderer-vue/src/BlockTree';
import '../../visual-editor-renderer-react/src/index';
import { BlockTree as ReactBlockTree } from '../../visual-editor-renderer-react/src/BlockTree';
import type { Block } from '../../visual-editor-renderer-react/src/types';
import { canonicalizeHtml } from '../canonicalize';

const parityDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');

interface Fixture {
    name: string;
    tree: Block[];
}

interface KnownDivergence {
    id: string;
    issue: string;
    reason: string;
    dropClassTokensMatching: string;
}

const MANIFEST: {
    fixtures: Fixture[];
    knownDivergences?: KnownDivergence[];
} = JSON.parse(readFileSync(resolve(parityDir, 'fixtures.json'), 'utf8'));

const FIXTURES = MANIFEST.fixtures;

/**
 * Declared, documented divergences — see the `knownDivergences` block in
 * fixtures.json for the reason and tracking issue behind each one.
 */
const DROP_CLASS_PATTERNS = (MANIFEST.knownDivergences ?? []).map(
    (divergence) => divergence.dropClassTokensMatching
);

function readGolden(name: string): string {
    // `\r\n` guard: the goldens are compared as exact strings, so a
    // CRLF checkout must not read as a divergence.
    return readFileSync(resolve(parityDir, 'goldens', `${name}.txt`), 'utf8')
        .replace(/\r\n/g, '\n')
        .replace(/\n+$/, '');
}

/**
 * Delimiter separating the canonical markup from the canonical
 * per-instance CSS section in the golden. Mirrors
 * `markupParityCssDelimiter()` in the Pest suite.
 */
const CSS_SECTION_DELIMITER = '@@ renderer-instance-css @@';

/**
 * Renderer `<style data-ve-*>` attributes carrying the baseline /
 * global-styles / theme layer. That layer is a known, documented
 * divergence — Blade compiles it from theme.json
 * (`ThemeJsonTokensCompiler::compileLayoutRules()`), the JS renderers ship
 * a static `LAYOUT_BASELINE_CSS` — so it is dropped rather than compared,
 * to avoid encoding the same difference twice. Mirrors
 * `markupParityGlobalStyleAttrs()` in the Pest suite.
 */
const GLOBAL_STYLE_ATTRS = [
    'data-ve-global-styles',
    'data-ve-layout-baseline',
    'data-ve-theme',
    'data-ve-theme-tokens',
    'data-ve-block-library',
    'data-ve-block-library-theme',
];

function collapseWhitespace(value: string): string {
    return value.replace(/[ \t\r\n\f\v]+/g, ' ');
}

/**
 * Splits the renderer-injected style tags off the markup and returns the
 * markup (every `<style>/<link>/<script data-ve-*>` tag removed) plus the
 * captured per-instance CSS bodies. Blade folds column-width, photo-grid,
 * visibility, and flex-arbitrary rules into one `<style data-ve-responsive>`
 * block; the React/Vue renderers split them across several tags. Capturing
 * the bodies (minus the global/baseline layer) lets the rule *bodies* be
 * compared regardless of which tag each renderer delivers them in. Mirrors
 * `markupParityExtractCss()` in the Pest suite.
 */
function extractRendererCss(html: string): { markup: string; css: string } {
    const captured: string[] = [];

    let markup = html.replace(
        /<style\s+(data-ve-[a-z-]+)(?:="[^"]*")?\s*>([\s\S]*?)<\/style>/g,
        (_full, attr: string, body: string) => {
            if (!GLOBAL_STYLE_ATTRS.includes(attr)) {
                captured.push(body);
            }

            return '';
        }
    );

    markup = markup
        .replace(/<link\b[^>]*\sdata-ve-[a-z-]+[^>]*>/g, '')
        .replace(/<script\b[^>]*\sdata-ve-[a-z-]+[^>]*>[\s\S]*?<\/script>/g, '');

    return { markup, css: captured.join('') };
}

/**
 * Splits a CSS string into top-level rules, tracking brace depth so an
 * `@media (...) { ... }` block stays a single rule. Mirrors
 * `markupParitySplitCssRules()` in the Pest suite.
 */
function splitCssRules(css: string): string[] {
    const rules: string[] = [];
    let depth = 0;
    let start = 0;

    for (let i = 0; i < css.length; i++) {
        const ch = css[i];

        if (ch === '{') {
            depth++;
        } else if (ch === '}') {
            depth--;

            if (depth === 0) {
                rules.push(css.slice(start, i + 1));
                start = i + 1;
            }
        }
    }

    const tail = css.slice(start);

    if (tail.trim() !== '') {
        rules.push(tail);
    }

    return rules;
}

/**
 * Canonicalizes the captured per-instance CSS: split into top-level rules,
 * collapse insignificant whitespace, drop empties, and sort so delivery
 * differences (Blade folds every rule into one `<style data-ve-responsive>`
 * in push order; the JS renderers split them across tags in tree order)
 * never register as divergence — only a differing rule body does. Mirrors
 * `markupParityCanonicalCss()` in the Pest suite.
 */
function canonicalRendererCss(css: string): string {
    return splitCssRules(css)
        .map((rule) => collapseWhitespace(rule).trim())
        .filter((rule) => rule !== '')
        .sort()
        .join('\n');
}

function canonicalOutput(html: string): string {
    const { markup, css } = extractRendererCss(html);
    const canonicalMarkup = canonicalizeHtml(markup, DROP_CLASS_PATTERNS);
    const canonicalCss = canonicalRendererCss(css);

    return canonicalCss === ''
        ? canonicalMarkup
        : `${canonicalMarkup}\n${CSS_SECTION_DELIMITER}\n${canonicalCss}`;
}

function renderReact(tree: Block[]): string {
    return canonicalOutput(renderToStaticMarkup(createElement(ReactBlockTree, { tree })));
}

async function renderVue(tree: Block[]): Promise<string> {
    const app = createSSRApp({
        render: () => vueH(VueBlockTree, { tree }),
    });

    return canonicalOutput(await vueRenderToString(app));
}

describe('declared divergences', () => {
    // Mirror of the Pest `compiles every declared divergence pattern` test.
    // A source that compiles here but not under PCRE would silently keep the
    // token in the golden while the JS side dropped it, since preg_match()
    // reports a failed compile as `false` — indistinguishable from "no
    // match". Both sides assert compilation so the mismatch surfaces at the
    // point it is introduced. The manifest may legitimately be empty once
    // every renderer has converged (as it is after #714), so this asserts
    // "all declared patterns compile", not that any are declared.
    it('compiles every declared divergence pattern', () => {
        expect(Array.isArray(DROP_CLASS_PATTERNS)).toBe(true);

        for (const source of DROP_CLASS_PATTERNS) {
            expect(() => new RegExp(source)).not.toThrow();
        }
    });
});

describe('Blade/React/Vue markup parity', () => {
    it.each(FIXTURES)('React matches the Blade golden for $name', ({ name, tree }) => {
        expect(renderReact(tree)).toBe(readGolden(name));
    });

    it.each(FIXTURES)('Vue matches the Blade golden for $name', async ({ name, tree }) => {
        expect(await renderVue(tree)).toBe(readGolden(name));
    });
});
