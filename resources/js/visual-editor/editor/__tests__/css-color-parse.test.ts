/**
 * `css-color-parse.ts` normalises the opaque CSS color syntaxes a
 * theme.json may carry into the `#rrggbb` form the WCAG helpers can
 * measure (#695).
 *
 * The regression these guard: `canvas-color-tokens.ts` accepts every CSS
 * color syntax but the contrast math is hex-only, so a dark
 * `rgb()` / `hsl()` / named background with no paired text color emitted
 * the background while leaving the foreground on the light default —
 * the same invisible-text failure #695 exists to remove, reached through
 * a different value syntax.
 *
 * The other half of the contract is the `null` cases: a value with no
 * fixed opaque color must not be guessed at.
 */

import { describe, expect, it } from 'vitest';

import { toMeasurableHex } from '../css-color-parse';

describe('toMeasurableHex', () => {
    it('passes hex through, expanding the shorthand form', () => {
        expect(toMeasurableHex('#111827')).toBe('#111827');
        expect(toMeasurableHex('#FFF')).toBe('#ffffff');
        expect(toMeasurableHex('#1a2b3c')).toBe('#1a2b3c');
    });

    it('resolves alpha-bearing hex only when fully opaque', () => {
        expect(toMeasurableHex('#111827ff')).toBe('#111827');
        expect(toMeasurableHex('#111f')).toBe('#111111');

        // Translucent renders against an unknown backdrop.
        expect(toMeasurableHex('#11182780')).toBeNull();
        expect(toMeasurableHex('#1118')).toBeNull();
    });

    it('rejects hex of a length CSS does not define', () => {
        expect(toMeasurableHex('#12')).toBeNull();
        expect(toMeasurableHex('#12345')).toBeNull();
        expect(toMeasurableHex('#1234567')).toBeNull();
        expect(toMeasurableHex('#zzzzzz')).toBeNull();
    });

    it('resolves named colors case-insensitively', () => {
        expect(toMeasurableHex('black')).toBe('#000000');
        expect(toMeasurableHex('White')).toBe('#ffffff');
        expect(toMeasurableHex('REBECCAPURPLE')).toBe('#663399');
        expect(toMeasurableHex('midnightblue')).toBe('#191970');

        // Both spellings of the grey family are defined.
        expect(toMeasurableHex('gray')).toBe('#808080');
        expect(toMeasurableHex('grey')).toBe('#808080');
    });

    it('treats `transparent` and keywords as unmeasurable', () => {
        expect(toMeasurableHex('transparent')).toBeNull();
        expect(toMeasurableHex('currentColor')).toBeNull();
        expect(toMeasurableHex('inherit')).toBeNull();
    });

    it('resolves rgb() in both the legacy and modern syntaxes', () => {
        expect(toMeasurableHex('rgb(17, 24, 39)')).toBe('#111827');
        expect(toMeasurableHex('rgb(17 24 39)')).toBe('#111827');
        expect(toMeasurableHex('rgba(17, 24, 39, 1)')).toBe('#111827');
        expect(toMeasurableHex('rgb(17 24 39 / 100%)')).toBe('#111827');
    });

    it('resolves percentage rgb() channels', () => {
        expect(toMeasurableHex('rgb(0% 0% 0%)')).toBe('#000000');
        expect(toMeasurableHex('rgb(100%, 100%, 100%)')).toBe('#ffffff');
    });

    it('rejects translucent rgb()', () => {
        expect(toMeasurableHex('rgba(17, 24, 39, 0.5)')).toBeNull();
        expect(toMeasurableHex('rgb(17 24 39 / 50%)')).toBeNull();
        expect(toMeasurableHex('rgba(0, 0, 0, 0)')).toBeNull();
    });

    it('clamps out-of-range rgb() channels the way CSS does', () => {
        expect(toMeasurableHex('rgb(300 -20 39)')).toBe('#ff0027');
    });

    it('resolves hsl()', () => {
        expect(toMeasurableHex('hsl(0 0% 0%)')).toBe('#000000');
        expect(toMeasurableHex('hsl(0, 0%, 100%)')).toBe('#ffffff');
        expect(toMeasurableHex('hsl(0 100% 50%)')).toBe('#ff0000');
        expect(toMeasurableHex('hsl(120 100% 50%)')).toBe('#00ff00');
        expect(toMeasurableHex('hsl(240 100% 50%)')).toBe('#0000ff');
    });

    it('resolves hsl() hues in every CSS angle unit', () => {
        expect(toMeasurableHex('hsl(120deg 100% 50%)')).toBe('#00ff00');
        expect(toMeasurableHex('hsl(0.3333333turn 100% 50%)')).toBe('#00ff00');
        expect(toMeasurableHex('hsl(133.3333grad 100% 50%)')).toBe('#00ff00');
        expect(toMeasurableHex('hsl(2.0943951rad 100% 50%)')).toBe('#00ff00');
    });

    it('normalises hues outside 0–360', () => {
        expect(toMeasurableHex('hsl(480 100% 50%)')).toBe('#00ff00');
        expect(toMeasurableHex('hsl(-240 100% 50%)')).toBe('#00ff00');
    });

    it('rejects translucent hsl()', () => {
        expect(toMeasurableHex('hsla(220, 39%, 11%, 0.5)')).toBeNull();
    });

    it('requires percentage units on hsl() saturation and lightness', () => {
        expect(toMeasurableHex('hsl(220 39 11)')).toBeNull();
    });

    it('returns null for values that only resolve in the browser', () => {
        expect(toMeasurableHex('var(--wp--preset--color--base)')).toBeNull();
        expect(toMeasurableHex('color-mix(in srgb, #111827 50%, white)')).toBeNull();
    });

    it('returns null for malformed input', () => {
        expect(toMeasurableHex('')).toBeNull();
        expect(toMeasurableHex('   ')).toBeNull();
        expect(toMeasurableHex('rgb(17, 24)')).toBeNull();
        expect(toMeasurableHex('rgb(17, 24, 39, 1, 1)')).toBeNull();
        expect(toMeasurableHex('rgb(a, b, c)')).toBeNull();
        expect(toMeasurableHex('notacolor')).toBeNull();
    });

    it('tolerates surrounding whitespace', () => {
        expect(toMeasurableHex('  #111827  ')).toBe('#111827');
        expect(toMeasurableHex('  rgb( 17 , 24 , 39 )  ')).toBe('#111827');
    });
});
