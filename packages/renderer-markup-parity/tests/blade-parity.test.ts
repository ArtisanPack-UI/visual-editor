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
 * Strips the renderer-injected `<style data-ve-*>` blocks.
 *
 * This check covers block markup only. The layout baseline / global-styles
 * CSS is a known, documented divergence between Blade (which emits it from
 * `ThemeJsonTokensCompiler::compileLayoutRules()`, gated on theme.json
 * layout sizes) and the JS renderers (which ship `LAYOUT_BASELINE_CSS`), so
 * comparing it here would only encode that difference twice.
 *
 * Mirrors `stripRendererStyleTags()` in the Pest suite.
 */
function stripRendererStyleTags(html: string): string {
    return html.replace(/<style\s+data-ve-[^>]*>[\s\S]*?<\/style>/g, '');
}

function renderReact(tree: Block[]): string {
    return canonicalizeHtml(
        stripRendererStyleTags(
            renderToStaticMarkup(createElement(ReactBlockTree, { tree }))
        ),
        DROP_CLASS_PATTERNS
    );
}

async function renderVue(tree: Block[]): Promise<string> {
    const app = createSSRApp({
        render: () => vueH(VueBlockTree, { tree }),
    });

    return canonicalizeHtml(
        stripRendererStyleTags(await vueRenderToString(app)),
        DROP_CLASS_PATTERNS
    );
}

describe('Blade/React/Vue markup parity', () => {
    it.each(FIXTURES)('React matches the Blade golden for $name', ({ name, tree }) => {
        expect(renderReact(tree)).toBe(readGolden(name));
    });

    it.each(FIXTURES)('Vue matches the Blade golden for $name', async ({ name, tree }) => {
        expect(await renderVue(tree)).toBe(readGolden(name));
    });
});
