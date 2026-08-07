/**
 * `canvas-color-tokens.ts` derives the two custom properties the canvas
 * body is painted with (`--ap-editor-canvas-bg` / `--ap-editor-canvas-fg`)
 * from the resolved theme.json's `styles.color` (#695).
 *
 * The regression these cover: nothing supplied those variables, so a
 * dark theme rendered a white canvas with white (invisible) headings.
 * The important half of the contract is the *absence* case — a theme
 * with no `styles.color` must leave the variables unset so the hex
 * fallbacks in `canvas-theme-tokens.css` still apply.
 */

import { describe, expect, it } from 'vitest';

import {
    buildCanvasColorTokenCss,
    canvasColorTokenStyle,
} from '../canvas-color-tokens';

describe('buildCanvasColorTokenCss', () => {
    it('emits both variables for a dark theme', () => {
        const css = buildCanvasColorTokenCss({
            color: { background: '#111827', text: '#FFFFFF' },
        });

        expect(css).toContain(':root {');
        expect(css).toContain('--ap-editor-canvas-bg: #111827;');
        expect(css).toContain('--ap-editor-canvas-fg: #FFFFFF;');
    });

    it('passes `var()` preset references through verbatim', () => {
        // theme.json commonly stores preset references rather than
        // literals; the emitter defines those presets on `:root` inside
        // the same document, so they resolve as-is.
        const css = buildCanvasColorTokenCss({
            color: {
                background: 'var(--wp--preset--color--base)',
                text: 'var(--wp--preset--color--text)',
            },
        });

        expect(css).toContain(
            '--ap-editor-canvas-bg: var(--wp--preset--color--base);'
        );
        expect(css).toContain(
            '--ap-editor-canvas-fg: var(--wp--preset--color--text);'
        );
    });

    it('leaves the variables unset when the theme declares no styles.color', () => {
        expect(buildCanvasColorTokenCss(undefined)).toBeNull();
        expect(buildCanvasColorTokenCss({})).toBeNull();
        expect(buildCanvasColorTokenCss({ color: {} })).toBeNull();
        expect(
            buildCanvasColorTokenCss({ typography: { fontSize: '16px' } })
        ).toBeNull();
    });

    it('ignores a non-object styles.color node', () => {
        expect(buildCanvasColorTokenCss({ color: null })).toBeNull();
        expect(buildCanvasColorTokenCss({ color: 'dark' })).toBeNull();
        expect(buildCanvasColorTokenCss({ color: ['#000'] })).toBeNull();
    });

    it('accepts WordPress preset shorthand as well as the CSS form', () => {
        // theme.json may carry either; the handbook recommends the
        // shorthand, and `/global-styles/base` returns the manifest
        // verbatim, so both shapes reach this module.
        const css = buildCanvasColorTokenCss({
            color: {
                background: 'var:preset|color|base',
                text: 'var:custom|contrast|primary',
            },
        });

        expect(css).toContain(
            '--ap-editor-canvas-bg: var(--wp--preset--color--base);'
        );
        expect(css).toContain(
            '--ap-editor-canvas-fg: var(--wp--custom--contrast--primary);'
        );
    });

    it('emits only the half the theme declares', () => {
        // A light background keeps the default foreground legible, so
        // nothing is synthesized and the variable stays unset.
        const bgOnly = buildCanvasColorTokenCss({
            color: { background: '#fafafa' },
        });

        expect(bgOnly).toContain('--ap-editor-canvas-bg: #fafafa;');
        expect(bgOnly).not.toContain('--ap-editor-canvas-fg');

        const fgOnly = buildCanvasColorTokenCss({
            color: { text: '#ffffff' },
        });

        expect(fgOnly).toContain('--ap-editor-canvas-fg: #ffffff;');
        expect(fgOnly).not.toContain('--ap-editor-canvas-bg');
    });

    /*
     * A dark background with no paired text color would leave body text
     * on the `#1f2937` default and headings on `#111827` — the exact
     * invisible-on-dark failure this change exists to remove. The pair
     * gets completed rather than half-emitted.
     */
    describe('unpaired background', () => {
        it('synthesizes a legible foreground for a dark background', () => {
            const css = buildCanvasColorTokenCss({
                color: { background: '#111827' },
            });

            expect(css).toContain('--ap-editor-canvas-bg: #111827;');
            expect(css).toContain('--ap-editor-canvas-fg: #ffffff;');
        });

        it('leaves headings to inherit the synthesized foreground', () => {
            // No heading token means the baseline chains
            // `heading-fg` → `fg`, so headings follow the same legible
            // color rather than the light `#111827` default.
            const css = buildCanvasColorTokenCss({
                color: { background: '#111827' },
            });

            expect(css).not.toContain('--ap-editor-canvas-heading-fg');
        });

        it('never overrides a text color the theme declared', () => {
            // An explicit low-contrast pairing is the theme's decision;
            // the canvas should render what the front end renders.
            const css = buildCanvasColorTokenCss({
                color: { background: '#111827', text: '#1f2937' },
            });

            expect(css).toContain('--ap-editor-canvas-fg: #1f2937;');
            expect(css).not.toContain('#ffffff');
        });

        it('does not guess for a background it cannot measure', () => {
            // A `var()` reference resolves only in the browser. Themes
            // using presets have a resolved palette and declare `text`
            // alongside `background` in practice.
            const css = buildCanvasColorTokenCss({
                color: { background: 'var(--wp--preset--color--base)' },
            });

            expect(css).toContain(
                '--ap-editor-canvas-bg: var(--wp--preset--color--base);'
            );
            expect(css).not.toContain('--ap-editor-canvas-fg');
        });

        /*
         * The contrast helpers are hex-only, but every one of these
         * syntaxes passes `ALLOWED_VALUE` and gets emitted. Without
         * normalisation the derivation silently no-ops and the canvas
         * keeps its `#1f2937` default on a near-black ground — #695
         * all over again, just reached via a different value syntax.
         */
        it.each([
            ['rgb()', 'rgb(17 24 39)'],
            ['legacy rgb()', 'rgb(17, 24, 39)'],
            ['opaque rgba()', 'rgba(17, 24, 39, 1)'],
            ['hsl()', 'hsl(220 39% 11%)'],
            ['a named color', 'black'],
            ['opaque 8-digit hex', '#111827ff'],
        ])('derives a foreground for a dark background in %s', (_label, value) => {
            const css = buildCanvasColorTokenCss({ color: { background: value } });

            expect(css).toContain(`--ap-editor-canvas-bg: ${value};`);
            expect(css).toContain('--ap-editor-canvas-fg: #ffffff;');
        });

        it('leaves a light non-hex background on the legible default', () => {
            // Derivation is not blanket normalisation — it only fires
            // when the default would actually be unreadable.
            const css = buildCanvasColorTokenCss({
                color: { background: 'rgb(250 250 250)' },
            });

            expect(css).toContain('--ap-editor-canvas-bg: rgb(250 250 250);');
            expect(css).not.toContain('--ap-editor-canvas-fg');
        });

        it('emits the background verbatim rather than the normalised hex', () => {
            // Normalisation exists to measure contrast; the theme's own
            // syntax is what reaches the stylesheet.
            const css = buildCanvasColorTokenCss({
                color: { background: 'hsl(220 39% 11%)' },
            });

            expect(css).toContain('--ap-editor-canvas-bg: hsl(220 39% 11%);');
            expect(css).not.toContain('#111827;');
        });

        it('does not guess for a translucent background', () => {
            // What a translucent color renders as depends on whatever is
            // painted behind it, so there is nothing to measure.
            const css = buildCanvasColorTokenCss({
                color: { background: 'rgba(17, 24, 39, 0.5)' },
            });

            expect(css).toContain('--ap-editor-canvas-bg: rgba(17, 24, 39, 0.5);');
            expect(css).not.toContain('--ap-editor-canvas-fg');
        });
    });

    it('admits the CSS color shapes a theme.json legitimately uses', () => {
        for (const value of [
            '#111827',
            '#fff',
            'rebeccapurple',
            'var(--wp--preset--color--base)',
            'rgb(0 0 0 / 50%)',
            'color-mix(in srgb, #111827 50%, white)',
        ]) {
            expect(
                buildCanvasColorTokenCss({ color: { background: value } })
            ).toContain(`--ap-editor-canvas-bg: ${value};`);
        }
    });

    it('drops values that would swallow the rest of the rule', () => {
        // Both of these slip past a naive `[{};<>]` denylist and take the
        // whole `:root` entry down with them — an unterminated comment
        // and an unbalanced paren each consume to end-of-stylesheet.
        // Dropping just the value keeps the `var()` fallbacks in play.
        for (const value of ['#111 /*', 'var(--wp--preset--color--base', '#fff )']) {
            expect(
                buildCanvasColorTokenCss({
                    color: { background: value, text: '#000' },
                })
            ).not.toContain('--ap-editor-canvas-bg');
        }

        // ...and the surviving half is still emitted.
        expect(
            buildCanvasColorTokenCss({
                color: { background: '#111 /*', text: '#000' },
            })
        ).toContain('--ap-editor-canvas-fg: #000;');
    });

    it('drops empty, non-string, and unsafe values rather than emitting them', () => {
        expect(
            buildCanvasColorTokenCss({ color: { background: '   ', text: 42 } })
        ).toBeNull();

        // A value that could close the declaration block is dropped, not
        // sanitized — theme.json colors never legitimately contain these.
        const css = buildCanvasColorTokenCss({
            color: { background: '#fff; } body { display: none;', text: '#000' },
        });

        expect(css).not.toContain('display: none');
        expect(css).not.toContain('--ap-editor-canvas-bg');
        expect(css).toContain('--ap-editor-canvas-fg: #000;');
    });

    it('trims surrounding whitespace off a declared value', () => {
        expect(
            buildCanvasColorTokenCss({ color: { background: '  #111827  ' } })
        ).toContain('--ap-editor-canvas-bg: #111827;');
    });
});

