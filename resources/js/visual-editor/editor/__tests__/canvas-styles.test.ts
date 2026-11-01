/**
 * `canvas-styles.ts` assembles the stylesheet bundle handed to
 * `BlockCanvas`'s `styles` prop so the iframe canvas isn't styled by
 * browser defaults (#347).
 *
 * The individual `@wordpress/*` sheets are resolved by Vite's `?inline`
 * transform at build/dev time; under vitest (`css: false`) those
 * imports collapse to empty strings, so this suite asserts the bundle's
 * *structure and ordering* rather than re-checking Gutenberg's CSS
 * text. `DEFAULT_CANVAS_STYLES` is a real TypeScript constant, so the
 * cascade-order anchor is still verifiable here. The block-stylesheet
 * glob (#566) also runs under vitest, so we can verify *which* paths
 * the glob discovers even though their CSS text is empty.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { LAYOUT_BASELINE_CSS as REACT_LAYOUT_BASELINE_CSS } from '../../../../../packages/visual-editor-renderer-react/src/support/layoutBaselineCss';
import { LAYOUT_BASELINE_CSS as VUE_LAYOUT_BASELINE_CSS } from '../../../../../packages/visual-editor-renderer-vue/src/support/layoutBaselineCss';
import {
    ALIGNMENT_OVERRIDE_STYLES,
    CANVAS_LAYOUT_STYLES,
    COMPOSED_CHROME_STYLES,
    DEFAULT_CANVAS_STYLES,
    LAYOUT_BASELINE_STYLES,
    POST_EDITOR_FRAMING_STYLES,
} from '../../editor-settings';
import {
    FLEX_LAYOUT_STYLE,
    blockStylesheetPaths,
    canvasStyles,
    formatStylesheetPaths,
} from '../canvas-styles';

describe('canvasStyles', () => {
    it('spaces flex and grid layout children with the block gap, as the front end does (#883)', () => {
        const css = canvasStyles.map((entry) => entry.css).join('\n');

        expect(css).toContain(':where(.is-layout-flex) { gap: var(--wp--style--block-gap, 0.5em); }');
        expect(css).toContain(':where(.is-layout-grid) { gap: var(--wp--style--block-gap, 0.5em); }');
    });

    it('exposes every sheet as a `{ css: string }` entry — the shape BlockCanvas injects', () => {
        expect(canvasStyles.length).toBeGreaterThan(0);

        for (const entry of canvasStyles) {
            expect(Object.keys(entry)).toEqual(['css']);
            expect(typeof entry.css).toBe('string');
        }
    });

    it('places DEFAULT_CANVAS_STYLES before POST_EDITOR_FRAMING_STYLES so the framing wins the cascade', () => {
        const defaultsIndex = canvasStyles.findIndex(
            (entry) => entry.css === DEFAULT_CANVAS_STYLES
        );
        const framingIndex = canvasStyles.findIndex(
            (entry) => entry.css === POST_EDITOR_FRAMING_STYLES
        );

        expect(defaultsIndex).toBeGreaterThanOrEqual(0);
        expect(framingIndex).toBeGreaterThan(defaultsIndex);
    });

    it('places COMPOSED_CHROME_STYLES after the framing so it can undo it inside chrome regions', () => {
        const framingIndex = canvasStyles.findIndex(
            (entry) => entry.css === POST_EDITOR_FRAMING_STYLES
        );
        const chromeIndex = canvasStyles.findIndex(
            (entry) => entry.css === COMPOSED_CHROME_STYLES
        );

        // The framing entry exists to frame post *content* — 48px padding
        // and a 720px column. Composed-view chrome (#655) renders inside
        // the same canvas but is a site header/footer, so it has to win
        // against that framing; hence it lands after.
        //
        // Only the #623 ribbon sheet follows it: the ribbon is editor
        // chrome that happens to render in the canvas and shares no
        // selectors with the chrome regions, so it cannot outrank the
        // reset. Any *other* future entry appended past the chrome reset
        // would, so this pins the tail to exactly one trailing sheet.
        expect(chromeIndex).toBeGreaterThan(framingIndex);
        expect(chromeIndex).toBe(canvasStyles.length - 2);
    });

    it('keeps the alignment overrides between the canvas defaults and the post-editor framing', () => {
        const defaultsIndex = canvasStyles.findIndex(
            (entry) => entry.css === DEFAULT_CANVAS_STYLES
        );
        const alignmentIndex = canvasStyles.findIndex(
            (entry) => entry.css === ALIGNMENT_OVERRIDE_STYLES
        );
        const framingIndex = canvasStyles.findIndex(
            (entry) => entry.css === POST_EDITOR_FRAMING_STYLES
        );

        expect(alignmentIndex).toBeGreaterThan(defaultsIndex);
        expect(alignmentIndex).toBeLessThan(framingIndex);
    });
});

describe('layout baseline parity with the front end (#1040)', () => {
    const bladeTemplate = readFileSync(
        resolve(
            __dirname,
            '../../../../../packages/visual-editor-renderer-blade/resources/views/components/blocks-styles.blade.php'
        ),
        'utf8'
    );

    /** Theme `blockGap` lands on the wrapper, which isn't `body` in the site editor. */
    const toFrontEndRule = (rule: string): string =>
        rule.replace(':where(.editor-styles-wrapper)', ':where(body)');

    const canvasRules = LAYOUT_BASELINE_STYLES.trim().split('\n').map(toFrontEndRule);

    /**
     * Rules that lay out a container's children or carry its gap. Float
     * rules are left out because `ALIGNMENT_OVERRIDE_STYLES` covers them.
     */
    const isLayoutRule = (rule: string): boolean =>
        /is-layout-(flow|constrained|flex|grid)|--ap-root-block-gap/.test(rule) &&
        !/align(left|right)/.test(rule);

    it('has every Blade layout rule, in the same order', () => {
        const baseline = /<style data-ve-layout-baseline>([\s\S]*?)<\/style>/.exec(bladeTemplate);

        expect(baseline).not.toBeNull();
        expect(canvasRules).toEqual(
            (baseline?.[1] ?? '').trim().split('\n').filter(isLayoutRule)
        );
    });

    it.each([
        ['React', REACT_LAYOUT_BASELINE_CSS],
        ['Vue', VUE_LAYOUT_BASELINE_CSS],
    ])('has every %s layout rule, in the same order', (_renderer, css) => {
        expect(canvasRules).toEqual(
            css
                .split('\n')
                .filter((rule) => rule.startsWith(':where(') || rule.startsWith('.is-layout-'))
                .filter(isLayoutRule)
        );
    });
});

