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
import type { TemplatePartRecord } from '../../visual-editor-renderer-react/src/templateParts';
import type { Block } from '../../visual-editor-renderer-react/src/types';
import { canonicalizeHtml } from '../canonicalize';

const parityDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');

interface Fixture {
    name: string;
    tree: Block[];
    /**
     * Optional template-part records, handed to `BlockTree`'s
     * `templateParts` prop. The Pest suite serves the same records through
     * a TemplatePartResolver stub (`markupParityBindTemplateParts()`).
     */
    templateParts?: TemplatePartRecord[];
}

/**
 * One declared, documented renderer divergence. Every entry carries
 * exactly one of the `drop*` keys; `fixturesMatching` (a regex source
 * tested against the fixture name) narrows it to the fixtures it applies
 * to, so a divergence declared for the navigation overlay cannot mask the
 * same drift anywhere else. Mirrors the Pest suite's reading of the
 * manifest.
 */
interface KnownDivergence {
    id: string;
    issue: string;
    reason: string;
    fixturesMatching?: string;
    /** Class tokens matching this regex are dropped from every `class`. */
    dropClassTokensMatching?: string;
    /** Attributes whose (lowercased) name matches this regex are dropped. */
    dropAttributesMatching?: string;
    /** Elements whose canonical open tag matches this regex are dropped, subtree included. */
    dropElementsMatching?: string;
    /** A renderer `<style data-ve-*>` tag (exact attribute name) dropped rather than compared. */
    dropRendererStyleTag?: string;
}

const DROP_KEYS = [
    'dropClassTokensMatching',
    'dropAttributesMatching',
    'dropElementsMatching',
    'dropRendererStyleTag',
] as const;

const MANIFEST: {
    fixtures: Fixture[];
    knownDivergences?: KnownDivergence[];
} = JSON.parse(readFileSync(resolve(parityDir, 'fixtures.json'), 'utf8'));

const FIXTURES = MANIFEST.fixtures;

/**
 * Declared, documented divergences — see the `knownDivergences` block in
 * fixtures.json for the reason and tracking issue behind each one.
 */
const DIVERGENCES: KnownDivergence[] = MANIFEST.knownDivergences ?? [];

interface FixtureDivergences {
    classes: string[];
    attributes: string[];
    elements: string[];
    styleTags: string[];
}

/**
 * The declared divergences that apply to one fixture, grouped by kind.
 * Mirrors `markupParityDivergencesFor()` in the Pest suite.
 */
function divergencesFor(name: string): FixtureDivergences {
    const applicable = DIVERGENCES.filter(
        (divergence) =>
            divergence.fixturesMatching === undefined ||
            new RegExp(divergence.fixturesMatching).test(name)
    );

    const pick = (key: (typeof DROP_KEYS)[number]): string[] =>
        applicable
            .map((divergence) => divergence[key])
            .filter((value): value is string => typeof value === 'string');

    return {
        classes: pick('dropClassTokensMatching'),
        attributes: pick('dropAttributesMatching'),
        elements: pick('dropElementsMatching'),
        styleTags: pick('dropRendererStyleTag'),
    };
}

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
function extractRendererCss(
    html: string,
    droppedStyleTags: string[] = []
): { markup: string; css: string } {
    const captured: string[] = [];

    let markup = html.replace(
        /<style\s+(data-ve-[a-z-]+)(?:="[^"]*")?\s*>([\s\S]*?)<\/style>/g,
        (_full, attr: string, body: string) => {
            if (!GLOBAL_STYLE_ATTRS.includes(attr) && !droppedStyleTags.includes(attr)) {
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

function canonicalOutput(html: string, name: string): string {
    const divergences = divergencesFor(name);
    const { markup, css } = extractRendererCss(html, divergences.styleTags);
    const canonicalMarkup = canonicalizeHtml(markup, divergences.classes, {
        dropAttributes: divergences.attributes,
        dropElements: divergences.elements,
    });
    const canonicalCss = canonicalRendererCss(css);

    return canonicalCss === ''
        ? canonicalMarkup
        : `${canonicalMarkup}\n${CSS_SECTION_DELIMITER}\n${canonicalCss}`;
}

function renderReact({ name, tree, templateParts }: Fixture): string {
    return canonicalOutput(
        renderToStaticMarkup(createElement(ReactBlockTree, { tree, templateParts })),
        name
    );
}

async function renderVue({ name, tree, templateParts }: Fixture): Promise<string> {
    const app = createSSRApp({
        render: () => vueH(VueBlockTree, { tree, templateParts }),
    });

    return canonicalOutput(await vueRenderToString(app), name);
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
        expect(Array.isArray(DIVERGENCES)).toBe(true);

        for (const divergence of DIVERGENCES) {
            for (const key of [
                'dropClassTokensMatching',
                'dropAttributesMatching',
                'dropElementsMatching',
                'fixturesMatching',
            ] as const) {
                const source = divergence[key];

                if (source !== undefined) {
                    expect(() => new RegExp(source)).not.toThrow();
                }
            }
        }
    });

    // Mirror of the Pest `declares every divergence with a reason and one
    // drop rule` test: a malformed entry would otherwise silently drop
    // nothing (or everything) on one side only.
    it('declares every divergence with a reason and exactly one drop rule', () => {
        for (const divergence of DIVERGENCES) {
            expect(typeof divergence.id).toBe('string');
            expect(divergence.issue).toBeTruthy();
            expect(divergence.reason).toBeTruthy();
            expect(DROP_KEYS.filter((key) => divergence[key] !== undefined)).toHaveLength(1);
        }
    });

    // A `fixturesMatching` scope that matches nothing is a stale entry: the
    // divergence it documents is no longer exercised by any fixture.
    it('scopes every divergence to at least one fixture', () => {
        for (const divergence of DIVERGENCES) {
            if (divergence.fixturesMatching === undefined) {
                continue;
            }

            const scope = new RegExp(divergence.fixturesMatching);

            expect(
                FIXTURES.some(({ name }) => scope.test(name)),
                `${divergence.id} matches no fixture`
            ).toBe(true);
        }
    });
});

describe('Blade/React/Vue markup parity', () => {
    it.each(FIXTURES)('React matches the Blade golden for $name', (fixture) => {
        expect(renderReact(fixture)).toBe(readGolden(fixture.name));
    });

    it.each(FIXTURES)('Vue matches the Blade golden for $name', async (fixture) => {
        expect(await renderVue(fixture)).toBe(readGolden(fixture.name));
    });
});