/*
 * #695, second half — `DEFAULT_CANVAS_STYLES` pins headings to a
 * hardcoded `#111827` at a specificity a theme's bare `h1`..`h6` rules
 * can't beat, so on a dark canvas headings render against a ground of
 * nearly the same color. The baseline now chains through
 * `--ap-editor-canvas-heading-fg` → `--ap-editor-canvas-fg` → the
 * original light default; these cover which of those the theme picks.
 */
describe('buildCanvasColorTokenCss — heading color', () => {
    it('prefers the `heading` element group', () => {
        const css = buildCanvasColorTokenCss({
            color: { text: '#FFFFFF' },
            elements: { heading: { color: { text: '#E5E7EB' } } },
        });

        expect(css).toContain('--ap-editor-canvas-heading-fg: #E5E7EB;');
    });

    it('falls back to h1..h6 when every declared level agrees', () => {
        const css = buildCanvasColorTokenCss({
            elements: {
                h1: { color: { text: '#E5E7EB' } },
                h2: { color: { text: '#E5E7EB' } },
            },
        });

        expect(css).toContain('--ap-editor-canvas-heading-fg: #E5E7EB;');
    });

    it('leaves the token unset when per-level colors diverge', () => {
        // One variable can't express six colors; leaving it unset makes
        // headings inherit the canvas foreground rather than pick a
        // level's color arbitrarily.
        const css = buildCanvasColorTokenCss({
            color: { text: '#FFFFFF' },
            elements: {
                h1: { color: { text: '#E5E7EB' } },
                h2: { color: { text: '#9CA3AF' } },
            },
        });

        expect(css).toContain('--ap-editor-canvas-fg: #FFFFFF;');
        expect(css).not.toContain('--ap-editor-canvas-heading-fg');
    });

    it('leaves the token unset for a theme that styles headings without color', () => {
        // The real-world case: `elements.h1..h6` carry typography only,
        // so headings must inherit the canvas foreground.
        const css = buildCanvasColorTokenCss({
            color: { background: '#111827', text: '#FFFFFF' },
            elements: {
                h1: { typography: { fontWeight: '600' } },
                h2: { typography: { fontWeight: '600' } },
            },
        });

        expect(css).toContain('--ap-editor-canvas-fg: #FFFFFF;');
        expect(css).not.toContain('--ap-editor-canvas-heading-fg');
    });

    it('emits heading colors even when the theme declares no canvas colors', () => {
        const css = buildCanvasColorTokenCss({
            elements: { heading: { color: { text: '#E5E7EB' } } },
        });

        expect(css).toContain('--ap-editor-canvas-heading-fg: #E5E7EB;');
        expect(css).not.toContain('--ap-editor-canvas-bg');
    });

    it('ignores malformed element nodes', () => {
        expect(
            buildCanvasColorTokenCss({ elements: { heading: 'white', h1: null } })
        ).toBeNull();
        expect(buildCanvasColorTokenCss({ elements: [] })).toBeNull();
    });
});

describe('canvasColorTokenStyle', () => {
    it('wraps the rule in the BlockCanvas style-entry shape', () => {
        expect(
            canvasColorTokenStyle({ color: { background: '#111827' } })
        ).toEqual({
            css: expect.stringContaining('--ap-editor-canvas-bg: #111827;'),
        });
    });

    it('is null when there is nothing to emit, so no entry is appended', () => {
        expect(canvasColorTokenStyle({})).toBeNull();
    });
});
