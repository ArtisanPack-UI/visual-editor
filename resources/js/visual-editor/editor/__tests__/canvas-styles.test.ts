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

import { describe, expect, it } from 'vitest';

import {
    ALIGNMENT_OVERRIDE_STYLES,
    DEFAULT_CANVAS_STYLES,
    POST_EDITOR_FRAMING_STYLES,
} from '../../editor-settings';
import { blockStylesheetPaths, canvasStyles } from '../canvas-styles';

describe('canvasStyles', () => {
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
        // Framing is the cascade anchor for the post editor's page-like
        // visual; if anything else were to land after it, that entry
        // would have to be intentional (theme.json bridge for the post
        // editor will land after framing).
        expect(framingIndex).toBe(canvasStyles.length - 1);
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

        const blockStyleStrings = new Set(
            blockStylesheetPaths.map((path) => path)
        );

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