describe('element margins in the canvas (#1040)', () => {
    it('leaves margins out of the specific-selector defaults, where they would beat the layout rules', () => {
        expect(DEFAULT_CANVAS_STYLES).not.toMatch(/\bmargin\s*:/);
    });

    it('declares them at zero specificity, ahead of the baseline they tie with', () => {
        for (const selector of ['p', ':is(h1, h2, h3, h4, h5, h6)', 'blockquote', ':is(ul, ol)', 'pre', 'hr']) {
            expect(CANVAS_LAYOUT_STYLES).toContain(`:where(.editor-styles-wrapper ${selector}) { margin:`);
        }

        expect(CANVAS_LAYOUT_STYLES.endsWith(LAYOUT_BASELINE_STYLES)).toBe(true);
        expect(CANVAS_LAYOUT_STYLES.length).toBeGreaterThan(LAYOUT_BASELINE_STYLES.length);
    });

    it('prints the baseline before the Flex Layout sheet, so its `.ap-flex` reset wins the tie', () => {
        const layoutIndex = canvasStyles.findIndex((entry) => entry.css === CANVAS_LAYOUT_STYLES);

        expect(layoutIndex).toBeGreaterThanOrEqual(0);
        expect(canvasStyles.indexOf(FLEX_LAYOUT_STYLE)).toBeGreaterThan(layoutIndex);
    });

    it('keeps Accordion and Tabs panel resets off blocks nested in a Group', () => {
        const css = canvasStyles.map((entry) => entry.css).join('\n');

        expect(css).toContain('.ap-accordion__body > .block-editor-block-list__block');
        expect(css).toContain('.ap-tab-section > .block-editor-block-list__block');
        expect(css).not.toContain('.ap-accordion__body .block-editor-block-list__block');
        expect(css).not.toContain('.ap-tab-section .block-editor-block-list__block');
    });
});

describe('root block spacing in the post editor (#1040)', () => {
    const rootGapSelector =
        '.block-editor-block-list__layout.is-root-container) > * + :where(:not(.block-list-appender))';

    it('spaces root blocks with the block gap, leaving out the appender', () => {
        expect(POST_EDITOR_FRAMING_STYLES).toContain(`:where(.editor-styles-wrapper ${rootGapSelector}`);
    });

    it('undoes that gap inside composed-view chrome', () => {
        expect(COMPOSED_CHROME_STYLES).toContain(`:where(.ap-visual-editor__chrome ${rootGapSelector}`);
    });
});

describe('blockStylesheetPaths (#566 glob)', () => {
    it('discovers every block-authored stylesheet by globbing the blocks directory', () => {
        // The glob is `../blocks/*/*.css`. If the wiring breaks (wrong
        // pattern, eager:false, missing query) the result collapses to
        // zero entries, so a non-empty assertion is the load-bearing
        // guard. We require the per-block sheets cited in the issue
        // (callout, breadcrumbs) plus representative entries from the
        // interactive families and the shared social-icons baseline,
        // so the test fails loudly if a future refactor narrows the
        // glob or breaks the path shape.
        expect(blockStylesheetPaths.length).toBeGreaterThan(0);

        const matchers: ReadonlyArray<RegExp> = [
            /\/blocks\/breadcrumbs\/breadcrumbs\.css$/,
            /\/blocks\/callout\/callout\.css$/,
            /\/blocks\/accordion\/accordion\.css$/,
            /\/blocks\/tabs\/tabs\.css$/,
            /\/blocks\/grid\/grid\.css$/,
            /\/blocks\/marquee\/marquee\.css$/,
            /\/blocks\/_shared\/social-icons\.css$/,
        ];

        for (const matcher of matchers) {
            expect(
                blockStylesheetPaths.some((path) => matcher.test(path))
            ).toBe(true);
        }
    });

    it('returns paths in stable alphabetical order so the cascade is deterministic across builds', () => {
        const sorted = [...blockStylesheetPaths].sort();
        expect(blockStylesheetPaths).toEqual(sorted);
    });

    it('places the block-stylesheet block after the @wordpress sheets and before DEFAULT_CANVAS_STYLES', () => {
        // The block-authored CSS group sits between the @wordpress
        // sheets (which set Gutenberg's baseline) and the package's
        // own DEFAULT_CANVAS_STYLES (typographic anchor). Verifying
        // the indices keeps a future refactor from quietly moving the
        // block group past the package defaults, which would let
        // block-authored rules override the typographic baseline.
        const defaultsIndex = canvasStyles.findIndex(
            (entry) => entry.css === DEFAULT_CANVAS_STYLES
        );
        expect(defaultsIndex).toBeGreaterThanOrEqual(0);

        const blockStyleStrings = new Set(blockStylesheetPaths);

        // Every discovered block stylesheet must land in canvasStyles
        // ahead of DEFAULT_CANVAS_STYLES. We can't equality-compare
        // empty CSS strings (vitest collapses them all to ''), so we
        // assert the count of block-group entries instead: at least
        // one per discovered path should sit before defaults.
        const entriesBeforeDefaults = canvasStyles.slice(0, defaultsIndex);
        expect(entriesBeforeDefaults.length).toBeGreaterThanOrEqual(
            blockStyleStrings.size
        );
    });
});

describe('format stylesheets in the canvas (#1039)', () => {
    it('discovers every stylesheet under formats/', () => {
        expect(formatStylesheetPaths).toContain('../formats/inline-icon/inline-icon.css');
    });

    it('hands them to the iframe ahead of the canvas defaults', () => {
        const defaultsIndex = canvasStyles.findIndex(
            (entry) => entry.css === DEFAULT_CANVAS_STYLES
        );

        expect(defaultsIndex).toBeGreaterThanOrEqual(
            blockStylesheetPaths.length + formatStylesheetPaths.length
        );
    });
});

describe('floated alignments in the canvas (#819)', () => {
    it('floats alignleft / alignright children of constrained and flow layouts', () => {
        expect(ALIGNMENT_OVERRIDE_STYLES).toMatch(/\.is-layout-constrained > \.alignleft \{\s*float: left;/);
        expect(ALIGNMENT_OVERRIDE_STYLES).toMatch(/\.is-layout-constrained > \.alignright \{\s*float: right;/);
        expect(ALIGNMENT_OVERRIDE_STYLES).toMatch(/\.is-layout-flow > \.alignleft \{\s*float: left;/);
        expect(ALIGNMENT_OVERRIDE_STYLES).toMatch(/is-root-container > \.wp-block\.alignleft \{\s*float: left;/);
    });

    it('excludes floated blocks from the root content framing', () => {
        for (const css of [POST_EDITOR_FRAMING_STYLES, COMPOSED_CHROME_STYLES]) {
            expect(css).toContain(
                'is-root-container > .wp-block:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright)'
            );
        }
    });
});

describe('floats stack at Mobile in the canvas (#820)', () => {
    it('unfloats aligned blocks at the mobile breakpoint', () => {
        expect(ALIGNMENT_OVERRIDE_STYLES).toMatch(/@media \(max-width: 767px\) \{[\s\S]*float: none;/);
    });
});
